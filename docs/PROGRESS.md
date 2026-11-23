# Прогресс
Обновлено: 2026-11-23
Текущий этап: 0 — Каркас (завершён, ждёт подтверждения перед этапом 1)
Текущая задача: T-0.2 (done)

## Сделано
- Roadmap переведён в `confirmed`. Q1–Q13 отдельно не подтверждались; этап 0 их не требует.
- T-0.1 ✅ `docker compose up` поднимает service, platform-mock и PostgreSQL. Коммит `a1f27d7`.
- T-0.2 ✅ В `service/` есть Composer-скрипты `cs:fix`, `cs:check`, `stan`, `test`, `check`, Codeception (unit / integration / api), PHPStan 8, PHP-CS-Fixer, слои `src/Domain`, `src/Application`, `src/Infrastructure`, `src/Presentation`. Хук `.githooks/pre-commit` вызывает `composer check` в `service/`. Включено: `core.hooksPath=.githooks`.

## В работе / дальше
- Этап 1 не начат. Перед ним остаются дефолты Q1–Q5 и Q7–Q13.

## Принятые решения и допущения
- Q6 принято: только HTTP-эндпоинты, UI не делаем. Сценарии — задокументированные `curl`. Пути: `POST /subscribers`, `GET /subscribers/{subscriberId}`, `POST /jobs/daily-charge`, `POST /webhooks/subscription`, `POST /webhooks/mo`, `GET /charges`.
- A-DB: PostgreSQL 16 в compose. Локальные пароль БД и `COOKIE_VALIDATION_KEY` лежат в `docker-compose.yml`, чтобы подъём не требовал ручного `.env`. Это не секреты Platform.
- A-STACK: оба процесса — PHP 8.2 и Yii2. Lock сервиса зафиксирован на platform php 8.2.36, иначе Composer подтягивает пакеты для PHP 8.4.
- `PLATFORM_BASE_URL=http://platform-mock:8080` внутри сети compose. С хоста мок на `localhost:8081`.
- PHPStan смотрит `src`, `config`, `public`, `bin` и наши unit/integration-тесты. Сгенерированные акторы Codeception в анализ не входят: их уровень 8 не проходит без baseline, а baseline без согласования запрещён.
- Dev-зависимости стоят в образе сервиса, чтобы `composer check` шёл внутри контейнера и на хосте не нужен PHP.

## Открытые вопросы
- Q6 закрыт. Остаются Q1–Q5 и Q7–Q13 в `docs/spec/open-questions.md`.

## Последний результат проверок
- `docker compose exec service composer check` (после пересборки образа):
  - php-cs-fixer: 0 files to fix
  - phpstan: OK, no errors
  - codeception: OK (3 tests, 7 assertions) — 1 api, 1 integration, 1 unit
- T-0.1 `tests/integration/health.sh`: 200, затем 503 при остановленной БД, затем снова 200.
