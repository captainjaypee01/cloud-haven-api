FROM php:8.4-apache
WORKDIR /var/www/html

# PHP extensions
RUN docker-php-ext-install pdo_mysql opcache

# Apache: rewrite and DocumentRoot → /public
RUN a2enmod rewrite \
  && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
  && sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public/>!g' /etc/apache2/apache2.conf \
  && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Copy code and set permissions (vendor will be copied in CI on the server using composer, or pre-baked)
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80