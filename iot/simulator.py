import os
import sys
import json
import time
import math
import random
from datetime import datetime
import paho.mqtt.client as mqtt
from dotenv import load_dotenv

# COLOR CONSTANTS FOR PREMIUM LOGGING 
RESET = "\033[0m"
BOLD = "\033[1m"
GREEN = "\033[32m"
BLUE = "\033[34m"
YELLOW = "\033[33m"
RED = "\033[31m"
CYAN = "\033[36m"
MAGENTA = "\033[35m"

def get_timestamp():
    return f"{BOLD}[{datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')[:-3]}]{RESET}"

print(f"{CYAN}{BOLD}========================================================")
print("     SMART AGRI CITY — IoT SIMULATOR BOOTSTRAP")
print(f"========================================================{RESET}")

# RESOLVE ENVIRONMENT CONFIGURATION
base_dir = os.path.dirname(os.path.abspath(__file__))
env_paths = [
    os.path.join(base_dir, '.env'),
    os.path.join(base_dir, '..', '.env'),
    os.path.join(base_dir, '..', '..', '.env'),
]

loaded = False
for path in env_paths:
    if os.path.exists(path):
        load_dotenv(path)
        print(f"{get_timestamp()} {GREEN}Success:{RESET} Loaded environment variables from {path}")
        loaded = True
        break

if not loaded:
    load_dotenv()
    print(f"{get_timestamp()} {YELLOW}Warning:{RESET} No explicit .env file found. Using default environment/fallbacks.")

# Load variables
MQTT_BROKER_HOST = os.getenv("MQTT_BROKER_HOST", "localhost")
try:
    MQTT_BROKER_PORT = int(os.getenv("MQTT_BROKER_PORT", 1883))
except ValueError:
    MQTT_BROKER_PORT = 1883

MQTT_USERNAME = os.getenv("MQTT_USERNAME", "iot_agri")
MQTT_PASSWORD = os.getenv("MQTT_PASSWORD", "agri_secret")
MQTT_CLIENT_ID = os.getenv("MQTT_CLIENT_ID", f"agri_premium_sim_{random.randint(1000, 9999)}")
try:
    PUBLISH_INTERVAL = int(os.getenv("MQTT_PUBLISH_INTERVAL", 10))
except ValueError:
    PUBLISH_INTERVAL = 10

print(f"{get_timestamp()} {CYAN}Configured MQTT Broker:{RESET} {MQTT_BROKER_HOST}:{MQTT_BROKER_PORT}")
print(f"{get_timestamp()} {CYAN}Configured Client ID  :{RESET} {MQTT_CLIENT_ID}")
print(f"{get_timestamp()} {CYAN}Publishing Interval   :{RESET} {PUBLISH_INTERVAL}s")

# CORE DATA STRUCTURES 
ZONES = ["zone-a", "zone-b", "zone-c", "zone-d", "zone-e"]
ZONE_NAMES = {
    "zone-a": "Zone A",
    "zone-b": "Zone B",
    "zone-c": "Zone C",
    "zone-d": "Zone D",
    "zone-e": "Zone E"
}
CROPS = {
    "zone-a": "padi",
    "zone-b": "jagung",
    "zone-c": "cabai",
    "zone-d": "tomat",
    "zone-e": "bawang"
}

def clamp(val, min_val, max_val):
    return max(min_val, min(val, max_val))

