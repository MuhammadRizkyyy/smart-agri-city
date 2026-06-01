FROM eclipse-mosquitto:2.0

COPY mosquitto.conf /mosquitto/config/mosquitto.conf
COPY mosquitto-entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p /mosquitto/config /mosquitto/data /mosquitto/log \
    && chown -R mosquitto:mosquitto /mosquitto \
    && chmod 755 /mosquitto/config

ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]
