#!/bin/sh

# Menjalankan supervisord untuk mengelola Nginx, PHP-FPM, dan Worker/Consumer
exec supervisord -c /etc/supervisor/conf.d/php-crop.conf