# Покрытие `ASSIGNMENT_SHORT.md`

Каждый диапазон строк исходника отнесён к файлу спецификации. «Не требование» — мета, его всё равно нельзя потерять: оно записано в обзоре.

| Строки | Что в ТЗ | Куда попало | Требования |
|---|---|---|---|
| 1–4 | Роль, AI-assisted coding разрешён | `00-overview.md` контекст | не требование к системе |
| 5–8 | Критерий оценки: корректность, деньги, понимание кода | `00-overview.md` контекст | не требование к системе |
| 9–13 | Узкий скоуп, глубина money path | `00-overview.md` | REQ-OVW-001 (рамка), REQ-OVW-008 |
| 16–20 | Продукт Daily Pass | `00-overview.md`, `01-glossary.md` | REQ-OVW-001 |
| 22–28 | `service/`, `platform-mock/`, конфигурируемый код, push callback | `00-overview.md`, `modules/platform-mock.md` | REQ-OVW-002, REQ-MOCK-001, REQ-MOCK-002, REQ-MOCK-003 |
| 30–33 | `docker compose up`, только Docker, способ прогнать сценарии | `00-overview.md`, `03-cross-cutting.md` | REQ-OVW-003, REQ-OVW-004, REQ-OVW-005 |
| 37–43 | F1 Opt-in | `modules/opt-in.md` | REQ-OPTIN-001 … REQ-OPTIN-005 |
| 45–49 | F2 Webhook | `modules/subscription-webhook.md` | REQ-WH-001 … REQ-WH-004 |
| 51–58 | F3 Daily charging | `modules/charging.md` | REQ-CHG-001 … REQ-CHG-006 |
| 60–64 | F4 Opt-out | `modules/opt-out.md` | REQ-OUT-001 … REQ-OUT-005 |
| 66–67 | F5 Status | `modules/status.md` | REQ-STA-001 |
| 71–75 | Money safety, `externalTrxId` | `03-cross-cutting.md`, `modules/charging.md` | REQ-CHG-007, REQ-CHG-008 |
| 76–77 | Retry policy | `03-cross-cutting.md`, `modules/charging.md` | REQ-CHG-012 |
| 78 | Секреты env, не логировать | `03-cross-cutting.md` | REQ-SEC-001 |
| 79–80 | Валидация MSISDN и amount | `03-cross-cutting.md` | REQ-SEC-003 |
| 81–82 | Masked MSISDN | `03-cross-cutting.md`, `01-glossary.md` | REQ-SEC-004 |
| 83–85 | Persistence и вопрос финансиста | `03-cross-cutting.md`, `02-domain-model.md` | REQ-AUD-001, REQ-AUD-002 |
| 87–88 | Out of scope | `00-overview.md` | REQ-OVW-006 |
| 92–99 | Deliverables | `05-acceptance.md` | REQ-OVW-002, REQ-DOC-001, REQ-DOC-002, REQ-TEST-001 |
| 103–112 | Acceptance checklist | `05-acceptance.md` | REQ-TEST-002, REQ-TEST-003, плюс ссылки на F1–F5 и SEC |
| 116–125 | Рубрика, таблица баллов | `05-acceptance.md` | REQ-EVAL-001 … REQ-EVAL-006, REQ-DOC-003, REQ-TEST-004, REQ-SEC-006 |
| 127–129 | Hard gate docker | `00-overview.md` | REQ-OVW-007 |
| 131–132 | Провал при double-charge | `00-overview.md` | REQ-OVW-008 |
| 136–143 | Транспорт, auth body, base URL | `04-integrations.md`, `03-cross-cutting.md` | REQ-API-001, REQ-API-002, REQ-SEC-005, REQ-MOCK-005 |
| 145–154 | §A Opt-in / Opt-out | `modules/opt-in.md`, `modules/opt-out.md` | REQ-API-003, REQ-OPTIN-002, REQ-OUT-002 |
| 156–162 | §B поля и ответ webhook | `modules/subscription-webhook.md` | REQ-WH-003, REQ-WH-005 |
| 164–169 | §C MT и MO | `modules/sms.md` | REQ-SMS-001, REQ-SMS-002 |
| 171–186 | §D Direct Debit и смысл ключа | `modules/charging.md` | REQ-CHG-009, REQ-CHG-013 |
| 188–197 | §E таблица кодов | `modules/charging.md` | REQ-CHG-010, REQ-CHG-011, REQ-CHG-004, REQ-CHG-005 |
| 198–199 | Design note: E1326 ≠ E1308 | `modules/charging.md` | REQ-CHG-004, REQ-CHG-005 |

Непокрытых диапазонов нет. Пустые строки 14–15, 34–36, 68–70, 89–91, 100–102, 113–115, 133–135, 144, 155, 163, 170, 187 входят в соседние диапазоны как разделители и требований не содержат.

REQ-DOC-004 дублирует формулировку REQ-CHG-008 («design and explain `externalTrxId`») и привязан к `DECISIONS.md`, чтобы объяснение не потерялось среди НФТ.
