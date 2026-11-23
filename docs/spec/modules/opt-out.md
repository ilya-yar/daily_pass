# Модуль: Opt-out
- Источник в ТЗ: строки 60–64, 111, 145–154, 167–169
- Зависит от: sms (MO и goodbye MT), charging (гонка со списанием), platform-mock (§A)
- Используется в: charging (INACTIVE пропускать)
- Статус: draft

## Назначение

Остановить подписку входящей SMS `STOP`: отписать на Platform, пометить INACTIVE, отправить прощальную SMS. После этого абонента больше никогда не списывать.

## Сущности и статусы

Subscriber: ACTIVE → **INACTIVE**. Ответ Platform: `subscriptionStatus` `UNREGISTERED`.

## Требования

- REQ-OUT-001: Handle an inbound **MO SMS** `STOP` (§C).
- REQ-OUT-002: Call `/subscription/send` with `action:"0"`.
- REQ-OUT-003: Mark the subscriber **INACTIVE**.
- REQ-OUT-004: Send a goodbye SMS.
- REQ-OUT-005: After opt-out the subscriber must **never** be charged again — mind the race between opt-out and an in-flight charging job.
- REQ-OUT-006: `STOP` opts the subscriber out; a subsequent charge run skips them.
- REQ-SMS-002: MO request fields and the required reply (см. `modules/sms.md`).

Тот же вызов §A, что и opt-in, с `action` `"0"`. Успешный `subscriptionStatus`: `"UNREGISTERED"`.

## Бизнес-правила и валидации

- Срабатывание — входящее MO с текстом `STOP`. Другие тексты MO в ТЗ не описаны.
- Гонка: opt-out и уже выполняющийся charging job. Итог обязан быть «не списан после opt-out». Механизм (блокировка, проверка статуса перед дебетом, тот же `externalTrxId`) ТЗ не предписывает.
- Повторный `STOP` в ТЗ не описан. Принятый Q12: уже INACTIVE не вызывает Platform повторно и не шлёт второй goodbye. Неизвестный `sourceAddress` отвечает `S1000` без Platform и без SMS.
- Ошибка `POST /subscription/send` при opt-out не описана.

## Интеграции

- Вход: MO endpoint нашего сервиса (§C).
- Выход: `POST /subscription/send` action `"0"`.
- Выход: `POST /sms/send` goodbye. Текст в ТЗ не задан. Принятый Q7: «Daily Pass is stopped».

## Права доступа

Не заданы.

## Нефункциональные требования модуля

REQ-TEST-001 включает opt-out-then-charge race. Это обязательный тест, не только сценарий.

## Вопросы и допущения

- Q7 и Q12 приняты вариантами по умолчанию. Тексты — в `open-questions.md`.
