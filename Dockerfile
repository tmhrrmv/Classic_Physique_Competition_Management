FROM dunglas/frankenphp

RUN install-php-extensions pdo_mysql

# Usar el Caddyfile del proyecto
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
