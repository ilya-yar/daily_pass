# Прогресс
Обновлено: 2026-11-23
Текущий этап: 3 — Сценарии сервиса
Текущая задача: T-3.4 (done)

## Сделано
- Roadmap переведён в `confirmed`. Q6 принят отдельно. Q1–Q5 и Q7–Q13 приняты 2026-11-23 вариантами по умолчанию.
- T-0.1 ✅ `docker compose up` поднимает service, platform-mock и PostgreSQL. Коммит `a1f27d7`.
- T-0.2 ✅ В `service/` есть Composer-скрипты `cs:fix`, `cs:check`, `stan`, `test`, `check`, Codeception (unit / integration / api), PHPStan 8, PHP-CS-Fixer, слои `src/Domain`, `src/Application`, `src/Infrastructure`, `src/Presentation`. Хук `.githooks/pre-commit` вызывает `composer check` внутри контейнера `service`. Включено: `core.hooksPath=.githooks`.
- T-1.1 ✅ Таблицы `subscriber` и `charge_attempt`. `external_trx_id` уникален, пустой `reference_id` допустим. Запись обоих в одной транзакции: сбой попытки откатывает нового подписчика. Статусы подписчика — дефолт Q4. Миграция выполняется при старте контейнера (`bin/serve.sh`).
- T-1.2 ✅ `SubscriberId` принимает только `^tel:[0-9]+$` и хранит строку целиком, без разбора телефонного плана. `Amount` принимает ровно два знака после точки и хранит минорные единицы целым числом.
- T-1.3 ✅ Коды §E: `S1000` terminal-ok; `E1337` и `E1308` non-retry-able (`E1308` → suspend); `E1326` retry-able → grace/retry; `E1318` retry-able → throttle/backoff. Неизвестный код non-retry-able. Числа попыток здесь нет.
- T-1.4 ✅ HTTP-клиент Platform: `POST /subscription/send`, `/sms/send`, `/caas/direct/debit`. `applicationId` и `password` только из env (`PLATFORM_APPLICATION_ID`, `PLATFORM_PASSWORD`). Таймауты connect/read: env `PLATFORM_CONNECT_TIMEOUT` и `PLATFORM_READ_TIMEOUT`, иначе 2 с и 5 с. Клиент не ретраит и не меняет `externalTrxId`. Пароль и application id не пишутся в лог.
- T-1.5 ✅ `externalTrxId` = календарная дата UTC и SHA-256 всего `subscriberId`. Тот же абонент и тот же UTC-день дают тот же ключ. Другой день или другой абонент меняют ключ. Номера попытки и секрета Platform в ключе нет. Время берётся из `Clock`, не из системных часов.
- T-2.1 ✅ Мок `POST /subscription/send`. `action` `"1"` отвечает `S1000` и `subscriptionStatus` `REGISTERED`, `"0"` — `UNREGISTERED`. В ответе есть `version`, `requestId`, `statusDetail`. Битый JSON и иной `action` получают `E1000`, не `S1000`. `E1000` — отказ мока, не код §E.
- T-2.2 ✅ Мок `POST /sms/send` принимает поля §C и отвечает `S1000`. Тела сохраняются в файл, `GET /sms` отдаёт список без `password` и `applicationId`. Повтор того же текста даёт вторую строку.
- T-2.3 ✅ Мок `POST /caas/direct/debit`. Код первого ответа — `MOCK_CHARGE_STATUS` (`S1000`, `E1326`, `E1308`, `E1337`), переключение в процессе: `POST /mock/charge-status`. Уже виденный `externalTrxId` всегда `E1337` и хранится в Postgres `mock_seen_external_trx`, поэтому рестарт мока не превращает повтор в новое списание. Успех отдаёт `externalTrxId`, `internalTrxId`, `referenceId`, `timeStamp`. Пароль в ответ не попадает.
- T-2.4 ✅ Мок `POST /mock/notifications` шлёт JSON §B (`applicationId`, `subscriberId`, `frequency`, `status`, `version`, `timeStamp`) одним HTTP POST. URL по умолчанию — `SERVICE_WEBHOOK_URL`. Повтор ручки шлёт событие снова. Недоступный приёмник даёт 502 и `E1000`, `/health` остаётся 200. Приёмник для демо и тестов: `POST /mock/inbox`, чтение `GET /mock/inbox`.
- T-3.1 ✅ `POST /subscribers` принимает `{"msisdn":"tel:…"}`. Невалидный id отвергается до Platform и не пишет строку. Уже ACTIVE — без Platform и без второго welcome (Q9). Новый: `action:"1"`, при `S1000` запись ACTIVE и welcome «Daily Pass is active». Таймаут Platform не делает ACTIVE и не шлёт welcome. CSRF на этой ручке выключен: контракт JSON/`curl`, не браузерная форма.
- T-3.2 ✅ `GET /subscribers/{subscriberId}` отдаёт локальный статус. После opt-in — `ACTIVE`. Неизвестный валидный id — 404 «не найден», без чужого состояния. Мусорный id — 400 до чтения БД. Platform не вызывается.
- T-3.3 ✅ `POST /jobs/daily-charge` списывает только ACTIVE. Ключ дня — UTC `externalTrxId`. На `S1000` пишется попытка и receipt с `referenceId` мока. Второй запуск в тот же день не создаёт второй успех и не шлёт debit с новым ключом. INACTIVE в выборку не входит. Таймаут не пишет попытку; повтор использует тот же ключ. Плата `DAILY_FEE_AMOUNT` / `DAILY_FEE_CURRENCY` из env.
- T-3.4 ✅ `E1326`: до 3 попыток, пауза 1 с / 2 с, тот же `externalTrxId`, без suspend и без receipt. Исчерпание оставляет ACTIVE, следующий job может списать тем же ключом. `E1308`: один debit, `SUSPENDED`, без retry и без нового ключа; следующий job этого абонента debit не вызывает. Таймаут ретраится тем же ключом.

