import os
import joblib
import pandas as pd
import numpy as np
from typing import List
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from prometheus_fastapi_instrumentator import Instrumentator
from prometheus_client import Gauge

app = FastAPI(title="Smart Agri City ML Service")


app = FastAPI(title="Smart Agri City ML Service", version="1.0", lifespan=lifespan)
Instrumentator().instrument(app).expose(app)
predicted_yield_metric = Gauge(
    "predicted_yield",
    "Latest predicted crop yield"
)

irrigation_volume_metric = Gauge(
    "irrigation_volume",
    "Latest irrigation volume recommendation"
)

# DEPENDENCY

def get_models(request: Request):
    """
    Dependency yang memastikan model sudah terload sebelum endpoint dieksekusi.
    Jika model belum siap, mengembalikan HTTP 503 daripada crash dengan TypeError.
    """
    models = getattr(request.app.state, "models", None)
    if models is None:
        raise HTTPException(
            status_code=503,
            detail="Model not loaded. Service is initializing or failed to start.",
        )
    return models


# PYDANTIC INPUT VALIDATORS (PLAN.md Section 6.3 Constraints)

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
def predict_yield(data: YieldInput, models=Depends(get_models)):
    """
    Prediksi hasil panen (ton/ha) beserta kategori dan estimasi hari panen.
    Response: predicted_yield_ton, yield_category, estimated_harvest_days
    """
    input_df = pd.DataFrame([data.dict()])
    predicted_yield_ton = round(
        float(models["model_yield"].predict(input_df)[0]),
        2
    )

    predicted_yield_metric.set(predicted_yield_ton)

    if predicted_yield_ton >= 7.5:
        yield_category = "Tinggi"
    elif predicted_yield_ton >= 4.0:
        yield_category = "Normal"
    else:
        yield_category = "Rendah"

    return {
        "predicted_yield_ton": predicted_yield_ton,
        "yield_category": yield_category,
        "estimated_harvest_days": estimate_harvest_days(data.week_of_planting, data.avg_temp),
    }


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
    irrigation_volume_metric.set(water_needed)

    if data.soil_moisture < 25:
        urgency = "Kritis"
    elif data.soil_moisture < 50:
        urgency = "Segera"
    else:
        urgency = "Tidak Perlu"

    return {"water_needed_liters": round(water_needed, 2), "irrigation_urgency": urgency}
