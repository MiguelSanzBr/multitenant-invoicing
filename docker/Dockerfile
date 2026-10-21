# dev: o q o compose usa, com bind mount, composer, node e pcov (contexto: docker/)
# prod: imagem fechada com o código, sem dev deps, opcache e usuário sem root (contexto: raiz do repo)
#   docker build -f docker/Dockerfile --target prod -t multitenant-invoicing:prod .
FROM node:22-bookworm-slim AS node

FROM php:8.4-cli-bookworm AS dev
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

# build: vendor sem dev deps e assets do vite, nada disso vai pra imagem final além do resultado
FROM dev AS build
USER root
COPY --chown=app:app . /app
USER app
RUN composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative \
 && cp .env.example .env && php artisan key:generate \
 && npm ci && npm run build \
 && rm -rf node_modules .env

FROM dunglas/frankenphp:1-php8.4-bookworm AS prod
RUN install-php-extensions pdo_pgsql pcntl intl zip bcmath opcache \
 && { echo 'opcache.enable=1'; echo 'opcache.validate_timestamps=0'; echo 'opcache.memory_consumption=128'; echo 'opcache.max_accelerated_files=20000'; } > "$PHP_INI_DIR/conf.d/opcache-prod.ini" \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && useradd -r -u 10001 app && chown -R app:app /config/caddy /data/caddy
WORKDIR /app
COPY --from=build --chown=app:app /app /app
USER app
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=pgsql
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s CMD curl -fsS http://localhost:8080/up || exit 1
# config, rotas e views em cache com o env de verdade, na subida do container
CMD ["sh", "-c", "php artisan optimize && exec frankenphp php-server --root public/ --listen :8080"]
