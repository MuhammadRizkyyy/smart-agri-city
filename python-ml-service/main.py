import os
import joblib
import pandas as pd
import numpy as np
from typing import List
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

app = FastAPI(title="Smart Agri City ML Service")

# Load the models immediately at the global level so pytest can see them instantly!
model_path = "models/agri_models.pkl"
if os.path.exists(model_path):
    models_pack = joblib.load(model_path)
    print("Models successfully loaded into memory globally!")
else:
    models_pack = None
    print("Warning: Model artifact file not found yet.")

class YieldInput(BaseModel):
    avg_temp: float = Field(..., ge=10, le=50)
    rainfall: float = Field(..., ge=0, le=5000)
    soil_moisture: float = Field(..., ge=0, le=100)
    ph: float = Field(..., ge=0, le=14)
    nitrogen: float = Field(..., ge=0, le=200)
    phosphorus: float = Field(..., ge=0, le=200)
    potassium: float = Field(..., ge=0, le=200)
    area_ha: float = Field(..., ge=0.1, le=100.0)
    week_of_planting: int = Field(..., ge=1, le=52)

class PestInput(BaseModel):
    air_humidity: float = Field(..., ge=0, le=100)
    leaf_temp: float = Field(..., ge=10, le=50)
    soil_ph: float = Field(..., ge=0, le=14)
    chlorophyll: float = Field(..., ge=0, le=100)
    light_lux: float = Field(..., ge=0, le=100000)
    zone: str

class IrrigationInput(BaseModel):
    soil_moisture: float = Field(..., ge=0, le=100)
    air_temp: float = Field(..., ge=10, le=50)
    rain_forecast: float = Field(..., ge=0, le=200)
    growth_phase: str
    evapotranspiration: float = Field(..., ge=0, le=20)

@app.get("/health")
def health_check():
    return {
        "status": "ok",
        "service": "python-ml",
        "models": ["yield", "pest", "irrigation"]
    }

@app.post("/predict/yield")
def predict_yield(data: YieldInput):
    if models_pack is None:
        raise HTTPException(status_code=500, detail="Model engine offline")
    input_df = pd.DataFrame([data.model_dump()])[models_pack["features"]["yield"]]
    raw_pred = models_pack["yield"].predict(input_df)
    pred_yield = float(np.atleast_1d(raw_pred)[0])
    category = "Tinggi" if pred_yield >= 7.5 else ("Normal" if pred_yield >= 4.0 else "Rendah")
    return {"predicted_yield_ton": round(pred_yield, 2), "yield_category": category}

@app.post("/predict/pest")
def predict_pest(data: PestInput):
    if models_pack is None:
        raise HTTPException(status_code=500, detail="Model engine offline")
    if data.zone not in ["zona1", "zona2", "zona3", "zona4"]:
        raise HTTPException(status_code=422, detail="Invalid zone boundary value")

    zone_encoded = int(models_pack["encoders"]["zone"].transform([data.zone])[0])
    input_dict = data.model_dump()
    input_dict["zone"] = zone_encoded
    input_df = pd.DataFrame([input_dict])[models_pack["features"]["pest"]]

    raw_pred = models_pack["pest"].predict(input_df)
    pred_idx = int(np.atleast_1d(raw_pred)[0])
    pest_label = str(models_pack["encoders"]["pest_category"].inverse_transform([pred_idx])[0])
    return {"pest_category": pest_label, "action_required": pest_label != "Sehat"}

@app.post("/predict/irrigation")
def predict_irrigation(data: IrrigationInput):
    if models_pack is None:
        raise HTTPException(status_code=500, detail="Model engine offline")
    if data.growth_phase not in ["semai", "vegetatif", "generatif", "panen"]:
        raise HTTPException(status_code=422, detail="Invalid growth phase value")

    growth_encoded = int(models_pack["encoders"]["growth_phase"].transform([data.growth_phase])[0])
    input_dict = data.model_dump()
    input_dict["growth_phase"] = growth_encoded
    input_df = pd.DataFrame([input_dict])[models_pack["features"]["irrigation"]]

    raw_pred = models_pack["irrigation"].predict(input_df)
    water_needed = float(np.atleast_1d(raw_pred)[0])

    if data.soil_moisture < 25:
        urgency = "Kritis"
    elif data.soil_moisture < 50:
        urgency = "Segera"
    else:
        urgency = "Tidak Perlu"

    return {"water_needed_liters": round(water_needed, 2), "irrigation_urgency": urgency}
