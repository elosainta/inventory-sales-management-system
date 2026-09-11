# Path — Scanning an invoice

BETA. Photograph a supplier invoice, check what was read off it, send it to Bukku as a purchase bill. **Web in, Bukku, web out** — this replaced a Telegram round trip.

This path is unlike every other one in [[Code paths index]]: it is the only operation that **writes outside this system**, into the company's real accounting books. Since 2026-09-03 it also writes *inside* it — a filed invoice records a Purchase and moves stock, which is what stopped every delivery being typed twice.

---

## Trigger

| | |
|---|---|
| Routes | `POST /invoice-scan` (read), `POST /invoice-scan/{scan}/push` (write) |
| Gate | `use-invoice-scan` → `isManager()` — Owner and Head Chef |
| Form requests | `StoreInvoiceScanRequest`, `PushInvoiceScanRequest` |
| Throttle | `10,1` on the scan, `20,1` on the push |
| Model | `InvoiceScan` — audited via `LogsActivity`, `user_id` is `nullOnDelete` |

Admin reaches this like every other feature (the blanket grant in [[Authorization gates]]), because the safety here does not rest on who may open the page — it rests on the split below.

---

## The line

### Part one — reading (`ScanInvoice`)

1. `InvoiceScanController@store` authorizes, then hands the upload to `ScanInvoice::execute()`.
2. The file is stored **first**, and an `invoice_scans` row is created **before** the model is called. A failed read must still leave the photo and a row to work from, or the reviewer has to start over.
3. `set_time_limit(180)` — php-fpm's 30s default is shorter than a careful read of a busy invoice.
4. One POST to the Anthropic Messages API via Laravel's `Http`. No SDK: this machine has no composer, so a new dependency could not get a matching `composer.lock` entry, and the Docker build installs against that lock.
5. `output_config.format` is a **json_schema**, so the reply is parseable rather than prose. `effort: medium` because a manager is watching a spinner.
6. A refusal arrives as a **200 with no usable content**, so `stop_reason === 'refusal'` is checked before the content blocks are read.
7. `clean()` drops lines with neither a description nor an amount — a blank row on the page would otherwise be a blank row the reviewer deletes by hand.
8. On any throwable: `report()`, and the row is marked `failed` with the message. Never an exception page.

### Part two — reviewing (human), and teaching

The review screen renders the photo beside the extracted lines. It is the whole safety story of this feature: **the model is allowed to be wrong, because a person checks before a bill exists.**

Every line is a row in a table:

- **Tick** bills the line. **Untick** rejects it — the row dims and stops counting toward the total, but stays visible, because a reviewer needs to see what they chose to reject.
- **Your inventory item** is a search box over this kitchen's shelf, using the same `partials/item-picker` datalist as Purchases. Matching a row once teaches `invoice_item_aliases`, and the next invoice using that wording arrives already matched.
- **Stock product (Bukku)** is a different question and stays separate: it drives the *account the bill posts to*. Inventory and Bukku's catalogue are unlinked (254 items vs 77 products, no foreign key between them).

The dictionary is keyed on a **normalised** form — lowercased, punctuation stripped, spaces collapsed — so `AYAM PEHA 1KG`, `Ayam Peha (1kg)` and `ayam  peha 1kg` are one entry rather than three. `InvoiceItemAlias::matchAll()` resolves the whole invoice in one query; `remember()` is an `updateOrCreate` on that key, so a **correction wins** over whatever was learned first.

Learning happens **before** the bill is sent. What a supplier's wording means is the reviewer's judgement about naming — it is true whether or not Bukku accepts the bill a moment later, and making someone re-match twenty lines because of an outage elsewhere is how a feature stops being used.

### Part three — writing (`PushInvoiceToBukku`)

