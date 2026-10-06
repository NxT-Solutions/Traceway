#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/apps/app
npm install
exec npm run dev