# ZONE SIMULATOR
class ZoneSimulator:
    def __init__(self, zone_id, crop):
        self.zone_id = zone_id
        self.crop = crop
        self.state = "NORMAL" 
        self.state_counter = 0
        
        self.moisture = random.uniform(40.0, 60.0)
        self.temperature = random.uniform(24.0, 28.0)
        self.ph = random.uniform(6.0, 6.8)
        self.nitrogen = random.uniform(60.0, 100.0)
        self.phosphorus = random.uniform(40.0, 70.0)
        self.potassium = random.uniform(80.0, 140.0)
        
        # Ambient/Air conditions
        self.air_temp = random.uniform(25.0, 30.0)
        self.humidity = random.uniform(65.0, 80.0)
        self.light_lux = 10000.0

    def update(self, hour):
        if self.state == "NORMAL":
            roll = random.random()
            if roll < 0.04:
                self.state = "DROUGHT"
                self.state_counter = random.randint(3, 6)
            elif roll < 0.08:
                self.state = "PEST"
                self.state_counter = random.randint(3, 6)
            elif roll < 0.09:
                if random.random() < 0.5:
                    self.ph = 4.3
                else:
                    self.ph = 8.2
        else:
            self.state_counter -= 1
            if self.state_counter <= 0:
                self.state = "NORMAL"

        time_factor = math.sin((hour - 7) / 24 * 2 * math.pi)

        if self.state == "NORMAL":
            self.moisture = clamp(self.moisture + random.gauss(0, 1.5) - 0.3 * time_factor, 30.0, 75.0)
            self.temperature = clamp(self.temperature + random.gauss(0, 0.4) + 0.8 * time_factor, 22.0, 32.0)
            self.ph = clamp(self.ph + random.gauss(0, 0.04), 5.5, 7.5)
            
            self.nitrogen = clamp(self.nitrogen + random.gauss(0, 2.0), 30.0, 130.0)
            self.phosphorus = clamp(self.phosphorus + random.gauss(0, 1.5), 15.0, 90.0)
            self.potassium = clamp(self.potassium + random.gauss(0, 3.0), 40.0, 180.0)
            
            self.air_temp = clamp(self.air_temp + random.gauss(0, 0.5) + 1.2 * time_factor, 22.0, 34.0)
            self.humidity = clamp(self.humidity + random.gauss(0, 1.5) - 2.5 * time_factor, 55.0, 90.0)

        elif self.state == "DROUGHT":
            self.moisture = clamp(self.moisture - random.uniform(3.0, 6.0), 15.0, 24.0)
            self.temperature = clamp(self.temperature + random.uniform(0.3, 1.0), 30.0, 35.0)
            self.ph = clamp(self.ph + random.gauss(0, 0.05), 5.0, 7.5)
            
            self.nitrogen = clamp(self.nitrogen + random.gauss(0, 1.0), 20.0, 120.0)
            self.phosphorus = clamp(self.phosphorus + random.gauss(0, 1.0), 10.0, 80.0)
            self.potassium = clamp(self.potassium + random.gauss(0, 1.5), 30.0, 150.0)

            self.air_temp = clamp(self.air_temp + random.uniform(0.5, 1.2), 32.0, 36.0)
            self.humidity = clamp(self.humidity - random.uniform(3.0, 6.0), 40.0, 52.0)

        elif self.state == "PEST":
            self.moisture = clamp(self.moisture + random.gauss(0, 1.0), 55.0, 75.0)
            self.temperature = clamp(self.temperature + random.uniform(0.4, 0.8), 32.5, 34.8) 
            self.ph = clamp(self.ph + random.gauss(0, 0.05), 5.5, 7.5)
            
            self.nitrogen = clamp(self.nitrogen + random.gauss(0, 1.5), 30.0, 130.0)
            self.phosphorus = clamp(self.phosphorus + random.gauss(0, 1.0), 15.0, 90.0)
            self.potassium = clamp(self.potassium + random.gauss(0, 2.0), 40.0, 180.0)

            self.air_temp = clamp(self.air_temp + random.uniform(0.4, 1.0), 32.5, 35.5) 
            self.humidity = clamp(self.humidity + random.uniform(3.0, 5.0), 91.0, 97.0) 

        if 6 <= hour <= 18:
            day_fraction = (hour - 6) / 12.0
            lux_curve = math.sin(day_fraction * math.pi)
            self.light_lux = clamp(60000.0 * lux_curve + random.uniform(-5000.0, 5000.0), 5000.0, 80000.0)
        else:
            self.light_lux = 0.0

        return {
            "moisture": round(self.moisture, 2),
            "temperature": round(self.temperature, 2),
            "ph": round(self.ph, 2),
            "nitrogen": round(self.nitrogen, 2),
            "phosphorus": round(self.phosphorus, 2),
            "potassium": round(self.potassium, 2),
            "air_temp": round(self.air_temp, 2),
            "humidity": round(self.humidity, 2),
            "light_lux": round(self.light_lux, 0),
        }

# INITIALIZE SIMULATORS 
simulators = {zone: ZoneSimulator(zone, CROPS[zone]) for zone in ZONES}

# MQTT SETUP 
connected = False

def on_connect(client, userdata, flags, rc, properties=None):
    global connected
    if rc == 0:
        connected = True
        print(f"{get_timestamp()} {GREEN}{BOLD}Connected successfully to MQTT Broker!{RESET}")
    else:
        print(f"{get_timestamp()} {RED}{BOLD}Connection failed with code {rc}{RESET}")

def on_disconnect(client, userdata, disconnect_flags, rc, properties=None):
    global connected
    connected = False
    print(f"{get_timestamp()} {YELLOW}Disconnected from MQTT Broker. Attempting reconnect...{RESET}")

client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2, client_id=MQTT_CLIENT_ID)
client.on_connect = on_connect
client.on_disconnect = on_disconnect

if MQTT_USERNAME and MQTT_PASSWORD:
    client.username_pw_set(MQTT_USERNAME, MQTT_PASSWORD)

