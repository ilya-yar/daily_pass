# Этап 0. Каркас

Цель: на чистой машине с одним Docker `docker compose up` поднимает оба процесса и БД. Бизнес-потоков ещё нет.

### T-0.1: Compose поднимает service, mock и БД
- Модуль: overview, platform-mock
- Требования: REQ-OVW-002, REQ-OVW-003, REQ-OVW-004, REQ-OVW-007, REQ-MOCK-005
- Файлы ТЗ для контекста: `docs/spec/00-overview.md`, `docs/spec/modules/platform-mock.md`
- Зависит от: —
- Затрагиваемые слои: Infrastructure / Presentation
- Размер: M
- Что сделать:
  - Каталоги `service/` и `platform-mock/` с Dockerfile.
  - `docker-compose.yml`: оба контейнера и PostgreSQL (A-DB).
  - Base URL Platform в env сервиса указывает на мок (REQ-MOCK-005).
  - У обоих процессов HTTP `/health`, который проверяет, что процесс жив. У сервиса health дополнительно проверяет БД, когда она подключена.
- Критерии приёмки:
  - `docker compose up` без других команд поднимает три контейнера.
  - С хоста доступны health сервиса и мока.
  - В образе есть PHP; на хосте PHP ставить не нужно.
- Тесты: integration — health сервиса отвечает успехом, когда БД доступна, и ошибкой, когда нет.
- Сценарии сбоев: БД не поднялась → health сервиса не врёт, что всё хорошо. Повторный `docker compose up` не требует ручной инициализации.
- Статус: done

### T-0.2: Инструменты проверки сервиса внутри репозитория
- Модуль: overview, acceptance
- Требования: REQ-OVW-004, REQ-TEST-001, REQ-DOC-003
- Файлы ТЗ для контекста: `docs/spec/00-overview.md`, `docs/spec/05-acceptance.md`
- Зависит от: T-0.1
- Затрагиваемые слои: Infrastructure
- Размер: M
- Что сделать:
  - В `service/`: Composer, `declare(strict_types=1)` для новых PHP-файлов, скрипты `cs:fix`, `cs:check`, `stan`, `test`, `check`.
  - Codeception (unit / integration / api) и PHPStan уровня не ниже 8.
  - Конфиг только из env. Pre-commit hook гоняет `composer check` там, где есть PHP-проект сервиса.
  - Каркас слоёв `src/` без бизнес-логики: Domain, Application, Infrastructure, Presentation.
- Критерии приёмки:
  - `composer check` в контейнере или через compose exec завершается успехом на пустом каркасе.
  - Секреты не появляются в репозитории.
- Тесты: unit — один дымовой тест каркаса, чтобы `composer test` имел что запускать.
- Сценарии сбоев: нет. Задача не ходит во внешние API.
- Статус: todo
