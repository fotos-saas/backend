# ============================================
# PHP-FPM Production Stage (Coolify compatible)
# ============================================
FROM php:8.3-fpm-alpine AS production

# Install system dependencies
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
    libheif \
    libheif-dev \
    libde265 \
    libde265-dev \
    x265-libs \
    x265-dev \
    libavif \
    libavif-dev \
    libwebp \
    libwebp-dev \
    libjxl \
    libjxl-dev \
    libopenraw \
    libopenraw-dev \
    fontconfig \
    ttf-dejavu \
    exiftool \
    libtool \
    autoconf \
    automake \
    g++ \
    make \
    pkgconfig \
    libxml2-dev \
    ghostscript-dev \
    libgomp \
    # libvips for high-performance image processing (4-8x faster, 10x less RAM)
    vips-dev \
    vips-tools \
    vips-heif \
    # FFI support for php-vips
    libffi-dev

# Build ImageMagick from source with HEIF support
RUN cd /tmp \
    && wget https://github.com/ImageMagick/ImageMagick/archive/refs/tags/7.1.2-3.tar.gz \
    && tar xzf 7.1.2-3.tar.gz \
    && cd ImageMagick-7.1.2-3 \
    && ./configure \
        --with-heic=yes \
        --with-webp=yes \
        --with-jpeg=yes \
        --with-png=yes \
        --with-freetype=yes \
        --with-gslib=yes \
        --with-xml=yes \
        --without-x \
        --disable-static \
        --enable-shared \
        --prefix=/usr \
    && make -j$(nproc) \
    && make install \
    && ldconfig /usr/lib \
    && cd /tmp \
    && rm -rf ImageMagick-7.1.2-3 7.1.2-3.tar.gz

# Install PHP extensions (including FFI for php-vips)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
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

# Install Redis and Imagick extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files (Coolify clones repo directly, no backend/ prefix)
COPY . ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Set permissions
RUN chown -R www-data:www-data ./storage ./bootstrap/cache \
    && chmod -R 775 ./storage ./bootstrap/cache

# Copy PHP debug config (REMOVE IN PRODUCTION)
COPY docker/php-debug.ini /usr/local/etc/php/conf.d/99-debug.ini

# Copy PHP FFI config for libvips (php-vips FFI binding)
COPY docker/php/php-ffi.ini /usr/local/etc/php/conf.d/ffi.ini

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
