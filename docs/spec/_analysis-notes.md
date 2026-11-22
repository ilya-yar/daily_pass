# Analysis notes

Источник: `ASSIGNMENT_SHORT.md` (199 строк, 9702 байта). Порции: 1–100, 101–199.

## Порция 1, строки 1–100

### Цель и границы
- 1–13: тестовое задание, AI-assisted coding ожидается. Оценивают корректность, безопасность денег, понимание кода. Scope узкий: глубина money path важнее широты. Тонкий корректный срез лучше широкого, который списывает дважды.
- 16–20: продукт **Daily Pass** — платная ежедневная подписка поверх Carrier Platform. Opt-in, фиксированная дневная плата с мобильного счёта, SMS-чек, остановка текстом `STOP`.
- 22–28: два runnable-компонента: `service/` (продукт) и `platform-mock/` (мок Carrier Platform). Мок: конфигурируемый статус списания минимум `S1000`, `E1326`, `E1308`, `E1337`; умеет push subscription notification callback в сервис.
- 30–33: `docker compose up` одной командой, без ручных шагов. Чистая машина только с Docker (нет локального runtime, нет «сначала установи X»). Способ прогнать сценарии: tiny UI, CLI или задокументированные `curl`.
- 87–88: **out of scope**: real auth/SSO, multi-currency, USSD, DLR handling, Query Balance, production infra, pixel-perfect UI. Явно сказать, что не строим.

### Функциональность
- F1 Opt-in, 39–43: вход (HTTP или UI) принимает MSISDN, вызывает Platform `POST /subscription/send` с `action:"1"`, сохраняет подписчика **ACTIVE**, шлёт welcome **SMS (MT)**. Уже активный не должен быть подписан повторно.
- F2 Webhook, 45–49: callback платформы (§B). Минимум `REGISTERED` и `UNREGISTERED`, ответ `S1000`. Идемпотентность: то же событие дважды не портит состояние и не вызывает лишнее списание.
- F3 Daily charging, 51–58: job (по расписанию или ручной триггер для демо) списывает со всех ACTIVE дневную плату **не больше одного раза за billing day**:
  - Direct Debit с осознанно спроектированным idempotency key `externalTrxId`;
  - `S1000` → записать транзакцию, SMS receipt с `referenceId`;
  - `E1326` (insufficient balance, transient) → grace / retry policy;
  - `E1308` (permanent) → **suspend** подписчика.
  - Повтор job в тот же день не даёт второго списания.
- F4 Opt-out, 60–64: входящий **MO SMS** `STOP` (§C): `/subscription/send` с `action:"0"`, статус **INACTIVE**, goodbye SMS. После opt-out никогда не списывать. Гонка opt-out и in-flight charging job.
- F5 Status, 66–67: endpoint текущего состояния подписчика (наша view).

### НФТ
- 73–75: at most once per billing day при duplicate webhooks, job re-runs, retries, restarts. `externalTrxId` спроектировать и объяснить.
- 76–77: retry-able vs non-retry-able. Bounded retries + backoff. Не ретраить non-retry-able в двойное списание.
- 78: `applicationId` / `password` из config/env, не хардкод, не логировать.
- 79–80: валидация MSISDN (`tel:` + digits) и amount (2-dp); мусор отвергать на границе.
- 81–82: код должен работать, когда `subscriberId` — стабильный masked alias, а не настоящий номер. Не парсить телефон из него.
- 83–85: persist subscribers и каждый charge attempt (status, `externalTrxId`, `referenceId`, timestamp), queryable. Финансист должен ответить «был ли MSISDN X списан сегодня и какой reference».

### Deliverables
- 94: git repo с `service/` и `platform-mock/`.
- 95–96: `README.md` — точный `docker compose up` и как вызвать сценарии: opt-in, daily charge success, insufficient balance, duplicate webhook, opt-out.
- 97–98: автотесты money-critical: charge-once idempotency, retry classification, opt-out-then-charge race. Coverage важна здесь.
- 99: `DECISIONS.md` ~полстраницы: trade-offs, что сознательно пропущено, known gaps.

### Заметки / вопросы из порции 1
- Статусы подписчика в тексте: ACTIVE, INACTIVE, suspend (F3 E1308). Не сказано, отдельный ли статус SUSPENDED от INACTIVE.
- «already active must not be double-subscribed» vs webhook REGISTERED — кто источник истины состояния.
- billing day не определён (UTC? граница суток?).
- daily fee amount не задан числом.
- grace / retry policy не задана числами (сколько попыток, интервал).
- F5 «your view» не задаёт формат ответа.
- Точка входа opt-in: HTTP или UI — не зафиксировано.

