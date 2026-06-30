import os
import joblib
import pandas as pd
import numpy as np
from contextlib import asynccontextmanager
from typing import Optional
import threading
import time

from fastapi import FastAPI, HTTPException, Depends, Request
from pydantic import BaseModel, Field
from prometheus_fastapi_instrumentator import Instrumentator
from prometheus_client import Gauge

# Prometheus custom metrics
predicted_yield_metric = Gauge(
    "predicted_yield",
    "Latest predicted crop yield (ton/ha)"
)

irrigation_volume_metric = Gauge(
    "irrigation_volume",
    "Latest irrigation volume recommendation (liters)"
)

# Model loading via lifespan 
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load ML models on startup, release on shutdown."""
    models_path = os.path.join(os.path.dirname(__file__), "models", "agri_models.pkl")
    app.state.models = None
    app.state.models_loading = True
    
    def load_models_async():
        """Load models in background thread to avoid blocking startup."""
        try:
            start = time.time()
            app.state.models = joblib.load(models_path)
            elapsed = time.time() - start
            print(f"[ML] ✓ Models loaded in {elapsed:.2f}s from {models_path}")
        except Exception as e:
            print(f"[ML] ✗ CRITICAL: Could not load models: {e}")
            app.state.models = None
        finally:
            app.state.models_loading = False
    
    # Load models asynchronously in background thread
    loader_thread = threading.Thread(target=load_models_async, daemon=True)
    loader_thread.start()
    
    # Wait max 30s for models to load (don't block container startup)
    for i in range(300):
        if not app.state.models_loading:
            break
        time.sleep(0.1)
    
    if app.state.models is None and app.state.models_loading:
        print(f"[ML] ⚠ Models still loading after 30s — requests will wait or return 503")
    
    yield
    app.state.models = None
    print("[ML] Models released.")

app = FastAPI(
    title="Smart Agri City ML Service",
    version="1.0.0",
    description="Machine learning endpoints for yield, pest, and irrigation prediction",
    lifespan=lifespan,
)

Instrumentator().instrument(app).expose(app)


# Dependency 
def get_models(request: Request):
    """
    Ensures models are loaded before endpoint execution.
    Returns HTTP 503 if models are not ready with retry hint.
    """
    models = getattr(request.app.state, "models", None)
    is_loading = getattr(request.app.state, "models_loading", False)
    
    if models is None:
        if is_loading:
            raise HTTPException(
                status_code=503,
                detail="Model is still loading. Retry in 5-10 seconds. Service initializing...",
            )
        else:
            raise HTTPException(
                status_code=503,
                detail="Model failed to load. Service configuration error.",
            )
    return models


# Pydantic input validators 
class YieldInput(BaseModel):
    avg_temp: float         = Field(..., ge=10, le=50)
    rainfall: float         = Field(..., ge=0, le=5000)
    soil_moisture: float    = Field(..., ge=0, le=100)
    ph: float               = Field(..., ge=0, le=14)
    nitrogen: float         = Field(..., ge=0, le=200)
    phosphorus: float       = Field(..., ge=0, le=200)
    potassium: float        = Field(..., ge=0, le=200)
    area_ha: float          = Field(..., ge=0.1, le=100.0)
    week_of_planting: int   = Field(..., ge=1, le=52)


class PestInput(BaseModel):
    air_humidity: float = Field(..., ge=0, le=100)
    leaf_temp: float    = Field(..., ge=10, le=50)
    soil_ph: float      = Field(..., ge=0, le=14)
    chlorophyll: float  = Field(..., ge=0, le=100)
    light_lux: float    = Field(..., ge=0, le=100000)
    zone: str


class IrrigationInput(BaseModel):
    soil_moisture: float        = Field(..., ge=0, le=100)
    air_temp: float             = Field(..., ge=10, le=50)
    rain_forecast: float        = Field(..., ge=0, le=200)
    growth_phase: str
    evapotranspiration: float   = Field(..., ge=0, le=20)


# Helpers 
def estimate_harvest_days(week_of_planting: int, avg_temp: float) -> int:
    """Simple heuristic: base 90 days, adjusted by temperature and planting week."""
    base_days = 90
    temp_factor = max(0, (avg_temp - 25) * 0.5)
    week_factor = max(0, (26 - week_of_planting) * 0.3)
    return max(30, int(base_days - temp_factor + week_factor))


# Routes 
@app.get("/health")
def health_check(request: Request):
    models_loaded = getattr(request.app.state, "models", None) is not None
    return {
        "status": "ok" if models_loaded else "degraded",
        "service": "python-ml",
        "models_loaded": models_loaded,
        "models": ["yield", "pest", "irrigation"],
    }


@app.post("/predict/yield")
def predict_yield(data: YieldInput, models=Depends(get_models)):
    """
    Prediksi hasil panen (ton/ha).
    Response: predicted_yield_ton, yield_category, estimated_harvest_days
    """
    features = models.get("features", {}).get("yield")
    model    = models.get("yield") or models.get("model_yield")

    if model is None:
        raise HTTPException(status_code=503, detail="Yield model not available")

    input_df = pd.DataFrame([data.model_dump()])
    if features:
        available = [f for f in features if f in input_df.columns]
        input_df = input_df[available]

    predicted_yield_ton = round(float(model.predict(input_df)[0]), 2)
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
    Klasifikasi kategori hama berdasarkan kondisi lingkungan.
    Response: pest_category, action_required
    """
    valid_zones = ["zona1", "zona2", "zona3", "zona4"]
    if data.zone not in valid_zones:
        raise HTTPException(status_code=422, detail=f"Invalid zone. Valid: {valid_zones}")

    encoders    = models.get("encoders", {})
    model_pest  = models.get("pest")
    features    = models.get("features", {}).get("pest")

    if model_pest is None:
        raise HTTPException(status_code=503, detail="Pest model not available")

    zone_encoded = int(encoders["zone"].transform([data.zone])[0])
    input_dict   = data.model_dump()
    input_dict["zone"] = zone_encoded
    input_df = pd.DataFrame([input_dict])
    if features:
        input_df = input_df[features]

    raw_pred   = model_pest.predict(input_df)
    pred_idx   = int(np.atleast_1d(raw_pred)[0])
    pest_label = str(encoders["pest_category"].inverse_transform([pred_idx])[0])

    return {"pest_category": pest_label, "action_required": pest_label != "Sehat"}


