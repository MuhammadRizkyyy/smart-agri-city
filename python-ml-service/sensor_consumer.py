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

    channel.queue_declare(queue="sensor.new", durable=True)
    channel.queue_declare(queue="irrigation.trigger", durable=True)
    channel.queue_declare(queue="alert.pest", durable=True)
    channel.queue_declare(queue="harvest.ready", durable=True)

    print(f"Connected to RabbitMQ at {RABBITMQ_HOST}:{RABBITMQ_PORT}. Listening on sensor.new...")
except Exception as e:
    print(f"RabbitMQ connection failed: {e}")
    print("Running in offline simulation mode.")
    channel = None


def _safe_encode(encoder, value, field_name, fallback):
    """
    Encode sebuah nilai kategorikal menggunakan LabelEncoder.
    Jika nilai tidak dikenal, log peringatan dan gunakan fallback
    daripada membiarkan transform() crash dengan ValueError.
    """
    valid_values = list(encoder.classes_)
    if value not in valid_values:
        print(f"[Warning] Unknown {field_name} '{value}'. Valid: {valid_values}. Defaulting to '{fallback}'.")
        value = fallback
    return encoder.transform([value])[0]


# SENSOR EVENT PROCESSOR
def process_sensor_event(ch, method, properties, body):
    try:
        payload = json.loads(body)
        zone = payload.get("zone", "Unknown")
        print(f"\n[Event] Received sensor data from zone: {zone}")

        moisture = float(payload.get("soil_moisture", 50.0))
        air_temp = float(payload.get("air_temp", 28.0))

        # --- Irrigation prediction ---
        input_irrig = pd.DataFrame([{
            "soil_moisture": moisture,
            "air_temp": air_temp,
            "rain_forecast": float(payload.get("rain_forecast", 10.0)),
            "growth_phase": _safe_encode(
                models_pack["encoders"]["growth_phase"],
                payload.get("growth_phase", "Vegetatif"),
                "growth_phase",
                "Vegetatif",
            ),
            "evapotranspiration": float(payload.get("evapotranspiration", 4.5)),
        }])
        input_irrig = input_irrig[models_pack["features"]["irrigation"]]
        pred_irrig = models_pack["model_irrig"].predict(input_irrig)[0]
        print(f"[Irrigation] Predicted water needed: {round(float(pred_irrig[0]), 1)} L")

        if moisture < 25.0:
            trigger_payload = {
                "zone": zone,
                "soil_moisture": moisture,
                "urgency": "HIGH",
                "action": "START_PUMP",
            }
            print(f"[ALERT] Moisture critical ({moisture}%). Publishing irrigation.trigger...")
            if ch:
                ch.basic_publish(
                    exchange="",
                    routing_key="irrigation.trigger",
                    body=json.dumps(trigger_payload),
                )

        # --- Pest classification ---
        zone_encoded = _safe_encode(
            models_pack["encoders"]["zone"],
            zone,
            "zone",
            "Zone-A",
        )
        input_pest = pd.DataFrame([{
            "air_humidity": float(payload.get("air_humidity", 75.0)),
            "leaf_temp": float(payload.get("leaf_temp", 27.0)),
            "soil_ph": float(payload.get("soil_ph", 6.2)),
            "chlorophyll": float(payload.get("chlorophyll", 45.0)),
            "light_lux": float(payload.get("light_lux", 5000.0)),
            "zone": zone_encoded,
        }])
        input_pest = input_pest[models_pack["features"]["pest"]]
        pred_pest_idx = models_pack["model_pest"].predict(input_pest)[0]
        pest_label = models_pack["encoders"]["pest_category"].inverse_transform([pred_pest_idx])[0]
        print(f"[Pest] Predicted category: {pest_label}")

        if pest_label != "Sehat":
            pest_payload = {
                "zone": zone,
                "threat_detected": pest_label,
                "severity": "WARNING",
            }
            print(f"[ALERT] Pest threat detected: {pest_label}. Publishing alert.pest...")
            if ch:
                ch.basic_publish(
                    exchange="",
                    routing_key="alert.pest",
                    body=json.dumps(pest_payload),
                )

        # --- Yield prediction ---
        input_yield = pd.DataFrame([{
            "avg_temp": air_temp,
            "rainfall": float(payload.get("rainfall", 1200.0)),
            "soil_moisture": moisture,
            "ph": float(payload.get("soil_ph", 6.2)),
            "nitrogen": float(payload.get("nitrogen", 70.0)),
            "phosphorus": float(payload.get("phosphorus", 45.0)),
            "potassium": float(payload.get("potassium", 50.0)),
            "area_ha": float(payload.get("area_ha", 1.5)),
            "week_of_planting": int(payload.get("week_of_planting", 8)),
        }])
        input_yield = input_yield[models_pack["features"]["yield"]]
        pred_yield = models_pack["model_yield"].predict(input_yield)[0]
        yield_cat = "Tinggi" if pred_yield >= 7.5 else ("Normal" if pred_yield >= 4.0 else "Rendah")
        print(f"[Yield] Predicted: {round(pred_yield, 2)} ton/ha -> {yield_cat}")

        if yield_cat == "Tinggi":
            harvest_payload = {
                "zone": zone,
                "predicted_yield_ton": round(pred_yield, 2),
                "status": "OPTIMAL_HARVEST",
            }
            print(f"[Harvest] High yield detected. Publishing harvest.ready...")
            if ch:
                ch.basic_publish(
                    exchange="",
                    routing_key="harvest.ready",
                    body=json.dumps(harvest_payload),
                )

        if ch:
            ch.basic_ack(delivery_tag=method.delivery_tag)

    except Exception as err:
        print(f"[Error] Failed to process sensor event: {err}")
        if ch:
            ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)


# ENTRY POINT
if channel:
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
        "air_temp": 32.0,
        "rain_forecast": 5.0,
        "growth_phase": "Vegetatif",
        "evapotranspiration": 6.1,
        "air_humidity": 88.5,
        "leaf_temp": 33.0,
        "soil_ph": 5.5,
        "chlorophyll": 32.0,
        "light_lux": 6500.0,
        "zone": "Zone-C",
        "rainfall": 1100.0,
        "nitrogen": 85.0,
        "phosphorus": 50.0,
        "potassium": 60.0,
        "area_ha": 2.0,
        "week_of_planting": 12,
    })
    process_sensor_event(None, None, None, mock_msg)
