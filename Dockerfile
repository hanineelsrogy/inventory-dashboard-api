FROM php:8.4-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and required PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create a non-root system user for security
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -ms /bin/bash -g appuser appuser

# Copy composer dependency specifications first to leverage Docker layer caching
COPY composer.json composer.lock ./

# Install Composer dependencies (including dev tools needed for testing)
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy the application source code
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Ensure storage and bootstrap cache permissions are set for appuser
RUN chown -R appuser:appuser /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to non-root user
USER appuser

# Expose Laravel development server port
EXPOSE 8000

# Default command: start Laravel built-in server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
