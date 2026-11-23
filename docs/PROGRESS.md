# Прогресс
Обновлено: 2026-11-23
Текущий этап: 1 — Фундамент
Текущая задача: T-1.2 (done)

## Сделано
- Roadmap переведён в `confirmed`. Q6 принят отдельно. Q1–Q5 и Q7–Q13 приняты 2026-11-23 вариантами по умолчанию.
- T-0.1 ✅ `docker compose up` поднимает service, platform-mock и PostgreSQL. Коммит `a1f27d7`.
- T-0.2 ✅ В `service/` есть Composer-скрипты `cs:fix`, `cs:check`, `stan`, `test`, `check`, Codeception (unit / integration / api), PHPStan 8, PHP-CS-Fixer, слои `src/Domain`, `src/Application`, `src/Infrastructure`, `src/Presentation`. Хук `.githooks/pre-commit` вызывает `composer check` внутри контейнера `service`. Включено: `core.hooksPath=.githooks`.
- T-1.1 ✅ Таблицы `subscriber` и `charge_attempt`. `external_trx_id` уникален, пустой `reference_id` допустим. Запись обоих в одной транзакции: сбой попытки откатывает нового подписчика. Статусы подписчика — дефолт Q4. Миграция выполняется при старте контейнера (`bin/serve.sh`).
- T-1.2 ✅ `SubscriberId` принимает только `^tel:[0-9]+$` и хранит строку целиком, без разбора телефонного плана. `Amount` принимает ровно два знака после точки и хранит минорные единицы целым числом.
- T-1.4 ✅ HTTP-клиент Platform: `POST /subscription/send`, `/sms/send`, `/caas/direct/debit`. `applicationId` и `password` только из env (`PLATFORM_APPLICATION_ID`, `PLATFORM_PASSWORD`). Таймауты connect/read: env `PLATFORM_CONNECT_TIMEOUT` и `PLATFORM_READ_TIMEOUT`, иначе 2 с и 5 с. Клиент не ретраит и не меняет `externalTrxId`. Пароль и application id не пишутся в лог.

## В работе / дальше
- В этапе 1 остались T-1.3 и T-1.5. Следующая по файлу этапа — T-1.3.

## Принятые решения и допущения
- Q1–Q13 приняты. Q6: только HTTP-эндпоинты, UI не делаем, сценарии — задокументированные `curl`. Пути: `POST /subscribers`, `GET /subscribers/{subscriberId}`, `POST /jobs/daily-charge`, `POST /webhooks/subscription`, `POST /webhooks/mo`, `GET /charges`. Остальные — варианты по умолчанию из `docs/spec/open-questions.md`: billing day = календарная дата UTC; плата `5.00` `XTS` из env; не больше 3 попыток с паузой 1/2/4 с и тем же `externalTrxId`; статусы `ACTIVE` / `SUSPENDED` / `INACTIVE`; webhook `REGISTERED`→`ACTIVE`, `UNREGISTERED`→`INACTIVE`, `PENDING`/`CHARGE` только запись; SMS-тексты и `sourceAddress` из Q7; MSISDN `^tel:[0-9]+$`; повторный ACTIVE opt-in без Platform и без второго welcome; ключ webhook = `subscriberId`+`status`+`timeStamp`+`frequency`; `E1337` без нового ключа; неизвестный и повторный STOP по Q12; мок — `MOCK_CHARGE_STATUS`, повтор ключа всегда `E1337`.
- A-DB: PostgreSQL 16 в compose. Локальные пароль БД и `COOKIE_VALIDATION_KEY` лежат в `docker-compose.yml`, чтобы подъём не требовал ручного `.env`. Это не секреты Platform.
- A-STACK: оба процесса — PHP 8.2 и Yii2. Lock сервиса зафиксирован на platform php 8.2.36, иначе Composer подтягивает пакеты для PHP 8.4.
- `PLATFORM_BASE_URL=http://platform-mock:8080` внутри сети compose. С хоста мок на `localhost:8081`.
- PHPStan смотрит `src`, `config`, `migrations`, `public`, `bin` и наши unit/integration-тесты. Сгенерированные акторы Codeception в анализ не входят: их уровень 8 не проходит без baseline, а baseline без согласования запрещён.
- Dev-зависимости стоят в образе сервиса, чтобы `composer check` шёл внутри контейнера и на хосте не нужен PHP.

## Открытые вопросы
- Q1–Q13 закрыты. Тексты решений — в `docs/spec/open-questions.md`.

## Последний результат проверок
- `docker compose exec service composer check` (после пересборки образа, T-1.2):
  - php-cs-fixer: 0 files to fix
  - phpstan: OK, no errors
  - codeception: OK (23 tests, 89 assertions) — 1 api, 5 integration, 17 unit
- T-0.1 `tests/integration/health.sh`: 200, затем 503 при остановленной БД, затем снова 200.
