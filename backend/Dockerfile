FROM php:8.3-fpm-alpine

RUN set -eux; \
    apk add --no-cache \
        bash \
        curl \
        git \
        icu-libs \
        libpq \
        libzip \
        netcat-openbsd \
        oniguruma \
        unzip \
        zip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        postgresql-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        pgsql \
        zip; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
