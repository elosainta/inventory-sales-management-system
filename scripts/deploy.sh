#!/bin/sh
# One-command production deploy for Inventory, Sales and Management System.
#
# Commit and `git push` your changes first, then run this from any machine that
# has SSH access to the droplet:
#
#     sh scripts/deploy.sh
#
# It pulls the latest main on the server, rebuilds the Docker image, runs any
# pending migrations, clears the view/config caches, purges the brand files from
# Cloudflare (see the purge step) and prints the live release version so you can
# eyeball it, along with the disk figure.
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


# — purge the edge —
# Only the brand files. Everything under /build carries a content hash, so a
# changed file is a new URL and Cloudflare can never hold a stale copy of it.
# /favicon.ico and /images/* keep one name for life, which is how an empty
# favicon.ico from 27 June stayed pinned at the edge for 48 days behind a
# year-long max-age, with no way to shift it but a purge by hand.
#
# The token is read from the droplet's own .env and used there, so it never
# crosses to the machine running this script and never reaches this repo. Set
# CLOUDFLARE_API_TOKEN (scoped to Zone -> Cache Purge on this zone alone) and
# CLOUDFLARE_ZONE_ID in the server's .env to switch it on; without them the
# step says so and moves on. Wrapped in `|| true` for the same reason the
# prune is: a CDN that will not answer must never fail a deploy that has
# already succeeded.
ssh -o ConnectTimeout=15 "$HOST" "sh -s '$DIR'" <<'PURGE' || true
set -eu
cd "$1"

# Read one value out of .env, with parameter expansion rather than another
# layer of quoting inside a heredoc inside a shell string.
#
# A quoted value is taken verbatim; an unquoted one ends at the first space,
# which is how dotenv itself reads them and what makes a trailing comment or a
# stray space harmless. Taking the whole rest of the line is what made the
# Cloudflare token arrive 53 characters long against the 40 Cloudflare issues,
# for an "Invalid API Token" that looked for all the world like a revoked one.
# A \r from an edit on Windows is invisible in output and counts toward the
# length, so it goes first.
#
# ponytail: the unquoted branch would also cut a value that legitimately
# contains a space. None of the three read here can — quote it if that changes.
read_env() {
  v=$(sed -n "s/^$1=//p" .env | head -1 | tr -d '\r')
  case $v in
    \"*\"*) v=${v#\"}; v=${v%%\"*} ;;
    \'*\'*) v=${v#\'}; v=${v%%\'*} ;;
    *)      v=${v%% *} ;;
  esac
  printf '%s' "$v"
}

token=$(read_env CLOUDFLARE_API_TOKEN)
zone=$(read_env CLOUDFLARE_ZONE_ID)
base=$(read_env APP_URL)
base=${base%/}

if [ -z "$token" ] || [ -z "$zone" ]; then
  echo "— purge —  skipped: no CLOUDFLARE_API_TOKEN/CLOUDFLARE_ZONE_ID in .env"
  exit 0
fi

echo "— purge —"
files=""
for f in /favicon.ico /images/app-icon.png /images/app-wordmark.png /images/app-wordmark-light.png; do
  [ -n "$files" ] && files="$files,"
  files="$files\"$base$f\""
done

response=$(curl -sS -X POST "https://api.cloudflare.com/client/v4/zones/$zone/purge_cache" \
     -H "Authorization: Bearer $token" \
     -H "Content-Type: application/json" \
     --data "{\"files\":[$files]}" 2>&1 || true)

if printf '%s' "$response" | grep -q '"success":true'; then
  echo "  brand files purged — the favicon and logos refetch on next request"
else
  # Say what went wrong, not just that something did. A bare "failed" sent
  # someone hunting through the Cloudflare dashboard for a token problem the
  # API had already spelled out in its reply. The reply names no secret —
  # the token is in the request, never the response.
  echo "  purge failed — the deploy is fine, but the edge may still serve an old logo"
  printf '  cloudflare said: %s\n' "$response"

  # Which kind of failure is it? A dead token and a live token that has lost
  # Cache Purge on this zone both answer 10000 on a purge, and they are not
  # fixed the same way. This asks the token about itself: "active" with an
  # expiry in the future means the token is fine and the permission is the
  # problem, anything else means it needs replacing.
  #
  # Only reached when the purge has already failed, so a good deploy still
  # makes one API call. The reply carries the token's id, status and dates —
  # never its value, which travels only in the request header.
  check=$(curl -sS "https://api.cloudflare.com/client/v4/user/tokens/verify" \
       -H "Authorization: Bearer $token" 2>&1 || true)
  printf '  token check:     %s\n' "$check"

  # "Invalid API Token" covers a revoked token AND one that merely arrived
  # malformed — a value truncated on its way into .env, or a quote the reader
  # above did not strip. The count tells those apart without disclosing
  # anything: a Cloudflare token is 40 characters, and a length is not a
  # secret. Never print the value itself.
  printf '  token length:    %s chars (Cloudflare issues 40)\n' "${#token}"
fi
PURGE

echo "✓ Deploy complete — confirm https://example.com/about shows the new version"
