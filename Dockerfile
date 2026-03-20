FROM php:8.4-fpm-alpine

# Instala dependências de build, compila extensões e remove headers para manter imagem enxuta
RUN apk add --no-cache \
    bash \
    curl \
    zip \
    unzip \
    git \
    libpng \
    libxml2 \
    oniguruma \
    icu-libs \
    && apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    soap \
    opcache \
    && apk del .build-deps

# Instala o Composer v2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho
WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]