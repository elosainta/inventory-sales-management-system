#!/usr/bin/env bash
# Build the offline Windows demo as ONE file: portable/dist/ISMS.exe, with PHP
# and this app embedded in it. See portable/README.md.
#
#   bash portable/build.sh
#
# Needs, on a Windows machine (Git Bash): `composer install` and a built
# public/build (`pnpm run build`) in this checkout; PHP 8.5 NTS x64 at C:\php
# (or set PHPDIR); and the Visual C++ runtime in System32, which is there on
# any machine that runs that PHP.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
OUT="$HERE/dist"
STAGE="$OUT/stage"
APP="$STAGE/app"
PHPDIR="${PHPDIR:-/c/php}"
CHARTJS=https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js
# Google serves woff2 only to a browser it recognises.
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'

[ -d "$REPO/vendor" ] && [ -d "$REPO/public/build" ] \
  || { echo "run composer install and pnpm run build first"; exit 1; }
[ -z "$(git -C "$REPO" status --porcelain --untracked-files=no)" ] \
  || { echo "commit your changes first - the demo is built from HEAD"; exit 1; }

rm -rf "$OUT"
mkdir -p "$APP"

# The app: the committed tree, plus the gitignored installs it needs to run.
git -C "$REPO" archive HEAD | tar -x -C "$APP"
cp -r "$REPO/vendor" "$APP/vendor"
cp -r "$REPO/public/build" "$APP/public/build"
rm -rf "$APP/tests" "$APP/docs" "$APP/scripts" "$APP/portable" "$APP/.github" \
       "$APP/docker-compose.yml" "$APP/Dockerfile" "$APP/docker"
cp "$HERE/SampleDataSeeder.php" "$APP/database/seeders/"

# The demo accounts, one button per role, under the sign-in form.
VIEWS="$APP/resources/views"
cp "$HERE/demo-accounts.blade.php" "$VIEWS/partials/"
perl -0 -i -pe 's{(\n            </form>\n)}{$1\n            \@include(\x27partials.demo-accounts\x27)\n}' "$VIEWS/auth/login.blade.php"
grep -q "partials.demo-accounts" "$VIEWS/auth/login.blade.php" \
  || { echo "the sign-in form moved - update the demo-accounts include above"; exit 1; }

# Offline, part 1: the dashboard's charts come from a CDN in the real app.
mkdir -p "$APP/public/vendor"
curl -fsSL "$CHARTJS" -o "$APP/public/vendor/chart.umd.min.js"
sed -i "s#https://cdn.jsdelivr.net/npm/chart.js#{{ asset('vendor/chart.umd.min.js') }}#" \
  "$VIEWS/dashboard.blade.php"

# Offline, part 2: the Google Fonts stylesheets, and every font file they name.
mkdir -p "$APP/public/fonts"
n=0
for url in $(grep -rhoE 'https://fonts\.googleapis\.com/css2\?[^"]+' "$VIEWS" | sort -u); do
  n=$((n + 1))
  css=$(curl -fsSL -A "$UA" "$url")
  for font in $(grep -oE 'https://fonts\.gstatic\.com/[^)]+' <<<"$css" | sort -u); do
    file=$(basename "$font")
    curl -fsSL "$font" -o "$APP/public/fonts/$file"
    css=${css//"$font"/$file}
  done
  printf '%s\n' "$css" > "$APP/public/fonts/google-$n.css"
  URL="$url" ASSET="{{ asset('fonts/google-$n.css') }}" \
    perl -i -pe 's/\Q$ENV{URL}\E/$ENV{ASSET}/g' $(grep -rlF "$url" "$VIEWS")
done
perl -i -ne 'print unless m{rel="preconnect" href="https://fonts\.g}' $(grep -rl 'rel="preconnect"' "$VIEWS")

# Nothing the browser loads may come from outside the machine.
if grep -rnoE '(src|href)="https?://[^"]+"' "$VIEWS" | grep -v 'w3\.org'; then
  echo "an external URL is still in a view - make it local above"; exit 1
fi

KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
# SESSION_COOKIE is pinned because the default is derived from APP_NAME, and
# the comma in this one makes a cookie the browser never sends back (every
# sign-in would be a 419).
cat > "$APP/.env" <<ENV
APP_NAME="Inventory, Sales and Management System"
APP_ENV=local
APP_KEY=$KEY
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Kuala_Lumpur
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=sqlite
SESSION_DRIVER=file
SESSION_COOKIE=isms_session
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
FILESYSTEM_DISK=local
ENV

# PHP: the runtime only, none of the dev headers or debuggers - plus the
# Visual C++ runtime it is built against, which Windows does not ship.
# App-local copies are what Microsoft's redistribution terms provide for.
mkdir -p "$STAGE/php"
( cd "$PHPDIR" && cp -r ext *.dll php.exe license.txt "$STAGE/php/" )
cp /c/Windows/System32/{vcruntime140.dll,vcruntime140_1.dll,msvcp140.dll} "$STAGE/php/"
{ cat "$PHPDIR/php.ini"; printf '\n; portable bundle\nmemory_limit=512M\nmax_execution_time=180\nupload_max_filesize=128M\npost_max_size=130M\n'; } > "$STAGE/php/php.ini"

# One exe: the staged folder rides inside it as a zip resource. The build id
# names the folder it unpacks to, so a new build never runs an old unpack.
( cd "$STAGE" && /c/Windows/System32/tar.exe -a -cf ../payload.zip php app )
printf 'static class BuildInfo { public const string Id = "%s"; }\n' "$(date +%Y%m%d%H%M%S)" > "$OUT/BuildInfo.cs"
FW=/c/Windows/Microsoft.NET/Framework64/v4.0.30319
"$FW/csc.exe" -nologo -optimize \
  -r:"$(cygpath -w "$FW/System.IO.Compression.dll")" -r:"$(cygpath -w "$FW/System.IO.Compression.FileSystem.dll")" \
  -resource:"$(cygpath -w "$OUT/payload.zip")",payload.zip \
  -out:"$(cygpath -w "$OUT/ISMS.exe")" "$(cygpath -w "$HERE/Launcher.cs")" "$(cygpath -w "$OUT/BuildInfo.cs")"

rm -rf "$STAGE" "$OUT/payload.zip" "$OUT/BuildInfo.cs"
ls -la "$OUT"
