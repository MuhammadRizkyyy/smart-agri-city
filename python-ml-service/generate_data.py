import os
import numpy as np
import pandas as pd

np.random.seed(42)
os.makedirs("data", exist_ok=True)

print("Generating smart agriculture datasets...")

# 1. CROP YIELD DATASET  (target R2 >= 0.70)
n_yield = 2200
avg_temp        = np.random.uniform(24, 34, n_yield)
rainfall        = np.random.uniform(500, 2500, n_yield)
soil_moisture   = np.random.uniform(20, 80, n_yield)
ph              = np.random.uniform(5.5, 7.5, n_yield)
nitrogen        = np.random.uniform(20, 120, n_yield)
phosphorus      = np.random.uniform(15, 90, n_yield)
potassium       = np.random.uniform(15, 100, n_yield)
area_ha         = np.random.uniform(0.5, 5.0, n_yield)
week_of_planting = np.random.randint(1, 17, n_yield)

base_yield = (
    (soil_moisture * 0.4)
    + (nitrogen * 0.5)
    + (phosphorus * 0.3)
    + (potassium * 0.3)
    - (abs(ph - 6.5) * 15)
    - (abs(avg_temp - 28) * 4)
)
noise = np.random.normal(0, 1.5, n_yield)
yield_ton_per_ha = np.clip((base_yield + noise) / 10, 1.5, 12.0)

df_yield = pd.DataFrame({
    "avg_temp": avg_temp,
    "rainfall": rainfall,
    "soil_moisture": soil_moisture,
    "ph": ph,
    "nitrogen": nitrogen,
    "phosphorus": phosphorus,
    "potassium": potassium,
    "area_ha": area_ha,
    "week_of_planting": week_of_planting,
    "yield_ton_per_ha": yield_ton_per_ha,
})
df_yield.to_csv("data/crop_yield.csv", index=False)
print(f"Created data/crop_yield.csv  ({len(df_yield)} rows)")


# 2. PEST & DISEASE DATASET  (target accuracy >= 70%)
n_pest       = 1600
air_humidity = np.random.uniform(50, 95, n_pest)
leaf_temp    = np.random.uniform(22, 35, n_pest)
soil_ph      = np.random.uniform(5.0, 8.0, n_pest)
chlorophyll  = np.random.uniform(30, 60, n_pest)
light_lux    = np.random.uniform(2000, 10000, n_pest)
zone         = np.random.choice(["Zone-A", "Zone-B", "Zone-C", "Zone-D"], n_pest)

pest_categories = []
for i in range(n_pest):
    if air_humidity[i] > 83 and leaf_temp[i] < 25.5:
        prob = [0.02, 0.03, 0.90, 0.03, 0.02]   # Blast
    elif air_humidity[i] > 80 and leaf_temp[i] > 31.5:
        prob = [0.02, 0.90, 0.03, 0.03, 0.02]   # Wereng
    elif chlorophyll[i] < 36 and soil_ph[i] < 5.8:
        prob = [0.03, 0.02, 0.02, 0.85, 0.08]   # Tungro
    elif chlorophyll[i] < 39 and soil_ph[i] > 7.2:
        prob = [0.03, 0.02, 0.02, 0.08, 0.85]   # Layu_Fusarium
    else:
        prob = [0.92, 0.02, 0.02, 0.02, 0.02]   # Sehat

    prob = np.array(prob) / np.sum(prob)
    cat = np.random.choice(["Sehat", "Wereng", "Blast", "Tungro", "Layu_Fusarium"], p=prob)
    pest_categories.append(cat)

df_pest = pd.DataFrame({
    "air_humidity": air_humidity,
    "leaf_temp": leaf_temp,
    "soil_ph": soil_ph,
    "chlorophyll": chlorophyll,
    "light_lux": light_lux,
    "zone": zone,
    "pest_category": pest_categories,
})
df_pest.to_csv("data/pest_disease.csv", index=False)
print(f"Created data/pest_disease.csv ({len(df_pest)} rows)")


# 3. IRRIGATION DEMAND DATASET  (target R2 >= 0.70)
n_irrig      = 1600
s_moisture   = np.random.uniform(10, 90, n_irrig)
air_temp     = np.random.uniform(24, 36, n_irrig)
rain_forecast = np.random.uniform(0, 50, n_irrig)
growth_phase = np.random.choice(["Semai", "Vegetatif", "Generatif", "Panen"], n_irrig)
evapo        = np.random.uniform(2.0, 8.0, n_irrig)

water_needed = []
urgency = []

for i in range(n_irrig):
    base_water = (100 - s_moisture[i]) * 5 + (air_temp[i] * 10) - (rain_forecast[i] * 8)
    if growth_phase[i] in ["Vegetatif", "Generatif"]:
        base_water += 150
    water = max(0, base_water + np.random.normal(0, 10))
    water_needed.append(round(water, 2))

    urg_score = (100 - s_moisture[i]) / 100 * 0.7 + (air_temp[i] / 36) * 0.3
    if rain_forecast[i] > 25:
        urg_score -= 0.4
    urgency.append(round(float(np.clip(urg_score, 0.0, 1.0)), 2))

df_irrig = pd.DataFrame({
    "soil_moisture": s_moisture,
    "air_temp": air_temp,
    "rain_forecast": rain_forecast,
    "growth_phase": growth_phase,
    "evapotranspiration": evapo,
    "water_needed_liters": water_needed,
    "irrigation_urgency": urgency,
})
df_irrig.to_csv("data/irrigation_demand.csv", index=False)
print(f"Created data/irrigation_demand.csv ({len(df_irrig)} rows)")
print("Dataset generation complete.")
