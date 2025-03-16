FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./

# Install composer dependencies
RUN composer install --no-scripts --no-autoloader

# Copy application files
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Set permissions for yt-dlp_linux
RUN chmod 755 /var/www/bin/yt-dlp_linux

# Set ownership
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Make sure the binary is executable
RUN chmod +x /var/www/bin/yt-dlp_linux

# Verify the binary is executable
RUN ls -la /var/www/bin/yt-dlp_linux

# Test the binary
RUN /var/www/bin/yt-dlp_linux --version || echo "Failed to run version check"

# Expose port 8000
EXPOSE 8000

# Start PHP server
CMD php artisan serve --host=0.0.0.0 --port=8000