FROM php:8.2-fpm-alpine AS base

LABEL maintainer="Smart Agri City Team"
LABEL service="php-irrigation"

RUN apk add --no-cache \
    nginx \
    curl \
    bash \
    linux-headers \
    rabbitmq-c \
    rabbitmq-c-dev \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install pdo pdo_mysql sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del autoconf g++ make rabbitmq-c-dev linux-headers

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

COPY . .

RUN chmod +x bin/consumer.php

# Nginx config untuk port 8002
RUN mkdir -p /run/nginx && \
    printf 'server {\n\
    listen 8002;\n\
    root /var/www/html/public;\n\
    index index.php;\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
\n\
    location ~ \\.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
}\n' > /etc/nginx/http.d/default.conf

# Entrypoint: jalankan php-fpm, nginx, dan consumer sebagai background process
RUN printf '#!/bin/bash\n\
set -e\n\
\n\
echo "[start.sh] Starting PHP-FPM..."\n\
php-fpm -D\n\
\n\
echo "[start.sh] Starting RabbitMQ consumer in background..."\n\
php /var/www/html/bin/consumer.php >> /var/log/consumer.log 2>&1 &\n\
\n\
echo "[start.sh] Starting Nginx..."\n\
exec nginx -g "daemon off;"\n' > /start.sh && \
    chmod +x /start.sh

EXPOSE 8002

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f http://localhost:8002/health || exit 1

CMD ["/start.sh"]
