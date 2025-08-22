# ---- Composer deps baked in (no dev deps) ----
  FROM composer:2 AS vendor
  WORKDIR /app
  COPY composer.json composer.lock ./
  RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  
  
  # ---- Runtime (PHP 8.4 + Apache) ----
  FROM php:8.4-apache
  WORKDIR /var/www/html
  
  
  # PHP extensions & Apache rewrite
  RUN docker-php-ext-install pdo_mysql opcache \
  && a2enmod rewrite \
  && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
  && sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public/>!g' /etc/apache2/apache2.conf \
  && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
  
  
  # App code + vendor
  COPY . .
  COPY --from=vendor /app/vendor /var/www/html/vendor
  
  
  # Storage permissions (adjust as needed)
  RUN chown -R www-data:www-data storage bootstrap/cache
  
  
  EXPOSE 80