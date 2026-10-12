#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/apps/app
bun install --frozen-lockfile
exec bun run dev
