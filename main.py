import os
import joblib
import pandas as pd
import numpy as np
from typing import List
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

# Initialize FastAPI App with exact title requested
app = FastAPI(title="Smart Agri City ML Service")

# Global placeholder for the model payload
models_pack = None

@app.on_event("startup")
def load_models():
    global models_pack
    model_path = "models/agri_models.pkl"
    if not os.path.exists(model_path):
        raise RuntimeError(f"❌ Model artifact not found at {model_path}. Run train_models.py first!")
    models_pack = joblib.load(model_path)
    print("🚀 Models successfully loaded into memory at startup!")

# =====================================================================
# PYDANTIC INPUT VALIDATORS (PLAN.md Section 6.3 Constraints)
# =====================================================================
class YieldInput(BaseModel):
    avg_temp: float = Field(..., ge=10, le=50, description="Average temperature")
    rainfall: float = Field(..., ge=0, le=5000, description="Annual rainfall mm")
    soil_moisture: float = Field(..., ge=0, le=100, description="Soil moisture percentage")
    ph: float = Field(..., ge=0, le=14, description="Soil pH level")
    nitrogen: float = Field(..., ge=0, le=200, description="Nitrogen level")
    phosphorus: float = Field(..., ge=0, le=200, description="Phosphorus level")
    potassium: float = Field(..., ge=0, le=200, description="Potassium level")
    area_ha: float = Field(..., ge=0.1, le=100.0, description="Farm area in hectares")
    week_of_planting: int = Field(..., ge=1, le=52, description="Week number since planting")

class PestInput(BaseModel):
    air_humidity: float = Field(..., ge=0, le=100)
    leaf_temp: float = Field(..., ge=10, le=50)
    soil_ph: float = Field(..., ge=0, le=14)
    chlorophyll: float = Field(..., ge=0, le=100)
    light_lux: float = Field(..., ge=0, le=100000)
    zone: str = Field(..., description="Must be Zone-A, Zone-B, Zone-C, or Zone-D")

class IrrigationInput(BaseModel):
    soil_moisture: float = Field(..., ge=0, le=100)
    air_temp: float = Field(..., ge=10, le=50)
    rain_forecast: float = Field(..., ge=0, le=200)
    growth_phase: str = Field(..., description="Must be Semai, Vegetatif, Generatif, or Panen")
    evapotranspiration: float = Field(..., ge=0, le=20)

# =====================================================================
# REST ENDPOINTS
# =====================================================================

@app.get("/health")
def health_check():
    return {
        "status": "ok",
        "service": "python-ml",
        "models": ["crop_yield_predictor", "pest_classifier", "irrigation_optimizer"]
    }

@app.post("/predict/yield")
def predict_yield(data: YieldInput):
    # Convert incoming validated Pydantic model to a standard DataFrame row
    input_df = pd.DataFrame([data.dict()])
    
    # Generate prediction from Random Forest array
    pred_yield = float(models_pack["model_yield"].predict(input_df)[0])
    
    # Assign categorized status label
    if pred_yield >= 7.5:
        category = "Tinggi"
    elif pred_yield >= 4.0:
        category = "Normal"
    else:
        category = "Rendah"
        
    return {
        "yield_ton_per_ha": round(pred_yield, 2),
        "category": category
    }

@app.post("/predict/pest")
def predict_pest(data: PestInput):
    # Ensure categorical zone is transformed using the correct LabelEncoder
    try:
        zone_encoded = models_pack["encoders"]["zone"].transform([data.zone])[0]
    except ValueError:
        raise HTTPException(status_code=400, detail=f"Invalid zone input. Choose from: {list(models_pack['encoders']['zone'].classes_)}")
        
    input_dict = data.dict()
    input_dict["zone"] = zone_encoded
    input_df = pd.DataFrame([input_dict])
    
    # Re-order columns strictly matching original model features order
    input_df = input_df[models_pack["features"]["pest"]]
    
    # Classify pest index
    pred_idx = models_pack["model_pest"].predict(input_df)[0]
    pest_label = str(models_pack["encoders"]["pest_category"].inverse_transform([pred_idx])[0])
    
    return {
        "pest_idx": int(pred_idx),
        "pest_category": pest_label
    }

@app.post("/predict/irrigation")
def predict_irrigation(data: IrrigationInput):
    # Ensure growth phase is mapped via LabelEncoder cleanly
    try:
        growth_encoded = models_pack["encoders"]["growth_phase"].transform([data.growth_phase])[0]
    except ValueError:
        raise HTTPException(status_code=400, detail=f"Invalid growth_phase. Choose from: {list(models_pack['encoders']['growth_phase'].classes_)}")
        
    input_dict = data.dict()
    input_dict["growth_phase"] = growth_encoded
    input_df = pd.DataFrame([input_dict])
    
    # Match structural features order
    input_df = input_df[models_pack["features"]["irrigation"]]
    
    # Predict double-array label outputs simultaneously
    preds = models_pack["model_irrig"].predict(input_df)[0]
    
    return {
        "water_needed_liters": round(float(preds[0]), 2),
        "irrigation_urgency": round(float(preds[1]), 2)
    }

@app.get("/model/feature-importance")
def get_feature_importance():
    # Return features configuration array details
    return {
        "yield_model_features": models_pack["features"]["yield"],
        "pest_model_features": models_pack["features"]["pest"],
        "irrigation_model_features": models_pack["features"]["irrigation"]
    }

@app.post("/predict/batch")
def predict_batch(inputs: List[YieldInput]):
    # Process sequential prediction array records fast
    results = []
    for item in inputs:
        input_df = pd.DataFrame([item.dict()])
        pred = float(models_pack["model_yield"].predict(input_df)[0])
        cat = "Tinggi" if pred >= 7.5 else ("Normal" if pred >= 4.0 else "Rendah")
        results.append({"yield_ton_per_ha": round(pred, 2), "category": cat})
    return {"batch_results": results}
