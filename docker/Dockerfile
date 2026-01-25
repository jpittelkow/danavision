# docker/Dockerfile
FROM php:8.3-fpm

# Install dependencies including Node.js, Python, and Chromium for Crawl4AI
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    sqlite3 \
    libsqlite3-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    # Python for Crawl4AI service
    python3 \
    python3-pip \
    python3-venv \
    # Chromium dependencies for Playwright
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    libcairo2 \
    ca-certificates \
    fonts-freefont-ttf \
    && docker-php-ext-install pdo pdo_sqlite zip bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Skip Playwright browser download - we'll install it separately
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Crawl4AI Python service
COPY docker/crawl4ai/requirements.txt /opt/crawl4ai/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /opt/crawl4ai/requirements.txt \
    && playwright install chromium \
    && playwright install-deps chromium
COPY docker/crawl4ai/ /opt/crawl4ai/

# Configure Nginx (Debian uses conf.d instead of http.d)
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisord.conf

# Configure PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure PHP-FPM to run as root (for bind mount compatibility)
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# Copy application (for production builds)
COPY backend/ .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build frontend
RUN npm ci && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage 2>/dev/null || true \
    && chmod -R 755 /var/www/html/bootstrap/cache 2>/dev/null || true

# Create database directories for SQLite (supports both dev and prod paths)
# - /data is used in development (docker-compose.yml)
# - /database is used in production (docker-compose.prod.yml)
RUN mkdir -p /var/www/html/data /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/database

# Create storage directories
RUN mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

# Create storage link (symlink from public/storage to storage/app/public)
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage || true

# Copy logo images to public folder
RUN mkdir -p /var/www/html/public/images

# Copy entrypoint script and fix Windows line endings
COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
