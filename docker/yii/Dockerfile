FROM php:8.5-fpm

ARG PHP_FPM_PORT=9000
ARG TIMEZONE=Europe/Amsterdam
ARG USER_ID=1000
ARG USER_NAME=appuser

RUN echo "date.timezone=${TIMEZONE}" > /usr/local/etc/php/conf.d/00-timezone.ini
RUN echo "expose_php=Off" > /usr/local/etc/php/conf.d/00-security.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg-dev libicu-dev \
    iputils-ping netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
      pdo_mysql mbstring zip exif pcntl bcmath gd intl

# Xdebug: keep config static; runtime toggles via env vars (XDEBUG_MODE / XDEBUG_CONFIG).
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && { \
      echo "xdebug.mode=off"; \
      echo "xdebug.start_with_request=trigger"; \
      echo "xdebug.discover_client_host=1"; \
      echo "xdebug.client_port=9878"; \
      echo "xdebug.idekey=PHPSTORM"; \
      echo "xdebug.connect_timeout_ms=200"; \
    } > /usr/local/etc/php/conf.d/99-xdebug.ini

RUN echo "max_execution_time=600" > /usr/local/etc/php/conf.d/99-custom.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN adduser --uid ${USER_ID} --disabled-password --gecos "" ${USER_NAME}

WORKDIR /var/www/html/yii

RUN sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s|9000|${PHP_FPM_PORT}|" /usr/local/etc/php-fpm.d/zz-docker.conf

USER ${USER_NAME}

CMD ["php-fpm"]
