# Модуль: Subscription notification webhook
- Источник в ТЗ: строки 45–49, 109, 156–162
- Зависит от: cross-cutting (идемпотентность), domain Subscriber
- Используется в: charging (не должен вызывать лишнее списание), platform-mock (push callback)
- Статус: draft

## Назначение

Принять callback Platform о событии подписки, ответить успехом и не испортить состояние при повторе доставки.

## Сущности и статусы

Входящий status: `REGISTERED | UNREGISTERED | PENDING | CHARGE`.

Локальные ACTIVE/INACTIVE этим разделом ТЗ явно не переключаются. F2 говорит «handle at least REGISTERED and UNREGISTERED», без таблицы переходов. См. Q5.

## Требования

- REQ-WH-001: Expose the callback the Platform calls (§B).
- REQ-WH-002: Handle at least `REGISTERED` and `UNREGISTERED`.
- REQ-WH-003: Reply `S1000`. Тело ответа из §B:

```json
{ "statusCode": "S1000", "statusDetail": "Success" }
```

- REQ-WH-004: The webhook must be idempotent — the same event delivered twice must not corrupt state or cause an extra charge.
- REQ-WH-005: Fields are `applicationId`, `subscriberId`, `frequency`, `status`, `version`, `timeStamp`, where `status ∈ REGISTERED | UNREGISTERED | PENDING | CHARGE`.
- REQ-TEST-003: A duplicate subscription notification does not double-charge (prove it).

## Бизнес-правила и валидации

- Дубль того же события не портит состояние и не списывает деньги.
- `PENDING` и `CHARGE` входят в контракт, но отдельное поведение не описано. Пока нет ответа на Q5, нельзя придумывать списание или смену статуса на этих значениях.
- Ключ идемпотентности события (какие поля образуют «то же событие») в ТЗ не задан. См. Q10.

## Интеграции

Входящий HTTP от Platform (в демо — от mock). Исходящих вызовов этот раздел не требует.

`frequency` и `timeStamp`: поля обязательны во входе по списку §B; допустимые значения и формат времени не заданы.

## Права доступа

Не заданы. Секрет webhook в ТЗ не описан.

## Нефункциональные требования модуля

Идемпотентность при повторной доставке, включая рестарт процесса, в составе REQ-CHG-007 (duplicate webhooks).

## Вопросы и допущения

- Q5: эффект `REGISTERED`, `UNREGISTERED`, `PENDING`, `CHARGE` на локальный статус.
- Q10: как узнаём, что событие «то же самое».
