# --------------------------------------------
# 1) Frontend build (Vite / Inertia / Vue)
# --------------------------------------------
FROM node:20.11.1 AS npm-builder

ENV NPM_CONFIG_LOGLEVEL=info
WORKDIR /var/www/html

# Install deps with cache friendliness
COPY package.json package-lock.json* ./
RUN npm install

# Build assets
COPY . .
RUN npm run build


# --------------------------------------------
# 2) Runtime (PHP 8.3 + Nginx)
#   - trafex/php-nginx は nobody ユーザー前提で動かすのが安定
#   - Renderでも 8080 待受に寄せる
# --------------------------------------------
FROM trafex/php-nginx:3.6.0

USER root
WORKDIR /var/www/html

# Nginx設定: Laravelのpublicディレクトリをドキュメントルートに
RUN sed -i 's|root /var/www/html;|root /var/www/html/public;|g' /etc/nginx/conf.d/default.conf

# PHP 8.3 extensions (DB不要なら sqlite だけでOK)
RUN apk add --no-cache \
    php83-bcmath \
    php83-curl \
    php83-mbstring \
    php83-openssl \
    php83-simplexml \
    php83-gd \
    php83-zip \
    php83-iconv \
    php83-exif \
    php83-pdo \
    php83-pdo_sqlite \
    php83-tokenizer \
    php83-xml \
    php83-xmlwriter

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# App source
COPY . .

# Built assets (Laravel Vite default: public/build)
COPY --from=npm-builder /var/www/html/public/build /var/www/html/public/build

# PHP deps
RUN composer install \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-dev

# Ensure dirs exist (safety)
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache

# Permissions (Laravel) - run as nobody (trafex default)
RUN chown -R nobody:nobody /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER nobody

EXPOSE 8080
