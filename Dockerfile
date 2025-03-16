FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Copy only the binary file
COPY bin/yt-dlp_linux /var/www/bin/yt-dlp_linux

# Set permissions for yt-dlp_linux
RUN chmod 755 /var/www/bin/yt-dlp_linux \
    && ls -la /var/www/bin/yt-dlp_linux \
    && /var/www/bin/yt-dlp_linux --version || echo "Failed to run version check"

# Keep the container running (optional, remove if not needed)
CMD ["tail", "-f", "/dev/null"]