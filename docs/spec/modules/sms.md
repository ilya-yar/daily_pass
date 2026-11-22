# Модуль: SMS
- Источник в ТЗ: строки 19–20, 41–42, 55, 60–62, 106, 164–169
- Зависит от: platform-mock (`POST /sms/send` и видимость MT)
- Используется в: opt-in, charging, opt-out
- Статус: draft

## Назначение

Исходящие SMS (MT) через Platform и приём входящих SMS (MO), в том числе `STOP`.

## Сущности и статусы

Отдельной доменной сущности SMS в ТЗ нет. Welcome, receipt и goodbye — исходящие MT. `STOP` — входящая MO.

## Требования

- REQ-SMS-001: Send (MT) — `POST /sms/send` with `applicationId`, `password`, `message`, `destinationAddresses` (array), `version`, `sourceAddress`, `encoding:"0"`.
- REQ-SMS-002: Receive (MO) — Platform calls our MO endpoint with `applicationId`, `sourceAddress` (the subscriber), `message`, `encoding`, `version`. Reply:

```json
{ "statusCode": "S1000", "statusDetail": "Success" }
```

- REQ-SMS-003: Welcome SMS is visible in the mock.
- REQ-OPTIN-004: welcome MT при opt-in.
- REQ-CHG-003: receipt MT содержит `referenceId` после `S1000`.
- REQ-OUT-001 / REQ-OUT-004: MO `STOP` и goodbye MT.

Пример назначения MT из ТЗ: `destinationAddresses: ["tel:1000000001"]`.

## Бизнес-правила и валидации

- `encoding` исходящей SMS в примере: `"0"`.
- Текст `message`, значение `sourceAddress` и shortcode не заданы. См. Q7.
- DLR handling вне скоупа (REQ-OVW-006). Доставку SMS подтверждать не требуется.
- Видимость welcome в моке означает, что мок сохраняет принятые MT и их можно посмотреть при демо (строка 106). Способ просмотра не задан. См. A-SMS-LOG.

## Интеграции

- Исходящие: Platform `POST /sms/send`.
- Входящие: наш HTTP endpoint, который вызывает Platform (в демо — способ прогнать сценарий, REQ-OVW-005).

## Права доступа

Не заданы.

## Нефункциональные требования модуля

Секреты MT-запроса не логировать (REQ-SEC-001).

## Вопросы и допущения

- Q7: тексты и `sourceAddress`.
- A-SMS-LOG: мок хранит MT и отдаёт их способом, пригодным для демо (список или лог). Это способ выполнить «visible in the mock», не отдельное требование ТЗ.
