# Обзор
- Источник в ТЗ: строки 1–33, 87–88, 127–132
- Зависит от: —
- Используется в: все модули
- Статус: draft

## Назначение

**Daily Pass** — платная ежедневная подписка поверх мобильной Carrier Platform. Абонент подключается, с мобильного счёта списывается фиксированная дневная плата, он получает SMS-чек и может остановить подписку, отправив `STOP`.

Оценивают не объём AI-кода, а корректность, безопасность денег и понимание результата. Узкий доказуемо верный срез лучше широкого, который списывает дважды (строки 5–13).

## Что входит

- REQ-OVW-001: Build "Daily Pass" — a paid daily subscription on a mobile Carrier Platform. A subscriber opts in, is charged a fixed daily fee from their mobile account, gets an SMS receipt, and can stop any time by texting `STOP`.
- REQ-OVW-002: Two runnable pieces: `service/` (the subscription service) and `platform-mock/` (a small mock of the Carrier Platform).
- REQ-OVW-003: The whole thing must come up with `docker compose up` — a single command, no manual steps.
- REQ-OVW-004: It must run cleanly on a fresh machine with only Docker installed (no local runtime, no "install X first").
- REQ-OVW-005: Expose a way to drive the scenarios (a tiny UI, a CLI, or documented `curl`s).
- REQ-MOCK-001: The mock implements the Platform endpoints in the appendix so the flow runs locally with no real operator. См. `modules/platform-mock.md`.
- REQ-MOCK-005: Base URL is a placeholder (`https://api.carrier.example`); point it at the mock.

## Что явно не входит

- REQ-OVW-006: Out of scope (say so, don't build): real auth/SSO, multi-currency, USSD, DLR handling, Query Balance, production infra, pixel-perfect UI.

## Критерий провала

- REQ-OVW-007: Hard gate: if `docker compose up` does not bring the whole thing up on a clean machine, the submission is not graded further. "It worked on my machine" is a fail.
- REQ-OVW-008: A submission that double-charges under a duplicate webhook or a job re-run fails — regardless of polish.

## Роли

Отдельных ролей с разными правами в ТЗ нет. «A finance person must be able to answer…» (строка 85) — требование к запросу данных, не RBAC. См. `03-cross-cutting.md`.

## Контекст, не являющийся требованием к коду

Строки 1–4: роль исполнителя — backend / full-stack; AI-assisted coding ожидается. Строки 5–8: на коротком созвоне нужно уметь объяснить свой код. Это не функции системы.
