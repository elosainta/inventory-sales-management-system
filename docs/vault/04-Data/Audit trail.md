# Audit trail

Every change to a financial model is recorded automatically, before/after, with the user who made it. Nothing calls the audit explicitly — it is a model event hook.

## The mechanism

`app/Traits/LogsActivity.php` — one trait, three Eloquent event hooks:

```php
public static function bootLogsActivity(): void
{
    static::created(function ($model) {
        Audit::create([
            'user_id'        => Auth::id(),
            'action'         => 'created',
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'before'         => null,
            'after'          => $model->toArray(),
        ]);
    });

    static::updated(function ($model) { /* … */ });
    static::deleted(function ($model) { /* … */ });
}
```

Laravel calls `bootLogsActivity()` automatically because of the naming convention `boot{TraitName}`. Adding `use LogsActivity;` to a model is the entire integration.

## The payloads differ per action

| Action | `before` | `after` |
|---|---|---|
| `created` | `null` | full `toArray()` |
| `updated` | **only the changed keys, old values** | **only the changed keys, new values** |
| `deleted` | full `toArray()` | `null` |

The update case is the clever one:

```php
'before' => array_intersect_key($model->getOriginal(), $model->getDirty()),
'after'  => $model->getDirty(),
```

`getDirty()` returns only changed attributes. Intersecting the original state with those keys yields the matching old values. So an update that changes one column of a 15-column row stores two keys, not thirty.

> [!note] `getDirty()` is empty when nothing actually changed
> Saving a model with no modifications does not fire `updated` at all, so no empty audit row appears. Laravel's dirty-checking handles it.

## Polymorphic target

`auditable_type` (fully-qualified class name) + `auditable_id`. `Audit::auditable()` is a `morphTo`.

This is why `compliance_reports` and the `ComplianceReport` model are **kept** after the Compliance module was retired: existing audit rows point at that class, and dropping it would make historical entries unresolvable in the log view. See [[Database overview]].

## Who is audited

Generated, always-current list: [[Models index]], "Audited" column.

The rule: **parents yes, child detail no.**

| Audited | Not audited | Why not |
|---|---|---|
| `Sale` | `SaleAttachment` | file metadata, covered by the sale |
| `Purchase` | `PurchaseLine` | line detail, covered by the purchase |
| `Recipe` | `RecipeIngredient` | rebuilt wholesale on every edit — see [[Path — Saving a recipe]] |
| `InventoryTally` | `InventoryTallyLine` | count detail, covered by the tally |
| `StockTake` | `StockTakeEntry`, `StockTakeOpenOrder` | sheet detail |
| `ProductionBatch` | `ProductionBatchLine` | line detail |
| `InventoryItem`, `FloatIssuance`, `WastageEntry`, `User`, `FeedbackEntry`, `DailyReport`, `StockTakeItem` | `Audit` itself | it would recurse |

## Volume

Every stock mutation is an audited `InventoryItem` update. One sale of a six-ingredient dish writes **seven** rows.

A purchase is worse: the purchase, one per item, plus one per recosted recipe. A 20-line invoice touching shared staples can produce fifty.

$$\text{rows per sale} = 1 + |\text{ingredients}| \qquad \text{rows per purchase} = 1 + |\text{items}| + |\text{recosted recipes}|$$

**No retention policy exists.** The table only grows.

```sql
SELECT COUNT(*) FROM audits;
SELECT auditable_type, COUNT(*) FROM audits GROUP BY auditable_type ORDER BY 2 DESC;
```

At current scale this is fine, and the growth is the intended trade — completeness over compactness. If it ever needs trimming, `InventoryItem` updates are the bulk and the least individually interesting.

## Reading it

Route `/audits` → `AuditController`. Gate `view-audit-log` → `isOwner()`. Owner only — it is the trust mechanism, and it records the Owner's own actions too.

## What it does and does not give you

**Does:**

- who changed what, when, from what, to what
- forensic reconstruction of a value's history — every previous `unit_cost` is in there
- the actor behind an anonymous [[Path — Logging a sale]] open order

**Does not:**

- **capture the actor for CLI or scheduled work.** `Auth::id()` is `null` for `demo:reset`, `feedback:monthly-report` and anything run through tinker. Those changes are recorded with a null user.
- **record reads.** Downloading the financial PDF leaves no trace.
- **survive `DB::table()` writes.** Query-builder writes bypass Eloquent entirely and fire no model events. Everything in `app/Domain/` uses Eloquent; a future bulk operation written with the query builder would be invisible.
- **prevent anything.** It is a record, not a control. Prevention is [[Authorization gates]].

## Adding a model to the trail

```php
use App\Traits\LogsActivity;

class Thing extends Model
{
    use LogsActivity;
}
```

That is all. `CLAUDE.md` requires it on all financial models.

## See also

[[Inventory as shared state]] · [[Authorization gates]] · [[Database overview]] · [[Roles]]
