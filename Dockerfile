FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql mysqli
RUN a2enmod rewrite

# Match XAMPP php.ini behavior
RUN echo "output_buffering = 4096" > "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html
COPY . .

# Env-based SMS config (the real sms_config.php is gitignored/dockerignored)
COPY docker/sms_config.production.php /var/www/html/sms_config.php

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint.sh \
    && chmod +x /usr/local/bin/app-entrypoint.sh \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

CMD ["app-entrypoint.sh"]
