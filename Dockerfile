# Dockerfile
FROM php:8.3-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    g++ \
    && docker-php-ext-install pdo pdo_mysql zip intl

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Запуск встроенного PHP-сервера (простой способ для dev)
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
