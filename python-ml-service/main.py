import os
import joblib
import pandas as pd
import numpy as np
from contextlib import asynccontextmanager
from typing import List
from fastapi import FastAPI, HTTPException, Depends, Request
from pydantic import BaseModel, Field
from prometheus_fastapi_instrumentator import Instrumentator
from prometheus_client import Gauge

@asynccontextmanager
async def lifespan(app: FastAPI):
    model_path = "models/agri_models.pkl"
    if not os.path.exists(model_path):
        raise RuntimeError(
            f"Model artifact not found at {model_path}. Run train_models.py first."
        )
    app.state.models = joblib.load(model_path)
    print("Models loaded successfully at startup.")
    yield
    # Cleanup saat shutdown — hapus referensi agar GC bisa bebaskan memori
    app.state.models = None
    print("Models released from memory.")


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
    avg_temp: float = Field(..., ge=10, le=50, description="Average temperature (C)")
    rainfall: float = Field(..., ge=0, le=5000, description="Annual rainfall (mm)")
    soil_moisture: float = Field(..., ge=0, le=100, description="Soil moisture (%)")
    ph: float = Field(..., ge=0, le=14, description="Soil pH level")
    nitrogen: float = Field(..., ge=0, le=200, description="Nitrogen content")
    phosphorus: float = Field(..., ge=0, le=200, description="Phosphorus content")
    potassium: float = Field(..., ge=0, le=200, description="Potassium content")
    area_ha: float = Field(..., ge=0.1, le=100.0, description="Farm area (ha)")
    week_of_planting: int = Field(..., ge=1, le=52, description="Week of planting (1-52)")


class PestInput(BaseModel):
    air_humidity: float = Field(..., ge=0, le=100, description="Air humidity (%)")
    leaf_temp: float = Field(..., ge=10, le=50, description="Leaf temperature (C)")
    soil_ph: float = Field(..., ge=0, le=14, description="Soil pH")
    chlorophyll: float = Field(..., ge=0, le=100, description="Chlorophyll content")
    light_lux: float = Field(..., ge=0, le=100000, description="Light intensity (lux)")
    zone: str = Field(..., description="Zone identifier: Zone-A, Zone-B, Zone-C, or Zone-D")


class IrrigationInput(BaseModel):
    soil_moisture: float = Field(..., ge=0, le=100, description="Soil moisture (%)")
    air_temp: float = Field(..., ge=10, le=50, description="Air temperature (C)")
    rain_forecast: float = Field(..., ge=0, le=200, description="Rain forecast (mm)")
    growth_phase: str = Field(..., description="Growth phase: Semai, Vegetatif, Generatif, or Panen")
    evapotranspiration: float = Field(..., ge=0, le=20, description="Evapotranspiration rate")


# HELPERS
_HARVEST_DAYS_BY_PHASE = {
    "Semai": 110,
    "Vegetatif": 85,
    "Generatif": 45,
    "Panen": 0,
}

def estimate_harvest_days(week_of_planting: int, avg_temp: float) -> int:
    """
    Estimasi sisa hari menuju panen berdasarkan minggu tanam dan suhu rata-rata.

    Logika:
    - Minggu 1-13 (musim hujan): baseline 110 hari
    - Minggu 14-26 (transisi): baseline 100 hari
    - Minggu 27-52 (kemarau): baseline 95 hari (ketersediaan air terbatas,
      pertumbuhan lebih lambat meski suhu tinggi)
    - Koreksi suhu: setiap 1C di atas 28C mengurangi 1 hari (max -10 hari)
    """
    if week_of_planting <= 13:
        base_days = 110
    elif week_of_planting <= 26:
        base_days = 100
    else:
        base_days = 95

    temp_correction = min(10, max(0, int(avg_temp - 28)))
    return max(0, base_days - temp_correction)


# ENDPOINTS

@app.get("/health")
def health_check():
    return {
        "status": "ok",
        "service": "python-ml",
        "models": ["crop_yield_predictor", "pest_classifier", "irrigation_optimizer"],
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
def predict_pest(data: PestInput, models=Depends(get_models)):
    """
    Klasifikasi hama/penyakit tanaman dari data sensor daun.
    Response: pest_category, confidence, action_required
    """
    try:
        zone_encoded = models["encoders"]["zone"].transform([data.zone])[0]
    except ValueError:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid zone. Valid values: {list(models['encoders']['zone'].classes_)}",
        )

    input_dict = data.dict()
    input_dict["zone"] = zone_encoded
    input_df = pd.DataFrame([input_dict])
    input_df = input_df[models["features"]["pest"]]

    pred_idx = models["model_pest"].predict(input_df)[0]
    proba = models["model_pest"].predict_proba(input_df)[0]
    pest_category = str(models["encoders"]["pest_category"].inverse_transform([pred_idx])[0])

    return {
        "pest_category": pest_category,
        "confidence": round(float(proba.max()), 3),
        "action_required": pest_category != "Sehat",
    }


@app.post("/predict/irrigation")
def predict_irrigation(data: IrrigationInput, models=Depends(get_models)):
    """
    Rekomendasi kebutuhan air irigasi berdasarkan kondisi lahan.
    Response: water_needed_liters, irrigation_urgency, open_valve_minutes
    """
    try:
        growth_encoded = models["encoders"]["growth_phase"].transform([data.growth_phase])[0]
    except ValueError:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid growth_phase. Valid values: {list(models['encoders']['growth_phase'].classes_)}",
        )

    input_dict = data.dict()
    input_dict["growth_phase"] = growth_encoded
    input_df = pd.DataFrame([input_dict])
    input_df = input_df[models["features"]["irrigation"]]

    preds = models["model_irrig"].predict(input_df)[0]
    water_needed_liters = round(float(preds[0]), 1)
    irrigation_volume_metric.set(water_needed_liters)

    if water_needed_liters > 5000:
        irrigation_urgency = "Kritis"
    elif water_needed_liters > 2000:
        irrigation_urgency = "Segera"
    else:
        irrigation_urgency = "Tidak Perlu"

    return {
        "water_needed_liters": water_needed_liters,
        "irrigation_urgency": irrigation_urgency,
        "open_valve_minutes": round(water_needed_liters / 83.3, 0),
    }


@app.get("/model/feature-importance")
def get_feature_importance(models=Depends(get_models)):
    """
    Bobot fitur (feature importance) dari ketiga model yang terlatih.
    """
    yield_importance = dict(zip(
        models["features"]["yield"],
        [round(float(v), 4) for v in models["model_yield"].feature_importances_],
    ))
    pest_importance = dict(zip(
        models["features"]["pest"],
        [round(float(v), 4) for v in models["model_pest"].feature_importances_],
    ))
    irrigation_importance = dict(zip(
        models["features"]["irrigation"],
        [round(float(v), 4) for v in models["model_irrig"].estimators_[0].feature_importances_],
    ))

    return {
        "crop_yield_predictor": yield_importance,
        "pest_classifier": pest_importance,
        "irrigation_optimizer": irrigation_importance,
    }


@app.post("/predict/batch")
def predict_batch(inputs: List[YieldInput], models=Depends(get_models)):
    """
    Batch prediction untuk yield — terima array input, kembalikan array output.
    """
    results = []
    for item in inputs:
        input_df = pd.DataFrame([item.dict()])
        pred = round(float(models["model_yield"].predict(input_df)[0]), 2)
        cat = "Tinggi" if pred >= 7.5 else ("Normal" if pred >= 4.0 else "Rendah")
        results.append({"predicted_yield_ton": pred, "yield_category": cat})
    return {"batch_results": results}
