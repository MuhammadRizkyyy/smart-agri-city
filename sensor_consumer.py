import pika
import json
import joblib
import pandas as pd
import numpy as np

print("⏳ Starting Smart Agri City ML Background Consumer...")

# =====================================================================
# 🗃️ LOAD MACHINE LEARNING ENGINE ARTIFACT
# =====================================================================
try:
    models_pack = joblib.load("models/agri_models.pkl")
    print("✅ ML Engine and encoders loaded cleanly into background memory!")
except Exception as e:
    print(f"❌ Error loading model file: {e}. Make sure to run train_models.py first.")
    exit(1)

# =====================================================================
# 🔌 AMQP RABBITMQ NETWORK CONNECTION
# =====================================================================
# Connecting to standard local instance. In production k8s, this maps to rabbitmq cluster.
try:
    connection = pika.BlockingConnection(pika.ConnectionParameters(host='localhost'))
    channel = connection.channel()
    
    # Declare the input queue we are subscribing to
    channel.queue_declare(queue='sensor.new', durable=True)
    
    # Declare outbound alert queues we might publish events into
    channel.queue_declare(queue='irrigation.trigger', durable=True)
    channel.queue_declare(queue='alert.pest', durable=True)
    channel.queue_declare(queue='harvest.ready', durable=True)
    
    print("🚀 Successfully connected to RabbitMQ Broker. Listening for events on queue: 'sensor.new'...")
except Exception as e:
    print(f"⚠️ RabbitMQ Connection skipped/failed: {e}")
    print("ℹ️ Script running in offline simulation validation mode.")
    channel = None

# =====================================================================
# 🧠 TELEMETRY PROCESSING CORE PIPELINE
# =====================================================================
def process_sensor_event(ch, method, properties, body):
    try:
        # 1. Parse incoming live sensor JSON packet data cleanly
        payload = json.loads(body)
        print(f"\n📥 [Received Event] Processing telemetry data packet for field zone: {payload.get('zone', 'Unknown')}")
        
        # --- MODEL 1: IRRIGATION OPTIMIZATION INSIGHT ---
        moisture = float(payload.get('soil_moisture', 50.0))
        air_temp = float(payload.get('air_temp', 28.0))
        
        input_irrig = pd.DataFrame([{
            "soil_moisture": moisture,
            "air_temp": air_temp,
            "rain_forecast": float(payload.get('rain_forecast', 10.0)),
            "growth_phase": models_pack["encoders"]["growth_phase"].transform([payload.get('growth_phase', 'Vegetatif')])[0],
            "evapotranspiration": float(payload.get('evapotranspiration', 4.5))
        }])
        input_irrig = input_irrig[models_pack["features"]["irrigation"]]
        pred_irrig = models_pack["model_irrig"].predict(input_irrig)[0]
        
        # Critical Trigger Check requested by Linear ticket DoD
        if moisture < 25.0:
            trigger_payload = {"zone": payload.get('zone'), "soil_moisture": moisture, "urgency": "HIGH", "action": "START_PUMP"}
            print(f"🚨 [ALERT] Moisture low ({moisture}%). Pushing irrigation trigger event...")
            if ch:
                ch.basic_publish(exchange='', routing_key='irrigation.trigger', body=json.dumps(trigger_payload))

        # --- MODEL 2: PEST DETECTION & CLASSIFICATION ---
        zone_raw = payload.get('zone', 'Zone-A')
        zone_encoded = models_pack["encoders"]["zone"].transform([zone_raw])[0]
        
        input_pest = pd.DataFrame([{
            "air_humidity": float(payload.get('air_humidity', 75.0)),
            "leaf_temp": float(payload.get('leaf_temp', 27.0)),
            "soil_ph": float(payload.get('soil_ph', 6.2)),
            "chlorophyll": float(payload.get('chlorophyll', 45.0)),
            "light_lux": float(payload.get('light_lux', 5000.0)),
            "zone": zone_encoded
        }])
        input_pest = input_pest[models_pack["features"]["pest"]]
        pred_pest_idx = models_pack["model_pest"].predict(input_pest)[0]
        pest_label = models_pack["encoders"]["pest_category"].inverse_transform([pred_pest_idx])[0]
        
        # Outbound Warning Check requested by Linear ticket DoD
        if pest_label != "Sehat":
            pest_payload = {"zone": zone_raw, "threat_detected": pest_label, "severity": "WARNING"}
            print(f"🐛 [ALERT] Outbreak threat '{pest_label}' flagged! Pushing alert data...")
            if ch:
                ch.basic_publish(exchange='', routing_key='alert.pest', body=json.dumps(pest_payload))

        # --- MODEL 3: HARVEST YIELD ANALYSIS ---
        input_yield = pd.DataFrame([{
            "avg_temp": air_temp,
            "rainfall": float(payload.get('rainfall', 1200.0)),
            "soil_moisture": moisture,
            "ph": float(payload.get('soil_ph', 6.2)),
            "nitrogen": float(payload.get('nitrogen', 70.0)),
            "phosphorus": float(payload.get('phosphorus', 45.0)),
            "potassium": float(payload.get('potassium', 50.0)),
            "area_ha": float(payload.get('area_ha', 1.5)),
            "week_of_planting": int(payload.get('week_of_planting', 8))
        }])
        input_yield = input_yield[models_pack["features"]["yield"]]
        pred_yield = models_pack["model_yield"].predict(input_yield)[0]
        yield_cat = "Tinggi" if pred_yield >= 7.5 else ("Normal" if pred_yield >= 4.0 else "Rendah")
        
        # Ready For Market Status Check requested by Linear ticket DoD
        if yield_cat == "Tinggi":
            harvest_payload = {"zone": zone_raw, "predicted_yield_ton": round(pred_yield, 2), "status": "OPTIMAL_HARVEST"}
            print(f"🌾 [HARVEST] Yield classification HIGH ({round(pred_yield,2)} ton/ha). Pushing readiness event...")
            if ch:
                ch.basic_publish(exchange='', routing_key='harvest.ready', body=json.dumps(harvest_payload))

        # Acknowledge processed packet message safely back to queue cluster
        if ch:
            ch.basic_ack(delivery_tag=method.delivery_tag)
            
    except Exception as err:
        print(f"❌ Error decoding message telemetry stream array: {err}")
        if ch:
            ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

# =====================================================================
# ⚙️ EXECUTION START BLOCK
# =====================================================================
if channel:
    channel.basic_consume(queue='sensor.new', on_message_callback=process_sensor_event)
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        print("\n🛑 Consumer background service stopped cleanly by user request.")
        connection.close()
else:
    print("\n⚠️ Simulation Mode Run: Simulating a live incoming telemetry event stream pack...")
    # Creating sample mock data matching fields format exactly
    mock_msg = json.dumps({
        "soil_moisture": 18.5, "air_temp": 32.0, "rain_forecast": 5.0, "growth_phase": "Vegetatif",
        "evapotranspiration": 6.1, "air_humidity": 88.5, "leaf_temp": 33.0, "soil_ph": 5.5,
        "chlorophyll": 32.0, "light_lux": 6500.0, "zone": "Zone-C", "rainfall": 1100.0,
        "nitrogen": 85.0, "phosphorus": 50.0, "potassium": 60.0, "area_ha": 2.0, "week_of_planting": 12
    })
    process_sensor_event(None, None, None, mock_msg)
