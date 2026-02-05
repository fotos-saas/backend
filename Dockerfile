# ============================================
# PHP-FPM Production Stage (Coolify compatible)
# ============================================
FROM php:8.3-fpm-alpine AS production

# Install system dependencies (minimal)
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    git \
    curl \
    unzip \
    libwebp-dev \
    # libvips for image processing
    vips-dev \
    vips-tools \
    # FFI extension dependencies (required by jcupitt/vips)
    libffi-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        opcache \
        mbstring \
        exif \
        pcntl \
        bcmath \
        ffi

# Install Redis + Imagick extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apk del .build-deps \
    && apk add --no-cache imagemagick-libs

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Set permissions
RUN chown -R www-data:www-data ./storage ./bootstrap/cache \
    && chmod -R 775 ./storage ./bootstrap/cache

# Copy configs
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-ffi.ini /usr/local/etc/php/conf.d/99-ffi.ini

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
