FROM composer:2 AS composer_bin

FROM php:8.2-apache

# Install system dependencies required by CodeIgniter 4 and image manipulation (GD)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        unzip \
        git \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        mysqli \
        pdo_mysql \
        gd \
        curl \
        zip \
        opcache \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP runtime settings
RUN { \
        echo "memory_limit = 256M"; \
        echo "upload_max_filesize = 10M"; \
        echo "post_max_size = 12M"; \
        echo "max_execution_time = 60"; \
        echo "date.timezone = Asia/Jakarta"; \
        echo "opcache.enable = 1"; \
        echo "opcache.memory_consumption = 128"; \
        echo "opcache.interned_strings_buffer = 8"; \
        echo "opcache.max_accelerated_files = 10000"; \
        echo "opcache.revalidate_freq = 2"; \
    } > /usr/local/etc/php/conf.d/sukajajan.ini

# Copy Composer binary from official composer image
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Configure Apache VirtualHost
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Install Composer dependencies first to leverage Docker layer caching
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# Copy application source
COPY . .

# Prepare entrypoint and permissions (normalize CRLF in case built on Windows)
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p \
        writable/cache \
        writable/logs \
        writable/session \
        writable/uploads \
        writable/debugbar \
        public/uploads/kuliner \
        public/uploads/thumbnails \
    && chown -R www-data:www-data /var/www/html/writable /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/writable /var/www/html/public/uploads

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
