# Layering rules

Four rules from `CLAUDE.md`, each with a reason and each with a known exception.

## 1. Controllers are thin

A controller method should authorize, hand off, and redirect. Nothing else.

```php
public function store(StoreSaleRequest $request, LogSale $action)
{
    Gate::authorize('manage-sales');

    $action->execute($request->validated());

    return back()->with('success', 'Sale logged.');
}
```

**Why:** logic in a controller can only be reached through HTTP. Logic in an action can be called from a console command, a test, or a second controller. `Sale::revenue()` being callable from both `LogSale` and `SaleController@update` is the whole reason those two paths cannot drift — see [[Sale revenue]].

## 2. `Gate::authorize()` is the first line of every controller method

No exceptions. Including `index`, `show`, and `exportPdf`.

**Why:** the failure mode of forgetting is silent. Nobody notices a missing check on `exportPdf` until a junior chef downloads the financial PDF. Making it mechanical — *first line, every method, no thought required* — is what makes it auditable. Verify with:

```bash
grep -L "Gate::authorize" app/Http/Controllers/*.php
```

Full mechanism: [[Authorization gates]]. Live matrix: [[Gates matrix]].

## 3. Input is validated by Form Requests, never in the controller

31 Form Requests live in `app/Http/Requests/`.

**Why:** validation rules are the contract of an endpoint. Kept in their own class they are greppable, testable, and reusable between store and update.

## 4. Business logic lives in `app/Domain/<Context>/Actions/`

One class, one `execute()`, wrapped in `DB::transaction()` when it touches more than one row.

**Why transactions specifically:** every one of these actions writes a record *and* mutates `inventory_items`. A half-applied sale — money recorded, stock not deducted — is corruption that nobody would notice until the next count. See [[Inventory as shared state]].

Which actions are transactional: [[Domain actions index]] — all six of them are.

**The corollary, added in 1.10.48:** a write that needs no transaction and derives no value does not need an action either. `LogEvent` and `IssueFloat` were single-line `Model::create()` wrappers and now live inline in their controllers. The rule is "business logic lives in an action", not "every write lives in an action".

---

## The known exceptions

Two features write stock **from the controller**, with no action class:

| Feature | File | Writes |
|---|---|---|
| Tally reconcile | `app/Http/Controllers/InventoryTallyController.php:47-110` | `inventory_items.quantity_on_hand`, `monetary_value` |
| Stock-take record | `app/Http/Controllers/StockTakeController.php:54-100` | nothing outside its own tables |

Both are wrapped in `DB::transaction()`, so rule 4's *actual* protection is intact. But the tally path is the more serious of the two — it is the only place outside `app/Domain/` that overwrites live stock quantities. If a second caller ever needs to reconcile stock, extract `ReconcileStock` before writing it twice.

Stock-take's exception is cheap: it writes only its own tables and touches nothing shared, so there is nothing for a second caller to duplicate.

## See also

[[Architecture overview]] · [[Request lifecycle]] · [[Path — Recording a tally]] · [[Audit trail]]