9. `execute()` refuses outright if `$scan->isPosted()`. Bukku bills are **voided, not deleted**, so a double-tapped Submit is expensive to unpick.
10. Line amounts are computed with **bcmath**, not float — the same rule as [[Rounding and money]]. This figure is what a supplier gets paid.
11. For a line the reviewer mapped to a Bukku product, `applyProduct()` fetches `GET /products/{id}` and takes the account **off the product itself** — `inventory_account_id` when it is stock-tracked, else `purchase_account_id` — plus the purchase-default unit and the stock location.
12. The photo is uploaded to Bukku so the bill carries its own evidence. A failure here is logged and ignored: the numbers matter more than the picture, and the picture is still on this server.
13. `send()` POSTs the bill **once**, with the recovery logic described under *Traps*.
14. On success the scan is updated with `bukku_transaction_id`, `bukku_number`, `bukku_short_link` and `posted_at`, and `extracted` is left **untouched** — it stays the record of what the model read, so the beta can be judged after a reviewer has corrected it.

### Part four — the kitchen's own copy (`RecordPurchaseFromScan`)

15. Lines are built from **matched rows only** and handed to `LogPurchase`, so stock in, `unit_cost` repriced and plate costs recalculated behave exactly as they do for a hand-keyed purchase — see [[Path — Logging a purchase]].
16. The supplier is resolved from the Bukku contact the reviewer picked, matched **by name, case-insensitively**, against this system's own `suppliers`. The two lists are separate but hold the same companies. A genuinely new one is created, because `purchases.supplier_id` is NOT NULL and refusing to record a delivery from someone new would be the worse answer.
17. `invoice_scans.purchase_id` is written, and is the guard against one invoice becoming two purchases.

This runs **after** the bill is filed and is **deliberately not fatal**. The bill is already on the books and re-pushing is refused, so a failure here reports itself and leaves the filing alone — throwing away a successful filing because the shelf update tripped would be far worse.

---

## The mathematics

Only one figure is computed here, and it is not a system formula — it is the bill total:

```
line amount = bcmul(quantity, unit_price, 2)
bill total  = bcadd(...) over every line
due date    = invoice date + TERMS[term_id]['days']
```

All four terms in `PushInvoiceToBukku::TERMS` are the `in_days` type, which is why the due date is plain addition. Verified against Bukku's own reference list on 2026-09-03: ids 1/2/3/4 are COD/NET14/NET30/NET60. Bukku also has `EOM`, `EOFM` and `DOM25`, which are **not** `in_days` and are deliberately not offered.

No tax is posted. The company is **not SST registered** — every tax code in Bukku is archived — so bills go out `tax_mode: exclusive` with no tax, matching what is already on the books.

---

## What it touches

| Table / system | Effect |
|---|---|
| `invoice_scans` | one row per upload — photo path, `extracted`, status, Bukku reference |
| `invoice_item_aliases` | one row per supplier wording the reviewer matched to a shelf item |
| `audits` | via `LogsActivity` |
| local disk | the uploaded photo |
| **Bukku** | a purchase bill, and a file attached to it |
| `purchases`, `purchase_lines` | one purchase, built from the matched lines |
| `suppliers` | a new row only when the supplier is genuinely unknown |
| `inventory_items` | **quantity in, `unit_cost` repriced** — for matched lines only |
| `recipes` | plate costs recalculated for any re-priced ingredient |
| `inventory_items.bukku_product_id` | read, never written here — set on the Inventory page or by `bukku:link-products` |

---

## Traps

**Bukku caps `page_size` at 100.** Asking for more is a 422, not a truncated list. The code asked for 200 contacts and 500 products, so both came back empty — and because a failed read was cached like a successful one, the review screen told the reviewer *"No suppliers came back from Bukku"* for an hour at a time. With valid credentials the feature still could not have posted a single bill. Reference lists are now paged at 100, and a failed read is not cached at all. Found 2026-09-03.

