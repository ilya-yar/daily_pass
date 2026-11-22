# Прогресс
Обновлено: 2026-11-23
Текущий этап: 0 — Каркас
Текущая задача: T-0.1 (done), дальше T-0.2

## Сделано
- Roadmap переведён в `confirmed`. Q1–Q13 отдельно не подтверждались; этап 0 их не требует.
- T-0.1 ✅ `docker compose up` поднимает service, platform-mock и PostgreSQL. `/health` сервиса 200 при живой БД и 503, когда БД остановлена. Мок `/health` 200. PHP 8.2.36 внутри образа.

## В работе / дальше
- T-0.2 — Composer-скрипты, Codeception, PHPStan, PHP-CS-Fixer, слои, pre-commit hook.

## Принятые решения и допущения
- A-DB: PostgreSQL 16 в compose. Локальные пароль БД и `COOKIE_VALIDATION_KEY` лежат в `docker-compose.yml`, чтобы `docker compose up` не требовал ручного `.env`. Это не `applicationId` / `password` Platform.
- A-STACK: оба процесса — PHP 8.2 и Yii2.
- `PLATFORM_BASE_URL=http://platform-mock:8080` (порт внутри сети compose). С хоста мок доступен на `localhost:8081`.

## Открытые вопросы
- Q1–Q13 в `docs/spec/open-questions.md`. До этапа 1 нужен явный ответ, брать ли варианты по умолчанию.

## Последний результат проверок
- `tests/integration/health.sh`: успех (200 ok, 503 error после остановки БД, снова 200 после старта).
- Повторный `docker compose up -d` поднял три контейнера без ручных шагов.
- С хоста: `GET http://127.0.0.1:8080/health` и `:8081/health` → 200 `{"status":"ok"}`.
- `php -v` в контейнере service: 8.2.36.
- `composer check`: ещё нет, это T-0.2.
