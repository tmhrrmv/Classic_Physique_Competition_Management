FROM dunglas/frankenphp

WORKDIR /app

RUN install-php-extensions pdo_mysql

COPY . /app

# Colocar php.ini donde PHP lo puede leer
COPY php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
