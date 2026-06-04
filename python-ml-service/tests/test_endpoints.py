import os
import pytest
from fastapi.testclient import TestClient
from main import app

client = TestClient(app)

# Safety check to ensure model file exists before running test assertions
@pytest.fixture(scope="session", autouse=True)
def check_model_exists():
    model_path = "models/agri_models.pkl"
    if not os.path.exists(model_path):
        pytest.fail(f"❌ Missing critical artifact at {model_path}. Run train_models.py inside inner directory first!")

def test_health_endpoint():
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["models"] == ["yield", "pest", "irrigation"]

def test_predict_yield_valid():
    payload = {
        "avg_temp": 28.5, "rainfall": 1200.0, "soil_moisture": 45.0,
        "ph": 6.2, "nitrogen": 80.0, "phosphorus": 45.0, "potassium": 60.0,
        "area_ha": 2.5, "week_of_planting": 8
    }
    response = client.post("/predict/yield", json=payload)
    assert response.status_code == 200
    data = response.json()
    assert "predicted_yield_ton" in data
    assert 0.0 <= data["predicted_yield_ton"] <= 15.0
    assert data["yield_category"] in ["Tinggi", "Normal", "Rendah"]

def test_predict_yield_invalid_moisture():
    payload = {
        "avg_temp": 28.5, "rainfall": 1200.0, "soil_moisture": 150.0, # Trigger boundary limit violation (>100)
        "ph": 6.2, "nitrogen": 80.0, "phosphorus": 45.0, "potassium": 60.0,
        "area_ha": 2.5, "week_of_planting": 8
    }
    response = client.post("/predict/yield", json=payload)
    assert response.status_code == 422 # Pydantic automatic input rejection

def test_predict_pest_valid():
    payload = {
        "air_humidity": 85.0, "leaf_temp": 24.0, "soil_ph": 6.0,
        "chlorophyll": 45.0, "light_lux": 5000.0, "zone": "zona1"
    }
    response = client.post("/predict/pest", json=payload)
    assert response.status_code == 200
    data = response.json()
    assert data["pest_category"] in ["Sehat", "Wereng", "Blast", "Tungro", "Layu_Fusarium"]
    assert "action_required" in data

def test_predict_irrigation_valid():
    payload = {
        "soil_moisture": 20.0, "air_temp": 30.0, "rain_forecast": 10.0,
        "growth_phase": "vegetatif", "evapotranspiration": 4.5
    }
    response = client.post("/predict/irrigation", json=payload)
    assert response.status_code == 200
    data = response.json()
    assert data["water_needed_liters"] > 0
    assert data["irrigation_urgency"] in ["Kritis", "Segera", "Tidak Perlu"]

def test_predict_irrigation_invalid_phase():
    payload = {
        "soil_moisture": 20.0, "air_temp": 30.0, "rain_forecast": 10.0,
        "growth_phase": "invalid_phase_name",
        "evapotranspiration": 4.5
    }
    response = client.post("/predict/irrigation", json=payload)
    assert response.status_code == 422
