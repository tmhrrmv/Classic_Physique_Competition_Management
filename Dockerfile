FROM dunglas/frankenphp

WORKDIR /app

COPY . /app

RUN install-php-extensions pdo_mysql mysqli

COPY php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
