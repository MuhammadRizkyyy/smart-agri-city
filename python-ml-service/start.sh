#!/bin/bash
# start.sh — jalankan FastAPI + RabbitMQ sensor consumer secara bersamaan

set -e

echo "[start.sh] Starting Smart Agri City Python ML Service..."

# Generate dataset jika belum ada
if [ ! -f "data/crop_yield.csv" ] || [ ! -f "data/pest_disease.csv" ] || [ ! -f "data/irrigation_demand.csv" ]; then
    echo "[start.sh] Datasets not found. Running generate_data.py..."
    python generate_data.py
    echo "[start.sh] Dataset generation complete."
fi

# Train models jika artifact belum ada
if [ ! -f "models/agri_models.pkl" ]; then
    echo "[start.sh] Model artifact not found. Running train_models.py..."
    python train_models.py
    echo "[start.sh] Model training complete."
fi

# Jalankan sensor consumer sebagai background process
# Retry otomatis jika RabbitMQ belum ready saat pertama start
echo "[start.sh] Starting RabbitMQ sensor consumer in background..."
while true; do
    python sensor_consumer.py >> /var/log/sensor_consumer.log 2>&1
    echo "[start.sh] Sensor consumer exited. Restarting in 5 seconds..." >&2
    sleep 5
done &

echo "[start.sh] Sensor consumer loop started (PID: $!)"

# Jalankan FastAPI sebagai foreground process (agar container tetap hidup)
echo "[start.sh] Starting FastAPI on port 5000..."
exec uvicorn main:app --host 0.0.0.0 --port 5000
