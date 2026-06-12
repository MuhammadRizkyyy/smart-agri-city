#!/bin/sh
set -e

PASSWD_FILE="/mosquitto/config/passwd"

if [ -z "$MQTT_USERNAME" ] || [ -z "$MQTT_PASSWORD" ]; then
  echo "[entrypoint] ERROR: MQTT_USERNAME or MQTT_PASSWORD is not set."
  exit 1
fi

echo "[entrypoint] Generating password file for user: $MQTT_USERNAME"
mosquitto_passwd -b -c "$PASSWD_FILE" "$MQTT_USERNAME" "$MQTT_PASSWORD"
chown mosquitto:mosquitto /mosquitto/config/passwd
chmod 600 "$PASSWD_FILE"
echo "[entrypoint] Password file generated at $PASSWD_FILE"

exec mosquitto -c /mosquitto/config/mosquitto.conf
