# python-ml-service/Dockerfile
FROM python:3.11-slim

LABEL maintainer="Smart Agri City Team"
LABEL service="python-ml-service"

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy requirements first for better layer caching
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application source
COPY . .

# Create models directory
RUN mkdir -p models data

EXPOSE 5000

HEALTHCHECK --interval=30s --timeout=15s --start-period=60s --retries=3 \
  CMD curl -f http://localhost:5000/health || exit 1

# CMD akan di-override saat aplikasi sudah diimplementasi
CMD ["python", "-c", "import time; print('ML service placeholder running'); [time.sleep(10) for _ in iter(int, 1)]"]
