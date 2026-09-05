# Inventory, Sales and Management System — Engineering Vault

Everything about how this system is built, wired and run. Restaurant kitchen management for a single kitchen, solving the Owner's financial-visibility problem: wastage, over-ordering and untracked stock.

New here? Read [[How to use this vault]] first.

---

## Start

- [[How to use this vault]] — the conventions, and which notes are hand-written vs machine-written
- [[Glossary]] — plain-language meaning of every term used in the code
- [[Vault automation]] — how this vault rebuilds itself on every commit

## Architecture

- [[Architecture overview]] — the shape of the whole thing in one page
- [[Request lifecycle]] — what happens between a click and a rendered page
- [[Layering rules]] — where code is allowed to live, and why
- [[Middleware stack]] — the four custom middleware and their order

## Code paths

Each note follows one action end to end, file by file, with the reasoning at every hop.

- [[Code paths index]] — the map
- [[Path — Logging a sale]] · [[Path — Logging a purchase]] · [[Path — Logging wastage]]
- [[Path — Logging production]] · [[Path — Saving a recipe]]
- [[Path — Recording a tally]] · [[Path — Recording a stock-take]]
- [[Path — Rendering the dashboard]]

## The mathematics

Every number the system shows, derived and explained.

- [[Formulas index]] — all formulas in one table
- Money: [[Sale revenue]] · [[Plate cost]] · [[Cost of goods sold]] · [[Gross margin]] · [[Petty cash balance]]
- Stock: [[Inventory monetary value]] · [[Low stock threshold]] · [[Tally variance]] · [[Stock-take movement]]
- Loss: [[Wastage cost and rate]]
- People: [[Feedback averages]]
- Foundations: [[Rounding and money]]

## Data

- [[Database overview]] — connections, schema families, conventions
- [[Inventory as shared state]] — the one table nearly everything writes to
- [[Audit trail]] — how every financial change is recorded
- [[Models index]] — generated
- [[Migrations index]] — generated

## Security

- [[Roles]] — who is who
- [[Authorization gates]] — the single mechanism controlling access
- [[Demo sandbox]] — physically isolated training accounts
- [[Gates matrix]] — generated

## Infrastructure

- [[VPS and hosting]] — the droplet, Docker, Cloudflare, TLS
- [[Deployment]] — how a commit becomes production
- [[Backups]] — the 5-minute pull-down loop
- [[Environment variables]] — every setting that matters
- [[Local development]] — running it on your own machine

## Generated reference

Rebuilt automatically on every commit — never edit these.

- [[Routes]] · [[Gates matrix]] · [[Models index]] · [[Migrations index]] · [[Domain actions index]] · [[Repo snapshot]]
