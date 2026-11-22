# Интеграции
- Источник в ТЗ: строки 136–143 и указатели на §A–§E
- Зависит от: `03-cross-cutting.md`
- Используется в: `service/`, `platform-mock/`
- Статус: draft

## Назначение

Единственная внешняя система — Carrier Platform. Реального оператора нет: base URL смотрит на `platform-mock/` (REQ-MOCK-005).

## Общий контракт

Brand-neutral reference for a generic mobile-operator billing gateway ("the Platform").

- REQ-API-001: Transport HTTP + JSON, `Content-Type: application/json`.
- REQ-API-002: Every response includes `statusCode` + `statusDetail`.
- REQ-SEC-005: Auth is `applicationId` + `password` in the JSON body. No OAuth.
- Placeholder base URL: `https://api.carrier.example`.

## Куда разложены эндпоинты

| Контракт | Куда | Суть |
|---|---|---|
| §A `POST /subscription/send` | `modules/opt-in.md`, `modules/opt-out.md` | action `"1"` / `"0"` |
| §B Subscription Notification | `modules/subscription-webhook.md` | Platform вызывает наш webhook |
| §C SMS MT/MO | `modules/sms.md` | `POST /sms/send` и наш MO endpoint |
| §D `POST /caas/direct/debit` | `modules/charging.md` | списание и `externalTrxId` |
| §E коды | `modules/charging.md` | таблица перенесена дословно |

Мок этих эндпоинтов и callback — `modules/platform-mock.md`.
