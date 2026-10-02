# Offline Windows demo

`ISMS.exe` is the whole system in one file, for trying it on a Windows PC with
no server, no installs and no internet. Download it from the
[Releases](../../releases) page.

## Using it

1. Double-click `ISMS.exe`. Windows SmartScreen may warn because the file is
   not code-signed: choose **More info → Run anyway**.
2. The first launch unpacks to `%LOCALAPPDATA%\ISMS` and creates eight weeks of
   sample data ending today (about 30 seconds). Later launches take a second.
3. Your browser opens on the sign-in page. Pick a role under
   **Try a demo account** to sign in as it - each role sees a different menu.
4. Close the black console window to stop. To start over with fresh sample
   data, delete `%LOCALAPPDATA%\ISMS`.

| Role | Email | Password |
|------|-------|----------|
| Owner | `owner@example.test` | `password` |
| Head Chef | `sam@example.test` | `password` |
| Junior Chef | `chef1@example.test` (also `chef2`, `chef3`) | `password` |
| Part timer | `parttimer@example.test` | `password` |
| Admin | `admin@example.test` | `password` |

Everything runs on the PC; nothing is sent anywhere. Invoice Scan and the
accounting-system link need API keys and the internet, so they show
"Not configured" here.

Requires 64-bit Windows 10 or 11.

## How it is built

```bash
bash portable/build.sh     # writes portable/dist/ISMS.exe
```

`build.sh` takes the committed app, adds `SampleDataSeeder` and the sign-in
page's demo-account buttons, and makes it fully offline: Chart.js and the
Google Fonts are downloaded into the bundle, and the build fails if any page
still loads from another host. It switches the app to SQLite, adds PHP with the
Visual C++ runtime DLLs it needs (Windows does not ship them), and embeds all
of it in `Launcher.cs`, compiled with the `csc.exe` that comes with Windows'
.NET Framework 4 - so the result needs nothing installed.

The launcher unpacks on first run, creates the database, starts PHP's built-in
server from `app/public` (Laravel's router script takes the document root from
the working directory) and opens the browser. PHP shares its console, so
closing the window stops both.
