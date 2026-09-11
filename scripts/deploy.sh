#!/bin/sh
# One-command production deploy for Inventory, Sales and Management System.
#
# Commit and `git push` your changes first, then run this from any machine that
# has SSH access to the droplet:
#
#     sh scripts/deploy.sh
#
# It pulls the latest main on the server, rebuilds the Docker image, runs any
# pending migrations, clears the view/config caches and prints the live release
# version so you can eyeball it, along with the disk figure.
#
# The prune step is not housekeeping-for-its-own-sake. `docker compose up -d
# --build` writes a fresh set of build-cache layers on EVERY deploy and never
# removes the old ones: by 2026-08-31 that had reached 37 GB across 834 entries,
# of which only 33 were in use, and had taken a 58 GB droplet to 73% full. A
# week of cache is kept so ordinary rebuilds stay fast, and it is wrapped in
# `|| true` because failing to tidy up must never fail a deploy.
# Nothing here is destructive — a failed pull or build leaves the running
# container untouched (the image only swaps in once the build succeeds).
#
# Override the target if the droplet ever moves:
#     DEPLOY_HOST=root@1.2.3.4 DEPLOY_PATH=/srv/app sh scripts/deploy.sh
set -eu

HOST="${DEPLOY_HOST:-root@your.server.ip}"
DIR="${DEPLOY_PATH:-/root/inventory-sales-management-system}"

echo "→ Deploying latest main to $HOST:$DIR"

ssh -o ConnectTimeout=15 -o ServerAliveInterval=20 "$HOST" "cd '$DIR' && \
  echo '— pull —'   && git pull --ff-only && \
  echo '— build —'  && docker compose up -d --build && \
  echo '— migrate —'&& docker compose exec -T app php artisan migrate --force && \
  echo '— caches —' && docker compose exec -T app php artisan view:clear && \
                       docker compose exec -T app php artisan config:clear && \
  echo '— prune —'  && { docker builder prune -f --filter until=168h || true; } && \
  echo '— disk —'   && df -h / | tail -1 && \
  echo '— status —' && docker compose ps && \
  echo '— release —'&& grep -E 'const (CURRENT_VERSION|TOTAL_COMMITS)' app/Support/ReleaseNotes.php"

echo "✓ Deploy complete — confirm https://example.com/about shows the new version"