**A mapped line must not keep the reviewer's account.** The review screen defaults every row to General Expense (33). A line correctly mapped to a stock product but left on that default posts as money spent with nothing bought. The account is taken off the product and **overrides** whatever the row said — the reviewer picks the product; the account is not theirs to get wrong. The product *list* carries no accounts, only `GET /products/{id}` does, which is why this is one GET per mapped line at Submit rather than 77 on every render.

**Bukku has no idempotency key.** A 5xx or a dropped connection might have committed before it failed, so a blind retry can put the same invoice on the books twice. Every bill this app writes carries a marker unique to its scan in the description, and that marker *is* the key: an ambiguous failure asks Bukku whether the bill exists, adopts it if so, and only retries once absence is confirmed. The lookup is a **substring** search — `invoice #7` returns both `#71` and `#72` — so a hit is confirmed against the whole description before it is believed.

**`product_unit_id` and `location_id` are unproven on the way in.** They were read off a bill's GET *response*, which is not proof Bukku accepts them on a *request*, and that cannot be proven without writing to the real books. A 422 therefore drops both and sends once more. Only a 422 — anything else might have committed.

**A 4xx is never retried at the client.** An identical second attempt cannot mend a request Bukku called malformed; it only triples the log noise and keeps the reviewer waiting.

**A nullable field the reviewer leaves blank is absent from `validated()`**, not present-and-empty. Indexing `$data['invoice_number']` directly threw an `ErrorException` and the reviewer saw *"Bukku did not accept the bill: Undefined array key"*. It is read once, defensively, at the top.

**Unmatched lines must not become stock.** A line the reviewer did not match is a delivery fee or something the kitchen does not stock — guessing a shelf for it would put fictional stock in front of the Owner. It goes on the Bukku bill and nowhere else.

**Recording stock must never cost a filed bill.** By the time `RecordPurchaseFromScan` runs, the bill exists in Bukku and re-pushing is refused. A throw here would leave the invoice filed with no way to retry, so it is caught, reported, and the reviewer is told to key the delivery in by hand.

**An unticked line must teach nothing.** Rejecting a row and still learning from it would poison the dictionary with exactly the readings a human just said were wrong. `include` is required per line, and a missing tick fails **closed** — the bill is refused with "A bill needs at least one line" rather than quietly billing everything.

**The alias cascades on delete**, against this repo's usual `nullOnDelete` habit. The deletion rule protects kitchen *records*; an alias is a lookup rule, and one pointing at a deleted shelf item can only ever produce a silently wrong match.

**Reads do not retry; writes do.** Three endpoints × three tries × a two-second pause is eighteen seconds of a manager watching a blank review screen during a wobble. An empty picker says so honestly; a half-sent bill is the expensive failure.

---

## How the account is chosen, now that nobody picks it

The account and Bukku-product pickers came off the review table on 2026-09-03. What replaced them is a chain, and each link was built for its own reason:

```
supplier's wording  ──(invoice_item_aliases)──▶  inventory item
inventory item      ──(bukku_product_id)─────▶  Bukku product
Bukku product       ──(GET /products/{id})───▶  its own account
```

The reviewer supplies only the middle step, by matching the line to a shelf item — which they were doing anyway, to move stock. Everything after that is derived. A **linked** item posts against Inventory; an **unlinked** one falls back to `BUKKU_DEFAULT_ACCOUNT_ID`, exactly as before the link existed, so the sparse state is safe rather than half-broken.

`inventory_items.bukku_product_id` has **no foreign key** — the id lives in another company's system, over HTTP — and is **unique**, because two shelf items pointing at one product would post the same stock twice.

`php artisan bukku:link-products` fills it in bulk, applying **only exact** name matches and printing anything less certain as a suggestion it refuses to write. A wrong link is silent and repeats on every future invoice; an unlinked item is merely blunt. Those two failures are not comparable, which is why the guessing stays with the person who knows.

---

## See also

[[Code paths index]] · [[Authorization gates]] · [[Rounding and money]] · [[Environment variables]] · [[Audit trail]]
