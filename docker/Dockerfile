FROM node:22-bookworm-slim AS node

FROM php:8.4-cli-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql pcntl intl zip bcmath \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# wayfinder roda php artisan durante o build do vite, por isso node mora na mesma imagem
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
ARG UID=1000
ARG GID=1000
# -o aceita GID/UID q já existem na imagem base (ex. 20 no macOS)
RUN groupadd -o -g ${GID} app && useradd -o -m -u ${UID} -g ${GID} app
USER app
WORKDIR /app