## Порция 2, строки 101–199

### Acceptance checklist (103–112)
- 105: `docker compose up` на чистой машине поднимает service + mock, без ручных шагов.
- 106: opt-in регистрирует подписчика и welcome SMS виден в моке.
- 107: успешное дневное списание — один раз, транзакция сохранена, receipt с `referenceId`.
- 108: job дважды в тот же день списывает один раз (доказать тестом).
- 109: дубль subscription notification не делает double-charge (доказать).
- 110: `E1326` и `E1308` обрабатываются по-разному.
- 111: `STOP` отписывает; следующий charge run пропускает подписчика.
- 112: нет credentials в коде и логах.

### Рубрика (116–132)
- 120: Money safety & idempotency — 35. Provably charge-once; sound `externalTrxId`; survives restarts/dupes.
- 121: Correctness F1–F5 — 25. Все потоки end-to-end против мока.
- 122: Error & retry — 15. Классификация retry-able vs not; backoff; нет double charge на retry.
- 123: Tests — 10. Money paths; сбои симулируются моком.
- 124: Security & config hygiene — 8. Secrets в env; validation; masked-MSISDN safe.
- 125: Code clarity & docs — 7. Читаемо, маленькие модули; нет мёртвого AI-кода; честный DECISIONS.md.
- 127–129: hard gate — если `docker compose up` не поднимает всё на чистой машине, дальше не оценивают.
- 131–132: «выглядит готовым», но double-charge на дубле webhook или повторном job — провал, независимо от полировки.

### Контракт Platform (136–199) — единственный integration contract
- 138–143: HTTP + JSON, `Content-Type: application/json`. Base URL placeholder `https://api.carrier.example`, направить на мок. Auth: `applicationId` + `password` в JSON body, без OAuth. Каждый ответ содержит `statusCode` + `statusDetail`.
- §A 145–154: `POST /subscription/send`. Тело: `applicationId`, `password`, `version` `"1.0"`, `action` `"1"` opt-in / `"0"` opt-out, `subscriberId` `"tel:1000000001"`. Ответ: `version`, `requestId`, `statusCode` `S1000`, `statusDetail` `SUCCESS`, `subscriptionStatus` `REGISTERED` (для opt-out `UNREGISTERED`).
- §B 156–162: Platform → наш webhook. Поля: `applicationId`, `subscriberId`, `frequency`, `status`, `version`, `timeStamp`. `status ∈ REGISTERED | UNREGISTERED | PENDING | CHARGE`. Ответ: `{ "statusCode": "S1000", "statusDetail": "Success" }`.
- §C 164–169: MT `POST /sms/send`: `applicationId`, `password`, `message`, `destinationAddresses: ["tel:..."]`, `version`, `sourceAddress`, `encoding:"0"`. MO: Platform → наш MO endpoint: `applicationId`, `sourceAddress`, `message`, `encoding`, `version`. Ответ `S1000` / `Success`.
- §D 171–186: `POST /caas/direct/debit`. Поля запроса включая `externalTrxId`, `paymentInstrumentName` `"Mobile Account"`, `amount` `"5.00"`, `currency` `"XTS"`. Ответ: `statusCode`, `statusDetail`, `externalTrxId`, `internalTrxId`, `referenceId`, `timeStamp` пример `2013-08-01T08:43:34.344+05:30`. `externalTrxId` — ключ идемпотентности. Повтор того же значения — осознанный retry (`E1337`); новое значение — второе списание.
- §E 188–199:

| Code | Meaning | Class |
|------|---------|-------|
| `S1000` | Success | terminal-ok |
| `E1337` | Duplicate request (same `externalTrxId`) | non-retry-able |
| `E1308` | Permanent charging error | non-retry-able → suspend |
| `E1326` | Insufficient balance (transient — user may top up) | retry-able → grace/retry |
| `E1318` | Per-second transaction limit exceeded | retry-able → throttle/backoff |

- 198–199: `E1326` и `E1308` оба означают «не списано», но обработка разная: grace/retry vs suspend.

### Новые вопросы порции 2
- Числовые лимиты retry/backoff/grace не заданы.
- `amount` `"5.00"` и `currency` `"XTS"` стоят в примере §D, не в описании продукта как тариф.
- Поведение `PENDING` и `CHARGE` кроме вхождения в enum не описано. F2 требует обработать минимум `REGISTERED` и `UNREGISTERED`.
- Форма ответа `E1337` (есть ли `referenceId`) не задана.
- `sourceAddress`, текст SMS, `frequency` не заданы.
- `paymentInstrumentName` показан только примером `"Mobile Account"`.
