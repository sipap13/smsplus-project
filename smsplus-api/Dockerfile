FROM php:8.2-cli

RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev git unzip curl libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install pdo pdo_pgsql pgsql gd zip \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && { \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.validate_timestamps=1"; \
    echo "opcache.revalidate_freq=0"; \
    echo "realpath_cache_size=4096K"; \
    echo "realpath_cache_ttl=600"; \
  } > /usr/local/etc/php/conf.d/app-performance.ini \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress \
  && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

EXPOSE 8000

