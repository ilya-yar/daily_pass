#!/usr/bin/env bash
# Format a PHP file with php-cs-fixer after an agent edit. Fail open.
set -u

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.file_path // .path // .filePath // .uri // empty')

if [[ -z "$file" || "$file" == "null" ]]; then
  exit 0
fi

if [[ "$file" == file://* ]]; then
  file="${file#file://}"
fi

case "$file" in
  *.php) ;;
  *) exit 0 ;;
esac

if [[ ! -f "$file" ]]; then
  echo "php-cs-fixer skipped, file not found: $file" >&2
  exit 0
fi

root=$(git rev-parse --show-toplevel 2>/dev/null || pwd)

if [[ -f /.dockerenv ]]; then
  if [[ -x "$root/vendor/bin/php-cs-fixer" ]]; then
    "$root/vendor/bin/php-cs-fixer" fix --path-mode=override "$file" >&2 || true
  elif [[ -x /app/vendor/bin/php-cs-fixer ]]; then
    /app/vendor/bin/php-cs-fixer fix --path-mode=override "$file" >&2 || true
  else
    echo "php-cs-fixer is not installed; skipped $file" >&2
  fi
  exit 0
fi

cd "$root"
if ! docker compose run --rm --no-deps -T \
  -v "$file:/tmp/php-cs-fixer-target.php" \
  -w /app \
  service \
  php /app/vendor/bin/php-cs-fixer fix --path-mode=override /tmp/php-cs-fixer-target.php >&2
then
  echo "php-cs-fixer in the service container failed; skipped $file" >&2
fi

exit 0
