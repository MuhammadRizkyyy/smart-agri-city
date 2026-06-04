import pika
import json
import joblib
import os
import pandas as pd
import numpy as np

print("Starting Smart Agri City ML sensor consumer...")

# LOAD MODEL ARTIFACT
try:
    models_pack = joblib.load("models/agri_models.pkl")
    print("ML models loaded successfully.")
except Exception as e:
    print(f"Error loading model file: {e}. Run train_models.py first.")
    exit(1)

# RABBITMQ CONNECTION
RABBITMQ_HOST = os.environ.get("RABBITMQ_HOST", "localhost")
RABBITMQ_PORT = int(os.environ.get("RABBITMQ_PORT", 5672))
RABBITMQ_USER = os.environ.get("RABBITMQ_USERNAME", "guest")
RABBITMQ_PASS = os.environ.get("RABBITMQ_PASSWORD", "guest")

EXCHANGE = "agri.events"

try:
    credentials = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASS)
    connection = pika.BlockingConnection(
        pika.ConnectionParameters(
            host=RABBITMQ_HOST,
            port=RABBITMQ_PORT,
            credentials=credentials,
        )
    )
    channel = connection.channel()

    # Declare exchange dan bind semua queue yang diperlukan
    channel.exchange_declare(EXCHANGE, exchange_type="topic", durable=True)

    for queue_name, routing_key in [
        ("sensor.new",         "sensor.new"),
        ("irrigation.trigger", "irrigation.trigger"),
        ("alert.pest",         "alert.pest"),
        ("harvest.ready",      "harvest.ready"),
        ("iot.valve",          "iot.valve"),
    ]:
        channel.queue_declare(queue=queue_name, durable=True)
        channel.queue_bind(queue=queue_name, exchange=EXCHANGE, routing_key=routing_key)

    print(f"Connected to RabbitMQ at {RABBITMQ_HOST}:{RABBITMQ_PORT}. Listening on sensor.new...")
except Exception as e:
    print(f"RabbitMQ connection failed: {e}")
    print("Running in offline simulation mode.")
    channel = None


def _safe_encode(encoder, value, field_name, fallback):
    """
    Encode nilai kategorikal dengan LabelEncoder.
    Fallback ke nilai default jika value tidak dikenal.
    """
    valid_values = list(encoder.classes_)
    if value not in valid_values:
        print(f"[Warning] Unknown {field_name} '{value}'. Valid: {valid_values}. Defaulting to '{fallback}'.")
        value = fallback
    return encoder.transform([value])[0]


def _get_float(payload: dict, *keys, default: float = 0.0) -> float:
    """
    Ambil nilai float dari payload dengan multiple fallback keys.
    PHP publisher mengirimkan beberapa alias field untuk kompatibilitas.
    """
    for key in keys:
        val = payload.get(key)
        if val is not None:
            try:
                return float(val)
            except (TypeError, ValueError):
                continue
    return default


