# Учёт товаров на складе

Система учёта товаров на **Symfony** с интеграцией **Redis**, **MySQL** и **RabbitMQ**.

## Технологии

- **Symfony 6.4+** — PHP фреймворк  
- **Doctrine ORM + Migrations** — работа с базой данных  
- **MySQL** — основная база данных  
- **Redis** — хранение сессий и кэширование списка товаров  
- **RabbitMQ** — асинхронная обработка событий (регистрация, CRUD операции)  
- **Docker & Docker Compose** — контейнеризация и оркестрация  
- **PHPUnit** — интеграционные и юнит-тесты с покрытием ≥ 60%  
- **OpenAPI/Swagger** — автоматическая документация API  

---

## Быстрый старт

```bash
# 1. Настраиваем окружение
git clone https://github.com/GrigoPon/web-prog.git
cd web-prog
cp .env.example .env

# 2. Запускаем сервисы
docker-compose up -d --build

# 3. Устанавливаем зависимости
docker-compose exec php composer install

# 4. Применяем миграции
docker-compose exec php php bin/console doctrine:migrations:migrate -n

# 5. Проверяем API
open http://localhost:8080
```

## Миграции
```bash
#Создать новую миграцию:
docker-compose exec php php bin/console make:migration

#Применить миграции:
docker-compose exec php php bin/console doctrine:migrations:migrate -n

#Откатить последнюю миграцию:
docker-compose exec php php bin/console doctrine:migrations:migrate prev -n
```

## Тестирование
```bash
#Создать тестовую БД:
docker-compose exec php php bin/console doctrine:database:create --env=test

#Применить миграцию для тестовой БД
docker-compose exec php php bin/console doctrine:migrations:migrate --env=test -n

#Запуск тестов
docker-compose exec php php bin/phpunit --coverage-html=public/coverage

# Просмотр отчёта
open http://localhost:8080/coverage
```

## Сервисы
- **Веб-интерфейс:** http://localhost:8080
- **API:** http://localhost:8080/api/...
- **Swagger UI:** http://localhost:8080/api/doc
- **RabbitMQ Management:** http://localhost:15672 (guest / guest)
- **MySQL:** localhost:3306 (user: app, pass: app)
- **Redis:** localhost:6379

## CI/CD
Проект включает GitHub Actions workflow, который:

- Запускает тесты при push в main,
- Проверяет, что покрытие кода ≥ 60%,
- Гарантирует стабильность основной ветки.
