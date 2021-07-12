FROM php:7.4-cli

WORKDIR /app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update -yq && apt-get install -yq git unzip
RUN install-php-extensions @composer pdo_mysql zip

CMD ["sh", "-c", "composer install && php -S 0.0.0.0:8000 -t ./public/"]