# RECONNECTION LOOP 
def connect_broker():
    retry_count = 0
    while True:
        try:
            print(f"{get_timestamp()} Connecting to broker {MQTT_BROKER_HOST}:{MQTT_BROKER_PORT} (Attempt {retry_count + 1})...")
            client.connect(MQTT_BROKER_HOST, MQTT_BROKER_PORT, keepalive=60)
            client.loop_start()
            break
        except Exception as e:
            retry_count += 1
            print(f"{get_timestamp()} {RED}Connection error: {e}{RESET}")
            if retry_count >= 3:
                print(f"{get_timestamp()} {YELLOW}Continuing offline mode for debugging logs. Will attempt reconnecting automatically.{RESET}")
                break
            time.sleep(2)

connect_broker()

# MAIN LOOP 
print(f"\n{GREEN}{BOLD}Starting Simulation Loop. Publishing every {PUBLISH_INTERVAL} seconds...{RESET}\n")

try:
    while True:
        now = datetime.now()
        timestamp_str = now.isoformat()
        
        print(f"\n{BLUE}{BOLD}--- TELEMETRY ROUND: {timestamp_str} ---{RESET}")
        
        for zone in ZONES:
            sim = simulators[zone]
            metrics = sim.update(now.hour)
            
            bundle_payload = {
                **metrics,
                "zone": zone,
                "crop": sim.crop,
                "timestamp": timestamp_str
            }
            
            bundled_topic = f"agri/{zone}/sensor"
            bundle_json = json.dumps(bundle_payload)
            
            if connected:
                client.publish(bundled_topic, bundle_json, qos=1)
                
            for sensor_type, value in metrics.items():
                individual_topic = f"agri/{zone}/{sensor_type}"
                if connected:
                    client.publish(individual_topic, str(value), qos=1)
            
            state_color = GREEN if sim.state == "NORMAL" else (YELLOW if sim.state == "DROUGHT" else RED)
            print(f"[{state_color}{sim.state}{RESET}] {BOLD}{ZONE_NAMES[zone]}{RESET} ({CYAN}{sim.crop}{RESET}): "
                  f"Moist={metrics['moisture']}% | Temp={metrics['temperature']}C | pH={metrics['ph']} | "
                  f"N={metrics['nitrogen']} | P={metrics['phosphorus']} | K={metrics['potassium']} | "
                  f"Humid={metrics['humidity']}%")
            
            # AUTOMATED ALERTS TRIGGERING 
            # Drought Alert (< 25)
            if metrics["moisture"] < 25:
                drought_payload = {
                    "type": "drought_warning",
                    "moisture": metrics["moisture"],
                    "zone": zone,
                    "timestamp": timestamp_str
                }
                alert_json = json.dumps(drought_payload)
                print(f"  +- {YELLOW}{BOLD}[ALERT: DROUGHT] Moisture {metrics['moisture']}% below critical threshold!{RESET}")
                if connected:
                    client.publish(f"agri/{zone}/alert", alert_json, qos=1)
            
            # pH Critical Alert (< 5.5 or > 7.0)
            if metrics["ph"] < 5.5 or metrics["ph"] > 7.0:
                ph_payload = {
                    "type": "ph_critical",
                    "value": metrics["ph"],
                    "zone": zone,
                    "timestamp": timestamp_str
                }
                alert_json = json.dumps(ph_payload)
                print(f"  +- {RED}{BOLD}[ALERT: pH CRITICAL] Soil pH {metrics['ph']} is outside range (5.5 - 7.0)!{RESET}")
                if connected:
                    client.publish(f"agri/{zone}/alert", alert_json, qos=1)

            # Pest Indicator Alert (humidity > 90% and temperature > 32°C)
            if metrics["humidity"] > 90.0 and metrics["temperature"] > 32.0:
                pest_payload = {
                    "type": "pest_indicator",
                    "humidity": metrics["humidity"],
                    "temperature": metrics["temperature"],
                    "zone": zone,
                    "timestamp": timestamp_str
                }
                alert_json = json.dumps(pest_payload)
                print(f"  +- {MAGENTA}{BOLD}[ALERT: PEST RISK] High Humid ({metrics['humidity']}%) & Temp ({metrics['temperature']}C) - Risk of pests!{RESET}")
                if connected:
                    client.publish(f"agri/{zone}/alert", alert_json, qos=1)

        time.sleep(PUBLISH_INTERVAL)

except KeyboardInterrupt:
    print(f"\n{YELLOW}Simulation stopped by user. Cleaning up...{RESET}")
    if connected:
        client.loop_stop()
        client.disconnect()
    print(f"{GREEN}Simulator exited cleanly.{RESET}")
    sys.exit(0)
