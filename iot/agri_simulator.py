import paho.mqtt.client as mqtt
import json, time, random, math
from datetime import datetime

BROKER = "localhost"
ZONES  = ["zona1", "zona2", "zona3", "zona4"]
CROPS  = {"zona1": "padi", "zona2": "jagung",
           "zona3": "cabai", "zona4": "tomat"}

client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client.username_pw_set("iot_agri", "agri_secret")
client.connect(BROKER, 1883)

def simulate_soil(zone, hour):
    # Kelembaban tanah lebih rendah siang hari (evapotranspirasi)
    base_moisture = 65 if 6 <= hour <= 10 else 45
    moist = max(10, base_moisture + random.gauss(0, 8))
    return {
        "moisture": round(moist, 2),
        "temperature": round(random.uniform(24, 34), 2),
        "ph": round(random.uniform(5.5, 7.2), 2),
        "nitrogen": round(random.uniform(20, 80), 2),
        "phosphorus": round(random.uniform(15, 60), 2),
        "potassium": round(random.uniform(30, 100), 2),
    }

def simulate_air(zone):
    return {
        "air_temp": round(random.uniform(22, 36), 2),
        "humidity": round(random.uniform(55, 90), 2),
        "light_lux": round(random.uniform(5000, 80000), 0),
    }

while True:
    now = datetime.now()
    for zone in ZONES:
        soil = simulate_soil(zone, now.hour)
        air  = simulate_air(zone)
        payload = {**soil, **air,
                   "zone": zone, "crop": CROPS[zone],
                   "timestamp": now.isoformat()}
        client.publish(f"agri/{zone}/sensor", json.dumps(payload), qos=1)
        print(f"[PUBLISH] Data sensor {zone} berhasil dikirim!")

        # Alert jika pH kritis (di luar batas 5.5–7.0)
        if payload["ph"] < 5.5 or payload["ph"] > 7.0:
            client.publish(f"agri/{zone}/alert",
                json.dumps({"type": "ph_critical", "value": payload["ph"],
                            "zone": zone, "timestamp": now.isoformat()}), qos=1)

        # Alert jika kelembaban sangat rendah (kekeringan)
        if payload["moisture"] < 25:
            client.publish(f"agri/{zone}/alert",
                json.dumps({"type": "drought_warning", "moisture": payload["moisture"],
                            "zone": zone, "timestamp": now.isoformat()}), qos=1)
    time.sleep(30)