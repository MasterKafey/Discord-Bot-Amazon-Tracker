FROM php:8.2-cli

ENV TZ Europe/Paris

RUN apt update && apt install -y \
    supervisor \
    git \
    cron \
    libzip-dev \
    tzdata \
    unzip \
    libxml2-dev

RUN apt clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install zip
RUN docker-php-ext-install ctype
RUN docker-php-ext-install simplexml
RUN docker-php-ext-install pdo_mysql


RUN echo "date.timezone = $TZ" > /usr/local/etc/php/php.ini

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

COPY ./ /app/
WORKDIR /app

RUN groupadd -r discord && useradd -r -g discord discord \
    && mkdir -p /app \
    && chown -R discord:discord /app

USER discord
RUN composer install --no-dev --optimize-autoloader
USER root

COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord"]