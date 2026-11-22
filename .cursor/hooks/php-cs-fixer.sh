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
fixer=""
if [[ -x "$root/vendor/bin/php-cs-fixer" ]]; then
  fixer="$root/vendor/bin/php-cs-fixer"
elif command -v php-cs-fixer >/dev/null 2>&1; then
  fixer=$(command -v php-cs-fixer)
else
  echo "php-cs-fixer is not installed; skipped $file" >&2
  exit 0
fi

"$fixer" fix --path-mode=override "$file" >&2
exit 0
