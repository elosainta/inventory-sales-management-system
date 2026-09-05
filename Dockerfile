# Stage 1: build frontend assets
FROM node:24-alpine AS frontend
WORKDIR /app
RUN npm install -g pnpm
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN pnpm run build

# Stage 2: PHP production image
FROM php:8.5-fpm-alpine AS app

# Install system deps + PHP extensions
RUN apk add --no-cache \
    nginx supervisor curl zip unzip git \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    icu-dev libxml2-dev oniguruma-dev \
    curl-dev libzip-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
        pdo_mysql mbstring xml zip gd bcmath intl
# opcache is deliberately not in that list: PHP 8.5 compiles it statically
# into the binary, so building it as a shared module produces no .so and the
# install step fails. It is still on - docker/php.ini sets opcache.enable=1.

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, from the manifest alone, so Docker reuses this layer on
# every build where composer.json/lock have not changed — which is most of
# them. Copying the source first meant one edited Blade file re-ran the whole
# install. --no-scripts because post-autoload-dump runs artisan
# package:discover, and the app source it needs is not here yet.
# (--prefer-source was added in 904aea1 for a broken brick/math dist zip; that
# URL serves fine again. Put it back if a dist download ever fails.)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader

# Copy app source
COPY --chown=www-data:www-data . .

# Copy built frontend assets from stage 1
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Source is present now, so build the optimised autoloader. This is what fires
# post-autoload-dump / package:discover, which needs artisan and config/.
RUN composer dump-autoload --optimize --no-dev

# Copy config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
