FROM dunglas/frankenphp

WORKDIR /app

RUN install-php-extensions pdo_mysql

COPY . /app

COPY php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
