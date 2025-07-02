# ROTZ Email Butler - Web Application Dockerfile
FROM php:8.1-apache as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libc-client-dev \
    libkrb5-dev \
    zip \
    unzip \
    supervisor \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    imap \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/logs

# Development stage
FROM base as development

# Install development dependencies
RUN composer install --optimize-autoloader --no-interaction

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy development PHP configuration
COPY docker/web/php-dev.ini /usr/local/etc/php/php.ini

# Production stage
FROM base as production

# Copy production PHP configuration
COPY docker/web/php-prod.ini /usr/local/etc/php/php.ini

# Copy supervisor configuration
COPY docker/web/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy cron jobs
COPY docker/web/crontab /etc/cron.d/rotz-cron
RUN chmod 0644 /etc/cron.d/rotz-cron \
    && crontab /etc/cron.d/rotz-cron

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port
EXPOSE 80 443

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

