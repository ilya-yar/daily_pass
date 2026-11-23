# Модуль: Daily charging
- Источник в ТЗ: строки 51–58, 73–77, 107–110, 171–199
- Зависит от: opt-in (ACTIVE), opt-out (INACTIVE не списывать), sms (receipt), platform-mock (§D, §E), cross-cutting
- Используется в: audit, acceptance
- Статус: draft

## Назначение

Списывать с каждого ACTIVE-подписчика фиксированную дневную плату не больше одного раза за billing day и по-разному обрабатывать коды Platform.

## Сущности и статусы

Charge attempt: status, `externalTrxId`, `referenceId`, timestamp (REQ-AUD-001).

На `E1308` подписчик suspend (глагол из ТЗ). Принятый Q4: локальный статус `SUSPENDED`.

## Требования

- REQ-CHG-001: A job (scheduled **or** manually triggerable for the demo) charges every ACTIVE subscriber the daily fee **at most once per billing day**.
- REQ-CHG-002: Call Direct Debit with a deliberately-designed idempotency key (`externalTrxId`).
- REQ-CHG-003: On `S1000` → record the transaction, send an SMS receipt with the `referenceId`.
- REQ-CHG-004: On `E1326` (insufficient balance, **transient**) → apply a grace / retry policy.
- REQ-CHG-005: On `E1308` (**permanent**) → **suspend** the subscriber.
- REQ-CHG-006: Re-running the job the same day must **not** produce a second charge.
- REQ-CHG-007: At most once per billing day even under duplicate webhooks, job re-runs, retries, and restarts.
- REQ-CHG-008: Design the `externalTrxId` deliberately and explain it.
- REQ-CHG-009: `externalTrxId` is the idempotency key. The Platform detects duplicates via it (`E1337`). Reusing the same value for a logical retry is intentional; a fresh value creates a second charge.
- REQ-CHG-010: `E1337` Duplicate request (same `externalTrxId`) — non-retry-able.
- REQ-CHG-011: `E1318` Per-second transaction limit exceeded — retry-able → throttle/backoff.
- REQ-CHG-012: Distinguish retry-able vs non-retry-able codes. Bounded retries with backoff. Never retry a non-retry-able error into a double charge.
- REQ-TEST-002: Running the daily job twice in the same day charges once (prove it in a test).

Сумма и валюта дневной платы в описании продукта не заданы. В примере §D стоят `"5.00"` и `"XTS"`. Принятый Q2: плата `5.00` `XTS` из env.

## Контракт §D — `POST /caas/direct/debit`

```json
{ "applicationId": "APP_000123", "password": "<password>",
  "externalTrxId": "25609",
  "subscriberId": "tel:1000000001",
  "paymentInstrumentName": "Mobile Account",
  "amount": "5.00", "currency": "XTS" }
```

Комментарий ТЗ к `externalTrxId`: `YOUR id — maps request↔response, dedupe key`.

Response:

```json
{ "statusCode": "S1000", "statusDetail": "Request was successfully processed",
  "externalTrxId": "25609", "internalTrxId": "913080108430074",
  "referenceId": "80430074", "timeStamp": "2013-08-01T08:43:34.344+05:30" }
```

- REQ-CHG-013: запрос и успешный ответ Direct Debit имеют поля из этих примеров. `paymentInstrumentName` в ТЗ встретился только как `"Mobile Account"`. См. A-INSTRUMENT в `open-questions.md`.

Amount на границе — 2 decimal places (REQ-SEC-003).

## Коды §E, которые нужно уметь обработать

| Code | Meaning | Class |
|------|---------|-------|
| `S1000` | Success | terminal-ok |
| `E1337` | Duplicate request (same `externalTrxId`) | non-retry-able |
| `E1308` | Permanent charging error | non-retry-able → suspend |
| `E1326` | Insufficient balance (transient — user may top up) | retry-able → grace/retry |
| `E1318` | Per-second transaction limit exceeded | retry-able → throttle/backoff |

> Design note: `E1326` and `E1308` both mean "not charged", but call for **different** handling — grace/retry vs. suspend. Decide your policy deliberately.

Форма тела ответа для кодов ошибки, кроме общего `statusCode` + `statusDetail` (REQ-API-002), не задана. Принятый Q11: `E1337` не порождает новый ключ и второй debit; `referenceId` сохраняется, только если он есть в ответе.

## Бизнес-правила и валидации

- Job берёт только ACTIVE.
- Повтор job в тот же billing day не создаёт второе списание. Для логического повтора тот же `externalTrxId`; новый `externalTrxId` Platform воспримет как новое списание.
- `E1326` и `E1308` обрабатываются по-разному (строка 110): retry/grace против suspend.
- Non-retry-able (`E1337`, `E1308`) нельзя ретраить так, чтобы получилось второе списание.
- Retry-able (`E1326`, `E1318`) — ограниченные повторы с backoff. Принятый Q3: не больше 3 попыток, пауза 1/2/4 с, тот же `externalTrxId`.
- Граница billing day в ТЗ не задана. Принятый Q1: календарная дата UTC.
- После opt-out подписчик не списывается, включая гонку с уже идущим job. Правило — в `modules/opt-out.md` (REQ-OUT-005).

## Интеграции

- `POST /caas/direct/debit`.
- `POST /sms/send` только после `S1000`, в тексте есть `referenceId`.

## Права доступа

Не заданы. Ручной запуск job для демо допустим наравне с расписанием (REQ-CHG-001: «scheduled or manually triggerable»). Расписание не обязательно, если есть ручной запуск.

## Нефункциональные требования модуля

Устойчивость к рестарту: повторная обработка того же billing day не списывает второй раз (REQ-CHG-007, REQ-EVAL-001).

## Вопросы и допущения

- Q1, Q2, Q3, Q4, Q11 приняты вариантами по умолчанию. Тексты — в `open-questions.md`.
- A-INSTRUMENT: в запросе передавать `paymentInstrumentName` = `Mobile Account`, как в единственном примере.
