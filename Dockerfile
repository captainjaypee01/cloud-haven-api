# ---- Composer deps baked in (no dev deps, no scripts) ----
  FROM php:8.4-cli AS vendor
  WORKDIR /app
  
  # Install system dependencies and composer
  RUN apt-get update && apt-get install -y --no-install-recommends \
      unzip \
      git \
      && rm -rf /var/lib/apt/lists/*
  COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
  
  COPY composer.json composer.lock ./
  # pass a build arg to decide dev vs no-dev
  ARG COMPOSER_DEV=false
  RUN if [ "$COMPOSER_DEV" = "true" ]; then \
        composer install --prefer-dist --no-interaction --no-progress --no-scripts; \
      else \
        composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts; \
      fi
  
  
  # ---- Runtime (PHP 8.4 + Apache) ----
  FROM php:8.4-apache
  WORKDIR /var/www/html
  
  
  # PHP extensions & Apache rewrite
  RUN docker-php-ext-install pdo_mysql opcache \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && a2enmod rewrite \
  && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
  && sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public/>!g' /etc/apache2/apache2.conf \
  && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
  
  
  # App code + vendor
  COPY . .
  COPY --from=vendor /app/vendor /var/www/html/vendor
  
  
  # Storage permissions
  RUN chown -R www-data:www-data storage bootstrap/cache
  RUN chmod -R 775 storage bootstrap/cache
  
  
  EXPOSE 80