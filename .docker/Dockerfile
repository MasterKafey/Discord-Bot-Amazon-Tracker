FROM php:8.2-apache

ENV TZ Europe/Paris

RUN apt update && apt install -y \
    supervisor \
    git \
    cron \
    libzip-dev \
    tzdata \
    unzip \
    libxml2-dev \
    npm

RUN apt clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install zip ctype simplexml pdo_mysql intl

RUN echo "date.timezone = $TZ" > /usr/local/etc/php/php.ini

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

COPY ./ /app/
WORKDIR /app

RUN groupadd -r discord && useradd -r -g discord discord \
    && chown -R discord:discord /app


RUN npm install
RUN npm run build

USER discord
RUN composer install --no-dev --optimize-autoloader

USER root

RUN a2enmod rewrite
COPY .docker/apache2/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod -R 777 /app/

EXPOSE 80

CMD ["/usr/bin/supervisord"]