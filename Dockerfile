FROM php:8.1-fpm
ARG CLIENT_ID
ENV CLIENT_ID=$CLIENT_ID
ARG DEPLOYMENT_ID
ENV DEPLOYMENT_ID=$DEPLOYMENT_ID
WORKDIR /var/www/html
COPY . /var/www/html
RUN apt update && \
    apt install -y libxml2-dev libcurl4-openssl-dev libzip-dev && \
    docker-php-ext-install xml curl mysqli pdo_mysql zip &&\
    curl -sS -o composer-setup.php https://getcomposer.org/installer && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php
RUN composer install -v --no-dev --no-interaction && \
    chown -R www-data:www-data /var/www/html/storage
ENTRYPOINT ["./wait-for-it.sh", "db:3306", "--strict", "--timeout=300", "--", "bash", "-c", "php artisan key:generate && php artisan migrate --force && sh ./LTIPlatformCreation.sh && php artisan serve --host=0.0.0.0 --port=9000"]