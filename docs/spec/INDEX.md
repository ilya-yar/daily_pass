# Карта спецификации

Исходник: `ASSIGNMENT_SHORT.md`, 199 строк. Ни один файл ниже не заменяет исходник. Статус всех файлов: `draft`.

| Файл | Суть | Ключевые сущности | Зависит от | Строки ТЗ |
|---|---|---|---|---|
| `00-overview.md` | Продукт, два процесса, docker, out of scope, hard gate | Daily Pass, service, mock | — | 1–33, 87–88, 127–132 |
| `01-glossary.md` | Термины предметной области | MSISDN, subscriberId, externalTrxId | — | по всему файлу |
| `02-domain-model.md` | Подписчик, попытка списания, уведомление, SMS | Subscriber, Charge attempt | glossary | 39–67, 83–85, 156–162 |
| `03-cross-cutting.md` | Секреты, валидация, идемпотентность, аудит, транспорт | applicationId, billing day | glossary | 71–88, 136–143 |
| `04-integrations.md` | Общий контракт Platform и указатели на §A–§E | Platform | cross-cutting | 136–143 |
| `05-acceptance.md` | Сдача, чеклист, рубрика 100 баллов | — | все модули | 92–132 |
| `modules/opt-in.md` | F1 и §A action `"1"` | Subscriber ACTIVE | sms, mock | 39–43, 106, 145–154 |
| `modules/subscription-webhook.md` | F2 и §B | Notification | cross-cutting | 45–49, 109, 156–162 |
| `modules/charging.md` | F3, §D, §E | Charge attempt, externalTrxId | opt-in, opt-out, sms | 51–58, 73–77, 171–199 |
| `modules/opt-out.md` | F4, MO `STOP`, §A action `"0"` | Subscriber INACTIVE | sms, charging | 60–64, 111, 167–169 |
| `modules/status.md` | F5 текущее состояние | Subscriber | domain | 66–67 |
| `modules/sms.md` | MT `POST /sms/send` и MO endpoint | SMS | mock | 164–169 |
| `modules/platform-mock.md` | Мок, конфигурируемый код списания, push callback | Mock charge, stored MT | контракты §A–§E | 25–28, 106 |
| `open-questions.md` | Вопросы Q1–Q13 и допущения | — | — | — |
| `_coverage.md` | Строка ТЗ → файл и REQ | — | — | 1–199 |
| `_analysis-notes.md` | Рабочие заметки по порциям | — | — | 1–199 |

Чтение при реализации задачи: файл модуля из задачи + при необходимости `01-glossary.md` и `03-cross-cutting.md`. Весь исходник целиком не подгружать.
