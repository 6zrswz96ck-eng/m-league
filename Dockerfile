FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev libonig-dev libsqlite3-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install curl dom mbstring pdo_sqlite zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN printf '%s\n' '<Directory /var/www/html/public>' 'AllowOverride All' 'Require all granted' '</Directory>' \
    > /etc/apache2/conf-available/laravel.conf && a2enconf laravel

COPY docker/start.sh /usr/local/bin/league-start
RUN chmod +x /usr/local/bin/league-start
CMD ["/usr/local/bin/league-start"]
