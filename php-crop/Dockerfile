FROM php:8.2-fpm-alpine AS base

LABEL maintainer="Smart Agri City Team"
LABEL service="php-crop"

RUN apk add --no-cache nginx curl supervisor linux-headers

RUN docker-php-ext-install pdo pdo_mysql sockets

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>/dev/null || \
    composer install --no-interaction --prefer-dist

COPY . .

# Nginx config — listen on port 8001
RUN mkdir -p /run/nginx && \
    printf 'server {\n\
    listen 8001;\n\
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

# Supervisord config — kelola php-fpm, nginx, dan alert consumer sekaligus
RUN mkdir -p /etc/supervisor/conf.d && \
    printf '[supervisord]\n\
nodaemon=true\n\
logfile=/dev/null\n\
logfile_maxbytes=0\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:php-fpm]\n\
command=php-fpm\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:nginx]\n\
command=nginx -g "daemon off;"\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:alert-consumer]\n\
command=php /var/www/html/bin/consume_alerts.php\n\
autostart=true\n\
autorestart=true\n\
startretries=5\n\
startsecs=3\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
' > /etc/supervisor/conf.d/php-crop.conf

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 \
  CMD curl -f http://localhost:8001/health || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/php-crop.conf"]
