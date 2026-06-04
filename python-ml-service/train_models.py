import os
import joblib
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.ensemble import RandomForestRegressor, GradientBoostingClassifier

os.makedirs("models", exist_ok=True)
print("⏳ Packaging core artifacts...")

# 1. YIELD
df_yield = pd.read_csv("data/crop_yield.csv")
model_yield = RandomForestRegressor(n_estimators=50, random_state=42)
model_yield.fit(df_yield.drop(columns=["yield_ton_per_ha"]), df_yield["yield_ton_per_ha"])

# 2. PEST
df_pest = pd.read_csv("data/pest_disease.csv")
le_zone = LabelEncoder().fit(["zona1", "zona2", "zona3", "zona4"])
df_pest["zone"] = le_zone.transform(df_pest["zone"])
le_pest = LabelEncoder().fit(["Sehat", "Wereng", "Blast", "Tungro", "Layu_Fusarium"])
df_pest["pest_category"] = le_pest.transform(df_pest["pest_category"])
model_pest = GradientBoostingClassifier(n_estimators=50, random_state=42)
model_pest.fit(df_pest.drop(columns=["pest_category"]), df_pest["pest_category"])

# 3. IRRIGATION
df_irrig = pd.read_csv("data/irrigation_demand.csv")
le_growth = LabelEncoder().fit(["semai", "vegetatif", "generatif", "panen"])
df_irrig["growth_phase"] = le_growth.transform(df_irrig["growth_phase"])
model_irrig = RandomForestRegressor(n_estimators=50, random_state=42)
model_irrig.fit(df_irrig.drop(columns=["water_needed_liters"]), df_irrig["water_needed_liters"])

# Save package with exact artifact key requested by lecturer
model_payload = {
    "yield": model_yield,
    "pest": model_pest,
    "irrigation": model_irrig,
    "encoders": {"zone": le_zone, "pest_category": le_pest, "growth_phase": le_growth},
    "features": {
        "yield": list(df_yield.drop(columns=["yield_ton_per_ha"]).columns),
        "pest": list(df_pest.drop(columns=["pest_category"]).columns),
        "irrigation": list(df_irrig.drop(columns=["water_needed_liters"]).columns)
    }
}
joblib.dump(model_payload, "models/agri_models.pkl")
print("🎉 Success! Artifact models/agri_models.pkl updated.")
