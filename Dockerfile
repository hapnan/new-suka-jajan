FROM composer:2 AS composer_bin

FROM php:8.5-apache

# Install system dependencies required by CodeIgniter 4, image manipulation (GD), and code coverage (PCOV)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        unzip \
        git \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mysqli \
        pdo_mysql \
        gd \
        zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

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
        echo "pcov.enabled = 1"; \
        echo "pcov.directory = /var/www/html/app"; \
    } > /usr/local/etc/php/conf.d/sukajajan.ini

# Copy Composer binary from official composer image
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Configure Apache VirtualHost
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Install Composer dependencies (including PHPUnit in require-dev) first to leverage Docker layer caching
COPY composer.json composer.lock ./
RUN composer install \
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
        build/.phpunit.cache \
        build/logs \
        writable/cache \
        writable/logs \
        writable/session \
        writable/uploads \
        writable/debugbar \
        public/uploads/kuliner \
        public/uploads/thumbnails \
    && chown -R www-data:www-data /var/www/html/build /var/www/html/writable /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/build /var/www/html/writable /var/www/html/public/uploads

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
