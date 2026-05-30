FROM python:3.11-slim

LABEL maintainer="Smart Agri City Team"
LABEL service="iot-simulator"

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

RUN pip install --no-cache-dir \
    paho-mqtt==2.1.0 \
    python-dotenv==1.0.0

COPY agri_simulator.py .
COPY simulator.py .

ENV MQTT_BROKER_HOST=mosquitto
ENV MQTT_BROKER_PORT=1883
ENV MQTT_USERNAME=admin
ENV MQTT_PASSWORD=adminpass
ENV MQTT_CLIENT_ID=agri_simulator_docker
ENV MQTT_PUBLISH_INTERVAL=10

CMD ["python", "agri_simulator.py"]
