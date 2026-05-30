FROM php:8.2-fpm-alpine AS base

LABEL maintainer="Smart Agri City Team"
LABEL service="php-irrigation"

RUN apk add --no-cache \
    nginx \
    curl \
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

RUN printf '#!/bin/sh\nphp-fpm -D\nnginx -g "daemon off;"\n' > /start.sh && \
    chmod +x /start.sh

EXPOSE 8002

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 \
  CMD curl -f http://localhost:8002/health || exit 1

CMD ["/start.sh"]
