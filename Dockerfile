FROM dunglas/frankenphp:latest AS base

# Install PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    intl \
    zip \
    opcache \
    pcov \
    sockets

RUN echo "pcov.enabled=0" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

WORKDIR /app

# --- Development stage ---
FROM base AS dev
# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# In dev, we usually mount the code via volumes, so we don't COPY here
# but we can copy composer files to pre-install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts
# OPCache config for dev (JIT off, validate timestamps on)
RUN echo "opcache.enable=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=2" > /usr/local/etc/php/conf.d/opcache.ini

# --- Production stage ---
FROM base AS prod
ENV APP_ENV=prod
ENV FRANKEN_WORKER=true
# Copy code
COPY . /app
# Install composer and dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

# OPCache + JIT configuration
COPY infra/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Preload script
COPY preload.php /app/preload.php

# Adjust permissions
RUN chown -R www-data:www-data /app/public
