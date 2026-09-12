FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
  $PHPIZE_DEPS \
    git \
    curl \
    unzip \
    tzdata \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/

ARG DOCKER_HOST_USER=app
ARG DOCKER_HOST_UID=1000
ARG DOCKER_HOST_GROUP=app
ARG DOCKER_HOST_GID=1000

RUN addgroup -g ${DOCKER_HOST_GID} ${DOCKER_HOST_GROUP} 2>/dev/null || true \
    && adduser -D \
        -u ${DOCKER_HOST_UID} \
        -G ${DOCKER_HOST_GROUP} \
        ${DOCKER_HOST_USER}

USER $DOCKER_HOST_USER

EXPOSE 9000
