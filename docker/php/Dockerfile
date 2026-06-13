FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    curl-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    su-exec

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install \
    curl \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug (development only) — gate with --build-arg INSTALL_XDEBUG=1
# Default off to avoid 20-40% per-request overhead in production.
# Local dev: docker compose build --build-arg INSTALL_XDEBUG=1 php-fpm
ARG INSTALL_XDEBUG=0
RUN if [ "$INSTALL_XDEBUG" = "1" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create log directories
RUN mkdir -p /var/log/php-fpm && chmod 777 /var/log/php-fpm

# Create system user
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

# Change ownership
RUN chown -R www:www /var/www

# Entrypoint runs as root to fix .env/storage perms, then drops to www via su-exec.
# Source of truth for the "panel writes .env as root:600" fix — also handled in
# post-deploy.sh, but the container guard catches every start path.
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