# SENSOR EVENT PROCESSOR
def process_sensor_event(ch, method, properties, body):
    try:
        payload = json.loads(body)
        zone    = str(payload.get("zone", payload.get("zone_id", "Unknown")))
        print(f"\n[Event] Received sensor.new from zone: {zone}")

        # Field mapping: PHP kirim kedua alias (soil_moisture + moisture, dst.)
        moisture    = _get_float(payload, "soil_moisture", "moisture",    default=50.0)
        air_temp    = _get_float(payload, "air_temp",      "temperature", default=28.0)
        air_humidity= _get_float(payload, "air_humidity",  "humidity",    default=70.0)
        soil_ph     = _get_float(payload, "soil_ph",       "ph",          default=6.2)
        leaf_temp   = _get_float(payload, "leaf_temp",     "air_temp",    default=air_temp)
        chlorophyll = _get_float(payload, "chlorophyll",                  default=45.0)
        light_lux   = _get_float(payload, "light_lux",                    default=5000.0)
        rain_fcast  = _get_float(payload, "rain_forecast",                default=10.0)
        growth_phase= payload.get("growth_phase", "Vegetatif")
        evapotr     = _get_float(payload, "evapotranspiration",           default=4.5)
        rainfall    = _get_float(payload, "rainfall",                     default=1200.0)
        nitrogen    = _get_float(payload, "nitrogen",                     default=70.0)
        phosphorus  = _get_float(payload, "phosphorus",                   default=45.0)
        potassium   = _get_float(payload, "potassium",                    default=50.0)
        area_ha     = _get_float(payload, "area_ha",                      default=1.5)
        wk_planting = int(_get_float(payload, "week_of_planting",         default=8.0))

        # ── Irrigation prediction ──────────────────────────────────────────
        growth_enc = _safe_encode(
            models_pack["encoders"]["growth_phase"],
            growth_phase, "growth_phase", "Vegetatif",
        )
        input_irrig = pd.DataFrame([{
            "soil_moisture":      moisture,
            "air_temp":           air_temp,
            "rain_forecast":      rain_fcast,
            "growth_phase":       growth_enc,
            "evapotranspiration": evapotr,
        }])
        input_irrig = input_irrig[models_pack["features"]["irrigation"]]
        pred_irrig  = models_pack["model_irrig"].predict(input_irrig)[0]
        water_liters = round(float(pred_irrig[0]) if hasattr(pred_irrig, '__len__') else float(pred_irrig), 1)
        print(f"[Irrigation] Predicted water needed: {water_liters} L")

        # Trigger irigasi jika moisture kritis (< 25%)
        if moisture < 25.0:
            trigger_payload = {
                "zone":         zone,
                "zone_id":      payload.get("zone_id", zone),
                "soil_moisture": moisture,
                "urgency":      "HIGH",
                "action":       "START_PUMP",
                "water_liters": water_liters,
                "timestamp":    payload.get("timestamp", ""),
            }
            print(f"[ALERT] Moisture critical ({moisture}%). Publishing irrigation.trigger...")
            if ch:
                ch.basic_publish(
                    exchange=EXCHANGE,
                    routing_key="irrigation.trigger",
                    body=json.dumps(trigger_payload),
                    properties=pika.BasicProperties(delivery_mode=2),
                )

        # ── Pest classification ────────────────────────────────────────────
        zone_encoded = _safe_encode(
            models_pack["encoders"]["zone"],
            zone, "zone", "Zone-A",
        )
        input_pest = pd.DataFrame([{
            "air_humidity": air_humidity,
            "leaf_temp":    leaf_temp,
            "soil_ph":      soil_ph,
            "chlorophyll":  chlorophyll,
            "light_lux":    light_lux,
            "zone":         zone_encoded,
        }])
        input_pest   = input_pest[models_pack["features"]["pest"]]
        pred_pest_idx= models_pack["model_pest"].predict(input_pest)[0]
        pest_label   = models_pack["encoders"]["pest_category"].inverse_transform([pred_pest_idx])[0]
        print(f"[Pest] Predicted category: {pest_label}")

        if pest_label != "Sehat":
            pest_payload = {
                "zone":             zone,
                "zone_id":          payload.get("zone_id", zone),
                "threat_detected":  pest_label,
                "severity":         "WARNING",
                "timestamp":        payload.get("timestamp", ""),
            }
            print(f"[ALERT] Pest threat detected: {pest_label}. Publishing alert.pest...")
            if ch:
                ch.basic_publish(
                    exchange=EXCHANGE,
                    routing_key="alert.pest",
                    body=json.dumps(pest_payload),
                    properties=pika.BasicProperties(delivery_mode=2),
                )

        # ── Yield prediction ───────────────────────────────────────────────
        input_yield = pd.DataFrame([{
            "avg_temp":         air_temp,
            "rainfall":         rainfall,
            "soil_moisture":    moisture,
            "ph":               soil_ph,
            "nitrogen":         nitrogen,
            "phosphorus":       phosphorus,
            "potassium":        potassium,
            "area_ha":          area_ha,
            "week_of_planting": wk_planting,
        }])
        input_yield = input_yield[models_pack["features"]["yield"]]
        pred_yield  = models_pack["model_yield"].predict(input_yield)[0]
        yield_cat   = "Tinggi" if pred_yield >= 7.5 else ("Normal" if pred_yield >= 4.0 else "Rendah")
        print(f"[Yield] Predicted: {round(pred_yield, 2)} ton/ha -> {yield_cat}")

        if yield_cat == "Tinggi":
            harvest_payload = {
                "zone":                zone,
                "zone_id":             payload.get("zone_id", zone),
                "predicted_yield_ton": round(pred_yield, 2),
                "status":              "OPTIMAL_HARVEST",
                "timestamp":           payload.get("timestamp", ""),
            }
            print(f"[Harvest] High yield detected. Publishing harvest.ready...")
            if ch:
                ch.basic_publish(
                    exchange=EXCHANGE,
                    routing_key="harvest.ready",
                    body=json.dumps(harvest_payload),
                    properties=pika.BasicProperties(delivery_mode=2),
                )

        if ch:
            ch.basic_ack(delivery_tag=method.delivery_tag)

    except Exception as err:
        print(f"[Error] Failed to process sensor event: {err}")
        import traceback
        traceback.print_exc()
        if ch:
            ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)


# ENTRY POINT
if channel:
    channel.basic_qos(prefetch_count=1)
    channel.basic_consume(queue="sensor.new", on_message_callback=process_sensor_event)
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        print("\nConsumer stopped.")
        connection.close()
else:
    print("\n[Simulation] Running offline with mock sensor payload...")
    mock_msg = json.dumps({
        "soil_moisture": 18.5,
        "moisture":      18.5,
        "air_temp":      32.0,
        "temperature":   32.0,
        "rain_forecast": 5.0,
        "growth_phase":  "Vegetatif",
        "evapotranspiration": 6.1,
        "air_humidity":  88.5,
        "humidity":      88.5,
        "leaf_temp":     33.0,
        "soil_ph":       5.5,
        "ph":            5.5,
        "chlorophyll":   32.0,
        "light_lux":     6500.0,
        "zone":          "Zone-C",
        "zone_id":       3,
        "rainfall":      1100.0,
        "nitrogen":      85.0,
        "phosphorus":    50.0,
        "potassium":     60.0,
        "area_ha":       2.0,
        "week_of_planting": 12,
        "timestamp":     "2026-06-04T10:00:00",
    })
    process_sensor_event(None, None, None, mock_msg)
