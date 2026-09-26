# Imagem PHP-FPM usada pelos serviços `app` e `scheduler`.
#
# Contém apenas as extensões que o projeto realmente usa (PostgreSQL, bcmath para
# cálculos monetários, intl para formatação pt-BR, zip para o Composer) e o próprio
# Composer, para que nenhum PHP/Composer precise existir na máquina host.
#
# O usuário `app` recebe o UID/GID do host (build args) para que arquivos criados
# dentro do container (vendor, logs, migrations geradas) não fiquem com dono root
# em hosts Linux. No Docker Desktop para Windows isso é indiferente.

FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        icu-libs \
        libpq \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

USER app

CMD ["php-fpm"]
