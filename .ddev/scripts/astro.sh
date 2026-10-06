#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/apps/web
npm install
exec npm run dev
