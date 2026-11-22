#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
cd "$root"

request() {
  local url=$1
  docker compose exec -T -e HEALTH_URL="$url" service php -r '
    $url = getenv("HEALTH_URL");
    if (!is_string($url) || $url === "") {
        fwrite(STDERR, "HEALTH_URL is missing\n");
        exit(2);
    }
    $context = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 5]]);
    $body = file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match("/\s(\d{3})\s/", $http_response_header[0], $match) === 1) {
        $code = (int) $match[1];
    }
    fwrite(STDOUT, $code . "\n" . ($body === false ? "" : $body));
  '
}

assert_status() {
  local url=$1
  local expected_code=$2
  local expected_body=$3
  local output code body
  output=$(request "$url")
  code=$(printf '%s\n' "$output" | head -n 1)
  body=$(printf '%s\n' "$output" | tail -n +2)
  if [[ "$code" != "$expected_code" || "$body" != "$expected_body" ]]; then
    echo "health check failed for $url: expected ${expected_code} ${expected_body}, got ${code} ${body}" >&2
    return 1
  fi
}

assert_status "http://127.0.0.1:8080/health" "200" '{"status":"ok"}'
assert_status "http://platform-mock:8080/health" "200" '{"status":"ok"}'

docker compose stop db >/dev/null
trap 'docker compose start db >/dev/null' EXIT
sleep 1

assert_status "http://127.0.0.1:8080/health" "503" '{"status":"error"}'

docker compose start db >/dev/null

ready=0
for _ in $(seq 1 30); do
  if assert_status "http://127.0.0.1:8080/health" "200" '{"status":"ok"}'; then
    ready=1
    break
  fi
  sleep 1
done

if [[ "$ready" != "1" ]]; then
  echo "database did not become reachable again" >&2
  exit 1
fi
