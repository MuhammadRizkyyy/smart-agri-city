FROM python:3.11-slim

LABEL maintainer="Smart Agri City Team"
LABEL service="python-ml-service"

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install dependencies first (better layer caching)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy all service source
COPY . .

# Ensure directories exist and start script is executable
RUN mkdir -p models data /var/log && chmod +x /app/start.sh

EXPOSE 5000

HEALTHCHECK --interval=30s --timeout=15s --start-period=60s --retries=3 \
  CMD curl -f http://localhost:5000/health || exit 1

# start.sh: jalankan sensor_consumer.py (background) + FastAPI (foreground)
CMD ["/app/start.sh"]
