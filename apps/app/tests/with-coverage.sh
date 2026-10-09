#!/bin/bash

# Pest's parallel workers start a fresh PHP binary and do not keep -d flags.
# This wrapper is that binary, so TIA and mutation collect coverage in every worker.
set -euo pipefail

cd "$(dirname "$0")/.."

directory="$(mktemp -d)"
trap 'rm -rf "$directory"' EXIT

cat > "$directory/php" <<'EOF'
#!/bin/sh
exec /usr/bin/php -d pcov.enabled=1 -d pcov.directory=/var/www/html/apps/app/app "$@"
EOF
chmod +x "$directory/php"

export PHP_BINARY="$directory/php"

/usr/bin/php -d pcov.enabled=1 -d pcov.directory=/var/www/html/apps/app/app "$@"
