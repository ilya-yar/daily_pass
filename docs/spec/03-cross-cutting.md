# Сквозные требования
- Источник в ТЗ: строки 30–33, 71–88, 103–112, 136–143
- Зависит от: `01-glossary.md`
- Используется в: все модули
- Статус: draft

## Назначение

Общие правила безопасности денег, конфигурации, валидации и аудита. В модули не копируются целиком.

## Запуск и воспроизводимость

- REQ-OVW-003: `docker compose up` — одна команда, без ручных шагов.
- REQ-OVW-004: чистая машина, установлен только Docker.
- REQ-OVW-005: сценарии можно прогнать через маленький UI, CLI или задокументированные `curl`.
- REQ-OVW-007: иначе задание дальше не оценивается.

## Секреты

- REQ-SEC-001: `applicationId` / `password` come from config/env — never hard-coded, never logged.
- REQ-SEC-002: No credentials in code or logs.
- REQ-SEC-005: Every Platform request carries `applicationId` + `password` in the JSON body (no OAuth).
- REQ-SEC-006: Secrets in env; input validation; masked-MSISDN safe. (рубрика, 8 баллов)

## Валидация на границе

- REQ-SEC-003: Validate MSISDN format (`tel:` + digits) and amount (2-dp); reject junk at the boundary.
- REQ-SEC-004: The code must work when `subscriberId` is a stable masked alias, not a real number (don't assume you can parse a phone number out of it).

Оба требования обязательны. Как они стыкуются, в ТЗ не разведено. Принятый Q8: вход `^tel:[0-9]+$`, значение хранится и уходит в Platform целиком, телефонный план не разбирается.

## Идемпотентность денег

- REQ-CHG-007: A subscriber is charged at most once per billing day even under duplicate webhooks, job re-runs, retries, and restarts.
- REQ-CHG-008: Design the `externalTrxId` deliberately and explain it.
- REQ-CHG-009: `externalTrxId` is the idempotency key. The Platform detects duplicates via it (`E1337`). Reusing the same value for a logical retry is intentional; a fresh value creates a second charge.
- REQ-CHG-012: Distinguish retry-able vs non-retry-able codes. Bounded retries with backoff. Never retry a non-retry-able error into a double charge.
- REQ-OVW-008: Double-charge при дубле webhook или повторном запуске job — провал сдачи.

## Аудит

- REQ-AUD-001: Subscribers and every charge attempt (status, `externalTrxId`, `referenceId`, timestamp) are persisted and queryable.
- REQ-AUD-002: A finance person must be able to answer "was MSISDN X charged today, and what's the reference?".

## Транспорт Platform

- REQ-API-001: HTTP + JSON, `Content-Type: application/json`.
- REQ-API-002: Every response includes `statusCode` + `statusDetail`.

Таймауты внешних вызовов в ТЗ не заданы. Для реализации их требует контракт проекта (`.cursor/rules`), это не требование ТЗ. См. допущение A-TIMEOUTS.

## Аутентификация пользователей

Не строить: REQ-OVW-006 (real auth/SSO). Отдельной модели прав в ТЗ нет.

## Логирование

Явно запрещено логировать `password` / credentials (REQ-SEC-001, REQ-SEC-002). Иных полей аудита логов ТЗ не задаёт.
