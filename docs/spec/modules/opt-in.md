# Модуль: Opt-in
- Источник в ТЗ: строки 39–43, 106, 145–154
- Зависит от: sms, platform-mock (§A), cross-cutting (валидация, секреты)
- Используется в: charging (только ACTIVE), status, subscription-webhook
- Статус: draft

## Назначение

Подключить абонента к Daily Pass: вызвать Platform, сохранить ACTIVE, отправить welcome SMS. Повторно уже активного не подписывать.

## Сущности и статусы

Subscriber → **ACTIVE**. Платформенный `subscriptionStatus` успешного ответа: `REGISTERED`.

## Требования

- REQ-OPTIN-001: An entry point (HTTP endpoint or UI action) takes an MSISDN.
- REQ-OPTIN-002: Opt-in calls the Platform `POST /subscription/send` with `action:"1"`.
- REQ-OPTIN-003: Opt-in persists the subscriber as **ACTIVE**.
- REQ-OPTIN-004: Opt-in sends a welcome **SMS (MT)**.
- REQ-OPTIN-005: A subscriber who is already active must not be double-subscribed.
- REQ-SMS-003: The welcome SMS is visible in the mock (acceptance, строка 106).

## Контракт вызова §A (opt-in)

`POST /subscription/send`

```json
{ "applicationId": "APP_000123", "password": "<password>", "version": "1.0",
  "action": "1", "subscriberId": "tel:1000000001" }
```

`action` `"1"` = opt-in, `"0"` = opt-out.

Response:

```json
{ "version": "1.0", "requestId": "1374746416574", "statusCode": "S1000",
  "statusDetail": "SUCCESS", "subscriptionStatus": "REGISTERED" }
```

`"UNREGISTERED"` — для opt-out, см. `modules/opt-out.md`.

REQ-API-003: реализовать этот запрос и разобрать этот ответ. `applicationId` и `password` — из env (REQ-SEC-001).

## Бизнес-правила и валидации

- MSISDN на входе: `tel:` + digits, иначе отказ (REQ-SEC-003).
- Уже ACTIVE — не double-subscribe (REQ-OPTIN-005). Что именно не повторять (вызов Platform, SMS, оба) ТЗ не уточняет. См. Q9.
- Ответ Platform не `S1000` для opt-in в ТЗ не описан.

## Интеграции

- Platform `POST /subscription/send`.
- Platform `POST /sms/send` — текст welcome не задан. См. Q7 и `modules/sms.md`.

## Права доступа

Не заданы. Auth/SSO вне скоупа (REQ-OVW-006).

## Нефункциональные требования модуля

Повторный opt-in активного абонента не создаёт вторую подписку. Идемпотентность webhook — в `modules/subscription-webhook.md`.

## Вопросы и допущения

- Q7 текст SMS и `sourceAddress`.
- Q8 формат masked `subscriberId` против валидации `tel:` + digits.
- Q9 поведение повторного opt-in.
- A-ENTRY: конкретный URL точки входа в ТЗ не задан; выбор — в Q6, это не требование.
