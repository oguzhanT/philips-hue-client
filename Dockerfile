FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    avahi-tools \
    avahi-dev

# Clear cache
RUN rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files (the lock keeps builds reproducible)
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod +x bin/hue \
    && chmod +x bin/hue-server

# Create cache directory
RUN mkdir -p /tmp/hue_cache && chown www-data:www-data /tmp/hue_cache

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/api/health || exit 1

# Run as an unprivileged user
USER www-data

# Run the application. The API refuses requests until HUE_API_KEY is set
# (or HUE_API_INSECURE=1 on a trusted network).
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/api.php"]