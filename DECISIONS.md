# Decisions

## `externalTrxId`

One logical charge per subscriber per UTC calendar day (Q1). The value is:

`{Y-m-d in UTC}:{sha256 of the full subscriberId string}`

The attempt number is not part of the key. Platform secrets are not part of the key. Time comes from an injected `Clock`, not from wall-clock calls scattered in the job.

A retry, a second job run, and a process restart **reuse that same string**. Platform treats `externalTrxId` as the debit idempotency key (`E1337`). Minting a new key would be a second charge. The local unique index on `charge_attempt.external_trx_id` is the same rule on our side. `subscriberId` is hashed only so the column stays within `varchar(128)`; it is not parsed as a phone number (Q8).

## Not built (REQ-OVW-006)

Real auth/SSO, multi-currency, USSD, DLR handling, Query Balance, production infrastructure, and a pixel-perfect UI were not built.

## Not built (post-MVP)

No cron: the daily job is `POST /jobs/daily-charge` (A-MANUAL-JOB). No return from `SUSPENDED` to `ACTIVE`. Webhook `PENDING` / `CHARGE` are stored and answered `S1000` only (Q5). The mock is not required to return `E1318`; the service still classifies that code (A-MOCK-E1318). Scenarios are documented `curl`s, not a CLI or tiny UI (Q6).

## Known gaps

Demo Platform credentials and the DB password live in `docker-compose.yml` as Compose env so `docker compose up` needs no extra file. They are local placeholders, not production secrets.

The mock persists every first debit key (including `E1326`) and then always answers `E1337` for that key, including after a mock restart (`mock_seen_external_trx`). Against this mock, an in-job `E1326` retry does not see a second `E1326`. Three `E1326` attempts with 1s/2s waits are covered in unit tests on a fake that does not record keys. Do not read this file as a claim that `composer check` is green right now; the last recorded run is in `docs/PROGRESS.md`.
