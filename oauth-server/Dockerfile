FROM node:20-alpine

LABEL maintainer="Smart Agri City Team"
LABEL service="oauth-server"

WORKDIR /app

RUN apk add --no-cache curl

COPY package*.json ./

RUN npm install --omit=dev

COPY src/ ./src/

EXPOSE 3002

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
  CMD curl -f http://localhost:3002/health || exit 1

CMD ["node", "src/index.js"]
