# Daily Pass

Paid daily subscription against a local Carrier Platform mock. Stack: PHP 8.2, Yii2, PostgreSQL 16. One Compose file starts `service`, `platform-mock`, and the database.

Platform credentials (`PLATFORM_APPLICATION_ID`, `PLATFORM_PASSWORD`) and other demo values are environment variables in `docker-compose.yml`. They are local placeholders, not production secrets. Do not put a live password in this file or in curl bodies — the service HTTP API does not accept the Platform password.

## Run

From the repository root, with Docker installed:

```bash
docker compose up
```

Leave that process running. The first start builds images and applies migrations. When it is ready:

- service: `http://localhost:8080`
- mock: `http://localhost:8081`

```bash
curl -sS http://localhost:8080/health
```

Expect `{"status":"ok"}`.

The commands below assume a **fresh** stack (empty database, mock charge status `S1000`). Use a new terminal. Replace `YYYY-MM-DD` with today's UTC calendar date (`date -u +%F`).

## Opt-in

```bash
curl -sS -X POST http://localhost:8080/subscribers \
  -H 'Content-Type: application/json' \
  -d '{"msisdn":"tel:1000000001"}'
```

Expect `"status":"ACTIVE"`. Welcome SMS in the mock:

```bash
curl -sS http://localhost:8081/sms
```

The list contains `"message":"Daily Pass is active"` for `tel:1000000001` and does not contain `password`.

## Daily charge success

Mock already answers `S1000` on a new debit key:

```bash
curl -sS -X POST http://localhost:8080/jobs/daily-charge
```

On a fresh stack this charges the one ACTIVE subscriber (`"charged":1`). If the database already has other ACTIVE rows, confirm this MSISDN with `GET /charges`, not the job totals. Receipt text is `Charged. Reference: {referenceId}` in `GET http://localhost:8081/sms`. The same reference:

```bash
curl -sS "http://localhost:8080/charges?msisdn=tel:1000000001&date=YYYY-MM-DD"
```

Expect `"charged":true` and that `referenceId`. Run the job again the same UTC day:

```bash
curl -sS -X POST http://localhost:8080/jobs/daily-charge
```

`GET /charges` for `tel:1000000001` still shows one `referenceId`. The mock keeps the same `externalTrxId` as a duplicate (`E1337`); a second success is not stored.

## Insufficient balance

Use a **new** subscriber so today's key is unused. Point the mock at `E1326` (T-2.3):

```bash
curl -sS -X POST http://localhost:8081/mock/charge-status \
  -H 'Content-Type: application/json' \
  -d '{"statusCode":"E1326"}'

curl -sS -X POST http://localhost:8080/subscribers \
  -H 'Content-Type: application/json' \
  -d '{"msisdn":"tel:1000000002"}'

curl -sS -X POST http://localhost:8080/jobs/daily-charge
```

The job reuses the same day key and does not store a success. The mock remembers that key (Q13), so a later try in the same run is `E1337`, not a second debit. Then:

```bash
curl -sS http://localhost:8080/subscribers/tel:1000000002
curl -sS "http://localhost:8080/charges?msisdn=tel:1000000002&date=YYYY-MM-DD"
```

Expect `"status":"ACTIVE"` and `"charged":false`. Restore success for the remaining scenarios:

```bash
curl -sS -X POST http://localhost:8081/mock/charge-status \
  -H 'Content-Type: application/json' \
  -d '{"statusCode":"S1000"}'
```

## Duplicate webhook

The mock pushes §B to the service (T-2.4). Repeat the same event twice (Q10: `subscriberId` + `status` + `timeStamp` + `frequency`):

```bash
curl -sS -X POST http://localhost:8081/mock/notifications \
  -H 'Content-Type: application/json' \
  -d '{"subscriberId":"tel:1000000003","status":"REGISTERED","frequency":"DAILY","timeStamp":"2026-11-24T12:00:00.000+00:00"}'

curl -sS -X POST http://localhost:8081/mock/notifications \
  -H 'Content-Type: application/json' \
  -d '{"subscriberId":"tel:1000000003","status":"REGISTERED","frequency":"DAILY","timeStamp":"2026-11-24T12:00:00.000+00:00"}'

curl -sS http://localhost:8080/subscribers/tel:1000000003
curl -sS "http://localhost:8080/charges?msisdn=tel:1000000003&date=YYYY-MM-DD"
```

Both pushes return `"statusCode":"S1000"`. One ACTIVE subscriber, `"charged":false` until you run the job. The webhook does not call Direct Debit.

## Opt-out

```bash
curl -sS -X POST http://localhost:8080/subscribers \
  -H 'Content-Type: application/json' \
  -d '{"msisdn":"tel:1000000004"}'

curl -sS -X POST http://localhost:8080/webhooks/mo \
  -H 'Content-Type: application/json' \
  -d '{"applicationId":"APP_000123","sourceAddress":"tel:1000000004","message":"STOP","encoding":"0","version":"1.0"}'

curl -sS http://localhost:8080/subscribers/tel:1000000004
curl -sS http://localhost:8081/sms
```

Expect `"status":"INACTIVE"` and goodbye `"Daily Pass is stopped"`. Then:

```bash
curl -sS -X POST http://localhost:8080/jobs/daily-charge
curl -sS "http://localhost:8080/charges?msisdn=tel:1000000004&date=YYYY-MM-DD"
```

Expect `"charged":false` for `tel:1000000004`. The job may still charge other ACTIVE subscribers from earlier steps; this MSISDN stays uncharged.

`applicationId` in the MO body is the mock application id from env (`MOCK_APPLICATION_ID` / `PLATFORM_APPLICATION_ID` in Compose), not a password.
