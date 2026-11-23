# Приёмка, сдача и оценка
- Источник в ТЗ: строки 92–132
- Зависит от: модули F1–F5, `platform-mock`
- Используется в: этап стабилизации
- Статус: draft

## Deliverables

- REQ-DOC-001: `README.md` with the exact `docker compose up` run, and how to trigger each scenario (opt-in, daily charge success, insufficient balance, duplicate webhook, opt-out).
- REQ-TEST-001: Automated tests for the money-critical paths: charge-once idempotency, retry classification, opt-out-then-charge race.
- REQ-DOC-002: `DECISIONS.md` — about half a page: key trade-offs, what you deliberately skipped, known gaps.
- REQ-OVW-002: A git repo with `service/` and `platform-mock/`.

## Acceptance checklist

Формулировки чеклиста (строки 105–112). Каждая закрывается требованиями модулей:

- [x] `docker compose up` on a clean machine brings up service + mock, no manual steps. → REQ-OVW-003, REQ-OVW-004, REQ-OVW-007 (`tests/integration/health.sh`, `HealthCest`)
- [x] Opt-in registers the subscriber and sends a welcome SMS visible in the mock. → REQ-OPTIN-003, REQ-OPTIN-004, REQ-SMS-003 (`OptInCest`, `EndToEndFlowsCest`)
- [x] Daily charge success charges once, stores the transaction, sends a receipt with `referenceId`. → REQ-CHG-003 (`DailyChargeCest`, `EndToEndFlowsCest`)
- [x] Running the daily job **twice** in the same day charges **once** (prove it in a test). → REQ-CHG-006, REQ-TEST-002 (`DailyChargeCest`)
- [x] A duplicate subscription notification does **not** double-charge (prove it). → REQ-WH-004, REQ-TEST-003 (`SubscriptionWebhookCest`, `EndToEndFlowsCest`)
- [x] Insufficient balance (`E1326`) and permanent error (`E1308`) are handled **differently**. → REQ-CHG-004, REQ-CHG-005 (`DailyChargeCest`, `RunDailyChargeTest`)
- [x] `STOP` opts the subscriber out; a subsequent charge run skips them. → REQ-OUT-006 (`MoStopCest`, `EndToEndFlowsCest`; race: `RunDailyChargeTest`)
- [x] No credentials in code or logs. → REQ-SEC-002 (`HttpPlatformClientTest`, `SourceHasNoPlatformPasswordTest`, `SecretsHygieneCest`)

- REQ-TEST-002: Running the daily job twice in the same day charges once (prove it in a test).
- REQ-TEST-003: A duplicate subscription notification does not double-charge (prove it).

## Evaluation rubric (100)

Таблица строк 118–125 перенесена дословно.

| Area | Pts | What "great" looks like |
|------|-----|--------------------------|
| Money safety & idempotency | 35 | Provably charge-once; sound `externalTrxId`; survives restarts/dupes |
| Correctness of flows (F1–F5) | 25 | All flows work end-to-end against the mock |
| Error & retry handling | 15 | Retry-able vs not correctly classified; backoff; no double charge on retry |
| Tests | 10 | Money paths covered; failures simulated via the mock |
| Security & config hygiene | 8 | Secrets in env; input validation; masked-MSISDN safe |
| Code clarity & docs | 7 | Readable, small modules; no dead AI cruft; honest DECISIONS.md |

- REQ-EVAL-001: Money safety & idempotency, 35. Provably charge-once; sound `externalTrxId`; survives restarts/dupes.
- REQ-EVAL-002: Correctness of flows (F1–F5), 25. All flows work end-to-end against the mock.
- REQ-EVAL-003: Error & retry handling, 15. Retry-able vs not correctly classified; backoff; no double charge on retry.
- REQ-EVAL-004: Tests, 10. Money paths covered; failures simulated via the mock. → также REQ-TEST-004, REQ-MOCK-004.
- REQ-EVAL-005: Security & config hygiene, 8. Совпадает с REQ-SEC-006.
- REQ-EVAL-006: Code clarity & docs, 7. Readable, small modules; no dead AI cruft; honest DECISIONS.md. → также REQ-DOC-003.
- REQ-DOC-003: Readable, small modules; no dead AI cruft; honest `DECISIONS.md`.
- REQ-DOC-004: Design the `externalTrxId` deliberately and explain it. Совпадает по смыслу с REQ-CHG-008; оба сохраняются, потому что объяснение требуется и в НФТ, и в ясности решения.
- REQ-TEST-004: Money paths covered; failures simulated via the mock.

Hard gate и провал за double-charge — REQ-OVW-007 и REQ-OVW-008 в `00-overview.md`.