@app.post("/predict/irrigation")
def predict_irrigation(data: IrrigationInput, models=Depends(get_models)):
    """
    Prediksi kebutuhan air irigasi (liter).
    Response: water_needed_liters, irrigation_urgency
    """
    valid_phases = ["semai", "vegetatif", "generatif", "panen"]
    if data.growth_phase.lower() not in valid_phases:
        raise HTTPException(status_code=422, detail=f"Invalid growth_phase. Valid: {valid_phases}")

    encoders         = models.get("encoders", {})
    model_irrigation = models.get("irrigation")
    features         = models.get("features", {}).get("irrigation")

    if model_irrigation is None:
        raise HTTPException(status_code=503, detail="Irrigation model not available")

    growth_encoded = int(encoders["growth_phase"].transform([data.growth_phase.lower()])[0])
    input_dict     = data.model_dump()
    input_dict["growth_phase"] = growth_encoded
    input_df = pd.DataFrame([input_dict])
    if features:
        input_df = input_df[features]

    water_needed = float(np.atleast_1d(model_irrigation.predict(input_df))[0])
    irrigation_volume_metric.set(water_needed)

    if data.soil_moisture < 25:
        urgency = "Kritis"
    elif data.soil_moisture < 50:
        urgency = "Segera"
    else:
        urgency = "Tidak Perlu"

    return {
        "water_needed_liters": round(water_needed, 2),
        "irrigation_urgency": urgency,
    }
