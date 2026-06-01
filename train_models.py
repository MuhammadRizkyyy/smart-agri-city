import os
import joblib
import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.ensemble import RandomForestRegressor, GradientBoostingClassifier

# Ensure models directory exists
os.makedirs("models", exist_ok=True)

print("⏳ Training 3 Smart Agriculture ML Models...")

# =====================================================================
# 1. TRAIN CROP YIELD MODEL
# =====================================================================
print("\n--- 🌾 Training Crop Yield Predictor ---")
df_yield = pd.read_csv("data/crop_yield.csv")
X_yield = df_yield.drop(columns=["yield_ton_per_ha"])
y_yield = df_yield["yield_ton_per_ha"]

X_train_y, X_test_y, y_train_y, y_test_y = train_test_split(X_yield, y_yield, test_size=0.2, random_state=42)

model_yield = RandomForestRegressor(n_estimators=100, random_state=42)
model_yield.fit(X_train_y, y_train_y)

cv_yield = cross_val_score(model_yield, X_yield, y_yield, cv=5)
r2_yield = model_yield.score(X_test_y, y_test_y)
print(f"✅ Yield Model R² Score: {r2_yield:.4f} (Target >= 0.70)")
print(f"🔄 5-Fold Cross Validation Mean: {cv_yield.mean():.4f}")


# =====================================================================
# 2. TRAIN PEST & DISEASE CLASSIFIER
# =====================================================================
print("\n--- 🐛 Training Pest & Disease Classifier ---")
df_pest = pd.read_csv("data/pest_disease.csv")

# Encode the categorical 'zone' feature and target labels
le_zone = LabelEncoder()
df_pest["zone"] = le_zone.fit_transform(df_pest["zone"])

le_pest = LabelEncoder()
df_pest["pest_category"] = le_pest.fit_transform(df_pest["pest_category"])

X_pest = df_pest.drop(columns=["pest_category"])
y_pest = df_pest["pest_category"]

X_train_p, X_test_p, y_train_p, y_test_p = train_test_split(X_pest, y_pest, test_size=0.2, random_state=42)

# Using Gradient Boosting to tackle the imbalanced datasets gracefully
model_pest = GradientBoostingClassifier(n_estimators=100, random_state=42)
model_pest.fit(X_train_p, y_train_p)

cv_pest = cross_val_score(model_pest, X_pest, y_pest, cv=5)
acc_pest = model_pest.score(X_test_p, y_test_p)
print(f"✅ Pest Model Accuracy: {acc_pest * 100:.2f}% (Target >= 70%)")
print(f"🔄 5-Fold Cross Validation Mean: {cv_pest.mean() * 100:.2f}%")


# =====================================================================
# 3. TRAIN IRRIGATION DEMAND MODEL
# =====================================================================
print("\n--- 💧 Training Irrigation Demand Optimizer ---")
df_irrig = pd.read_csv("data/irrigation_demand.csv")

le_growth = LabelEncoder()
df_irrig["growth_phase"] = le_growth.fit_transform(df_irrig["growth_phase"])

# We are predicting two targets here: water_needed_liters and irrigation_urgency
X_irrig = df_irrig.drop(columns=["water_needed_liters", "irrigation_urgency"])
y_irrig = df_irrig[["water_needed_liters", "irrigation_urgency"]]

X_train_i, X_test_i, y_train_i, y_test_i = train_test_split(X_irrig, y_irrig, test_size=0.2, random_state=42)

model_irrig = RandomForestRegressor(n_estimators=100, random_state=42)
model_irrig.fit(X_train_i, y_train_i)

cv_irrig = cross_val_score(model_irrig, X_irrig, y_irrig, cv=5)
r2_irrig = model_irrig.score(X_test_i, y_test_i)
print(f"✅ Irrigation Model R² Score: {r2_irrig:.4f} (Target >= 0.70)")
print(f"🔄 5-Fold Cross Validation Mean: {cv_irrig.mean():.4f}")


# =====================================================================
# 🗃️ SAVE EVERYTHING INTO A SINGLE PACKAGED ARTIFACT
# =====================================================================
model_payload = {
    "model_yield": model_yield,
    "model_pest": model_pest,
    "model_irrig": model_irrig,
    "encoders": {
        "zone": le_zone,
        "pest_category": le_pest,
        "growth_phase": le_growth
    },
    "features": {
        "yield": list(X_yield.columns),
        "pest": list(X_pest.columns),
        "irrigation": list(X_irrig.columns)
    }
}

joblib.dump(model_payload, "models/agri_models.pkl")
print("\n🎉 Success! All models trained and compiled into models/agri_models.pkl")
