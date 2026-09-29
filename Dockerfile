FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

ENTRYPOINT ["sh", "./docker/start.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
