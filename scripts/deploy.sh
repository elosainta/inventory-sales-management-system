#!/bin/sh
# One-command production deploy for Inventory, Sales and Management System, from a dev machine.
#
# Commit and `git push` your changes first, then run this from any machine that
# has SSH access to the droplet:
#
#     sh scripts/deploy.sh
#
# It pulls the latest main on the server and runs scripts/server/deploy-here.sh
# there — the same steps GitHub Actions runs when a release is pushed (see
# .github/workflows/deploy.yml), so there is one deploy, not two that drift.
# Pulling first means the server always runs the newest copy of those steps.
#
# Override the target if the droplet ever moves:
#     DEPLOY_HOST=root@1.2.3.4 DEPLOY_PATH=/srv/app sh scripts/deploy.sh
set -eu

HOST="${DEPLOY_HOST:-root@your.server.ip}"
DIR="${DEPLOY_PATH:-/root/inventory-sales-management-system}"

echo "→ Deploying latest main to $HOST:$DIR"

ssh -o ConnectTimeout=15 -o ServerAliveInterval=20 "$HOST" \
  "cd '$DIR' && echo '— pull —' && git pull --ff-only && sh scripts/server/deploy-here.sh"