## В работе / дальше
- Следующая задача этапа 3: T-3.5 обработка E1337 и E1318.

## Принятые решения и допущения
- Q1–Q13 приняты. Q6: только HTTP-эндпоинты, UI не делаем, сценарии — задокументированные `curl`. Пути: `POST /subscribers`, `GET /subscribers/{subscriberId}`, `POST /jobs/daily-charge`, `POST /webhooks/subscription`, `POST /webhooks/mo`, `GET /charges`. Остальные — варианты по умолчанию из `docs/spec/open-questions.md`: billing day = календарная дата UTC; плата `5.00` `XTS` из env; не больше 3 попыток с паузой 1/2/4 с и тем же `externalTrxId`; статусы `ACTIVE` / `SUSPENDED` / `INACTIVE`; webhook `REGISTERED`→`ACTIVE`, `UNREGISTERED`→`INACTIVE`, `PENDING`/`CHARGE` только запись; SMS-тексты и `sourceAddress` из Q7; MSISDN `^tel:[0-9]+$`; повторный ACTIVE opt-in без Platform и без второго welcome; ключ webhook = `subscriberId`+`status`+`timeStamp`+`frequency`; `E1337` без нового ключа; неизвестный и повторный STOP по Q12; мок — `MOCK_CHARGE_STATUS`, повтор ключа всегда `E1337`.
- A-DB: PostgreSQL 16 в compose. Локальные пароль БД и `COOKIE_VALIDATION_KEY` лежат в `docker-compose.yml`, чтобы подъём не требовал ручного `.env`. Это не секреты Platform.
- A-STACK: оба процесса — PHP 8.2 и Yii2. Lock сервиса зафиксирован на platform php 8.2.36, иначе Composer подтягивает пакеты для PHP 8.4.
- `PLATFORM_BASE_URL=http://platform-mock:8080` внутри сети compose. С хоста мок на `localhost:8081`. Локальные `PLATFORM_APPLICATION_ID`, `PLATFORM_PASSWORD` и `SMS_SOURCE_ADDRESS` заданы в `docker-compose.yml` (как пароль БД): иначе opt-in не стартует одной командой.
- PHPStan смотрит `src`, `config`, `migrations`, `public`, `bin` и наши unit/integration-тесты. Сгенерированные акторы Codeception в анализ не входят: их уровень 8 не проходит без baseline, а baseline без согласования запрещён.
- Dev-зависимости стоят в образе сервиса, чтобы `composer check` шёл внутри контейнера и на хосте не нужен PHP.

## Открытые вопросы
- Q1–Q13 закрыты. Тексты решений — в `docs/spec/open-questions.md`.

## Последний результат проверок
- `docker compose exec service composer check` (после пересборки образа service, T-3.4):
  - php-cs-fixer: 0 files to fix
  - phpstan: OK, no errors
  - codeception: OK (63 tests, 547 assertions) — 11 api, 5 integration, 12 mock, 35 unit
- T-0.1 `tests/integration/health.sh`: 200, затем 503 при остановленной БД, затем снова 200.
