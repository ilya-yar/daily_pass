# Модуль: Platform mock
- Источник в ТЗ: строки 25–28, 94, 106, 123, 136–141
- Зависит от: контракты §A–§E (модули opt-in, sms, charging, subscription-webhook)
- Используется в: все сценарии `service/` и тесты
- Статус: draft

## Назначение

Локальная замена Carrier Platform, чтобы поток шёл без оператора. Поднимается вместе с `service/` одной командой `docker compose up`.

## Сущности и статусы

- Сохранённые MT, чтобы welcome был виден (REQ-SMS-003).
- Принятые Direct Debit по `externalTrxId`, чтобы повтор того же ключа давал `E1337` (REQ-CHG-009).
- Настраиваемый код ответа списания.

## Требования

- REQ-MOCK-001: A small mock of the Carrier Platform (the endpoints in the appendix) so the flow runs locally with no real operator.
- REQ-MOCK-002: The mock must return a **configurable** status code for a charge (at least `S1000`, `E1326`, `E1308`, `E1337`).
- REQ-MOCK-003: The mock must be able to **push a subscription notification callback** to the service.
- REQ-MOCK-004: Failures are simulated via the mock (рубрика тестов).
- REQ-MOCK-005: Service base URL points at this mock, not at `https://api.carrier.example`.
- REQ-OVW-002: каталог `platform-mock/` в репозитории.
- REQ-OVW-003 / REQ-OVW-004: мок поднимается `docker compose up` на машине, где есть только Docker.

Эндпоинты, которые сервис вызывает и которые мок обязан принять:

- `POST /subscription/send` — §A, см. `modules/opt-in.md`.
- `POST /sms/send` — §C, см. `modules/sms.md`.
- `POST /caas/direct/debit` — §D, см. `modules/charging.md`.

Ответы содержат `statusCode` и `statusDetail` (REQ-API-002).

## Бизнес-правила и валидации

- Для списания код ответа конфигурируется. Минимум четыре значения: `S1000`, `E1326`, `E1308`, `E1337`. Способ конфигурации в ТЗ не задан. Принятый Q13: env `MOCK_CHARGE_STATUS`; уже виденный `externalTrxId` всегда `E1337`.
- Тот же `externalTrxId` Platform считает дублем (`E1337`). Мок должен вести себя так, иначе сервис нельзя проверить на идемпотентность.
- `E1318` в списке «at least» для мока нет. Обработка `E1318` на стороне сервиса обязательна (§E «you must handle»). Умение мока вернуть `E1318` в ТЗ не требуется. См. A-MOCK-E1318.
- Push callback — исходящий вызов мока на webhook сервиса (§B).

## Интеграции

- Вход от `service/`: три POST выше.
- Выход в `service/`: subscription notification callback (REQ-MOCK-003).
- MO `STOP` мок пушить не обязан: ТЗ требует push только для subscription notification. Прогон `STOP` закрывается REQ-OVW-005 (curl/CLI/UI на MO endpoint сервиса).

## Права доступа

Не заданы. Проверка `password` моком не описана.

## Нефункциональные требования модуля

Запуск без локального PHP и без ручных шагов (REQ-OVW-004).

## Вопросы и допущения

- Q13 принят вариантом по умолчанию. Текст — в `open-questions.md`.
- A-SMS-LOG: сохранённые MT можно прочитать при демо.
- A-MOCK-E1318: возврат `E1318` моком не требуется ТЗ; для теста классификации его можно добавить, но это расширение. Не записывать как требование, пока пользователь не попросит.
