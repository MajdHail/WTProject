# TaskFlow task manager, served by PHP's built-in web server.
FROM php:8.4-cli

# The official php image already bundles pdo_sqlite, so no extension install is needed.
WORKDIR /var/www/html
COPY . .

# Fork workers so the calendar's parallel month fetches don't queue.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html"]
