FROM php:8.1-fpm

WORKDIR /var/www/html

RUN apt update && \
	apt install -y libxml2-dev libcurl4-openssl-dev libzip-dev && \
    docker-php-ext-install xml curl mysqli pdo_mysql zip &&\
    curl -sS -o composer-setup.php https://getcomposer.org/installer && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php

COPY . /var/www/html

RUN	composer install -v --no-dev --no-interaction && \
    chown -R www-data:www-data /var/www/html/storage

COPY . /var/www/html

ARG URL_LMS
ENV URL_LMS=$URL_LMS
ARG CLIENT_ID
ENV CLIENT_ID=$CLIENT_ID
ARG DEPLOYMENT_ID
ENV DEPLOYMENT_ID=$DEPLOYMENT_ID

ENTRYPOINT ["./wait-for-it.sh", "db:3306", "--strict", "--timeout=300", "--", "bash", "-c", "php artisan migrate --force && php artisan lti:add_platform_1.3 moodle --platform_id=${URL_LMS} --client_id=${CLIENT_ID} --deployment_id=${DEPLOYMENT_ID} && php artisan serve --host=0.0.0.0 --port=9000"]
