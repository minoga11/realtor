# Realtor Property & Import API

Сервіс бекенду на базі Laravel для керування пропозиціями нерухомості, імпортом даних від постачальників та бронюваннями гостей.

---

## Зміст

1. [Вимоги та налаштування](#вимоги-та-налаштування)
2. [Сіди бази даних (Seeders)](#сіди-бази-даних-seeders)
3. [Структура імпорту (`forimport.json`)](#структура-імпорту-forimportjson)
4. [API Ендпоінти та структура запитів](#api-ендпоінти-та-структура-запитів)
5. [Тестування](#тестування)

---

## Вимоги та налаштування

### Передумови

- **PHP** >= 8.2
- **Composer**
- **MySQL** / PostgreSQL / SQLite

### Кроки встановлення

1. **Клонування репозиторію та встановлення залежностей:**

   ```bash
   composer install
   ```

2. **Налаштування файлу середовища:**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Налаштування бази даних:**
   Оновіть дані підключення до бази даних у файлі `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=realtor
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Запуск міграцій:**

   ```bash
   php artisan migrate
   ```

5. **Заповнення бази початковими даними (сіди):**

   ```bash
   php artisan db:seed
   ```

6. **Запуск сервера розробки:**

   ```bash
   php artisan serve
   ```

---

## Сіди бази даних (Seeders)

Проєкт містить `DatabaseSeeder`, який автоматично створює стандартних постачальників, необхідних для обробки імпорту пропозицій.

- **`DatabaseSeeder`**: Створює 10 стандартних постачальників (`SUP1name` — `SUP10name`) за допомогою методу `Supplier::firstOrCreate()`.

Для ручного запуску сіадерів:

```bash
php artisan db:seed
```

---

## Структура імпорту (`forimport.json`)

Файл `forimport.json` у кореневій директорії слугує прикладом корисного навантаження (payload) для імпорту пропозицій нерухомості від постачальників.

### Формат JSON структури

```json
[
    {
        "supplier": "SUP1name",
        "external_import_id": "import-2026-09-01-001",
        "sent_at": "2026-09-01T10:00:00Z",
        "offers": [
            {
                "external_id": "offer-1-10001",
                "property": {
                    "code": "BCN-0001",
                    "name": "Apartment near Sagrada Familia",
                    "city": "Barcelona"
                },
                "check_in": "2026-10-10",
                "check_out": "2026-10-15",
                "max_guests": 4,
                "price": 72500,
                "currency": "EUR",
                "available_units": 2,
                "expires_at": "2026-09-10T23:59:59Z"
            }
        ]
    }
]
```

### Опис полів

- `supplier`: Назва постачальника (має відповідати існуючим постачальникам у базі даних).
- `external_import_id`: Унікальний ідентифікатор пакету імпорту від постачальника.
- `sent_at`: Мітка часу ISO 8601, коли було відправлено імпорт.
- `offers`: Масив пропозицій від постачальника.
  - `external_id`: Унікальний ідентифікатор пропозиції.
  - `property`: Об'єкт з інформацією про нерухомість (`code`, `name`, `city`).
  - `check_in` / `check_out`: Дати доступності (`YYYY-MM-DD`).
  - `max_guests`: Максимальна кількість гостей.
  - `price`: Ціна в мінімальних одиницях валюти (наприклад, центах).
  - `currency`: Код валюти (наприклад, `EUR`, `USD`).
  - `available_units`: Кількість доступних одиниць/номерів.
  - `expires_at`: Термін дії пропозиції у форматі ISO 8601.

---

## API Ендпоінти та структура запитів

### 1. Імпорти (Imports)

#### Отримати список усіх імпортів

- **URL:** `GET /api/imports`
- **Опис:** Повертає список усіх оброблених пакетів імпорту.

#### Створити імпорт

- **URL:** `POST /api/imports`
- **Content-Type:** `application/json`
- **Структура запиту:**

  ```json
  {
      "supplier": "SUP1name",
      "external_import_id": "import-2026-09-01-001",
      "sent_at": "2026-09-01T10:00:00Z",
      "offers": [
          {
              "external_id": "offer-1-10001",
              "property": {
                  "code": "BCN-0001",
                  "name": "Apartment near Sagrada Familia",
                  "city": "Barcelona"
              },
              "check_in": "2026-10-10",
              "check_out": "2026-10-15",
              "max_guests": 4,
              "price": 72500,
              "currency": "EUR",
              "available_units": 2,
              "expires_at": "2026-09-10T23:59:59Z"
          }
      ]
  }
  ```

#### Отримати інформацію про конкретний імпорт

- **URL:** `GET /api/imports/{import}`
- **Опис:** Отримання детальної інформації про пакет імпорту за його ID.

---

### 2. Нерухомість та пропозиції (Properties & Offers)

#### Список нерухомості / пропозицій

- **URL:** `GET /api/properties`
- **Опис:** Пошук та фільтрація доступних об'єктів нерухомості та пропозицій.

---

### 3. Бронювання (Reservations)

#### Створити бронювання

- **URL:** `POST /api/reservations` або `POST /api/offers/{offer}/reservations`
- **Content-Type:** `application/json`
- **Структура запиту:**

  ```json
  {
      "offer_id": 1,
      "guest_name": "John Doe",
      "guest_email": "john@example.com",
      "guests_count": 2
  }
  ```

---

## Тестування

Для запуску автоматизованих тестів виконайте:

```bash
php artisan test
```
