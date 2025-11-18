FROM php:8.3-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    librabbitmq-dev \
    zip \
    unzip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка PHP
RUN docker-php-ext-install pdo pdo_mysql mbstring

RUN pecl install amqp && docker-php-ext-enable amqp
# Установка Xdebug (для coverage)
RUN pecl install xdebug && docker-php-ext-enable xdebug

WORKDIR /var/www/html

# Запуск встроенного PHP-сервера (простой способ для dev)
EXPOSE 9000
CMD ["php-fpm"]
