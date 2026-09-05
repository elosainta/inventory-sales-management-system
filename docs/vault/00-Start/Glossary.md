# Glossary

Kitchen words and code words, and where they meet.

| Term | Means | Where it lives |
|---|---|---|
| **Action** | A single business operation, one class, one `execute()`. The only place a write is allowed to start. | `app/Domain/<Context>/Actions/` — [[Layering rules]] |
| **Audit** | An immutable before/after record of a change to a financial model. | `audits` table — [[Audit trail]] |
| **COGS** | Cost of goods sold: what the food you actually sold cost to make. Not what you spent buying stock. | [[Cost of goods sold]] |
| **Float** | Petty cash handed to a chef for market runs. Issued, spent, returned. | `float_issuances` — [[Petty cash balance]] |
| **Gate** | A named yes/no authorization rule. The only permission mechanism in the system. | `AppServiceProvider::boot()` — [[Authorization gates]] |
| **Manager** | Owner *or* Head Chef. Most operational gates key off this. | `User::isManager()` — [[Roles]] |
| **Market purchase** | An ad-hoc ingredient buy from a market, as opposed to a supplier order. | `market_purchases` |
| **Misc percent** | Overhead added on top of ingredient cost — gas, condiments, small consumables. Defaults to 30%. | [[Plate cost]] |
| **Monetary value** | The money sitting on the shelf for one item: quantity × unit cost. | [[Inventory monetary value]] |
| **Open order** | Food a staff member ordered from the kitchen, usually discounted. An *option on a sale*, not a separate module. Recorded anonymously. | [[Path — Logging a sale]] |
| **Finished dish** | The stock a recipe produces (`recipes.output_inventory_item_id`). Set = that recipe runs Inventory → Production → Sales; null = a sale deducts its raw ingredients, as before. | [[Path — Logging production]] |
| **Plate cost** | What one serving of a dish costs to make. | [[Plate cost]] |
| **Production batch** | A dish the kitchen cooked: one recipe and how many were made. Consumes the recipe's ingredients and adds the finished dish to stock. | [[Path — Logging production]] |
| **Reorder threshold** | The comfortable target stock level. **Not** the alert trigger — the trigger is half of it. | [[Low stock threshold]] |
| **Section** | A physical station in the kitchen with its own task checklist. Owned by nobody — anyone in the kitchen works any section. | `sections`, `section_tasks` |
| **Stock-take** | What came in and what went out, counted off the Pantry catalog. **In adds to live stock, Out takes off it.** Current stock and balance are read from inventory, never typed. Feature 4. | [[Path — Recording a stock-take]] |
| **Tally** | Manual count against the *live* 185-item inventory that **reconciles stock to the count**. Feature 5. | [[Path — Recording a tally]] |
| **Unit cost** | Cost of one unit of an ingredient. Overwritten by the most recent purchase or production. | [[Inventory as shared state]] |
| **Variance** | Counted minus system. Negative means shrinkage. | [[Tally variance]] |
| **Wastage rate** | Wasted money as a percentage of purchase spend. | [[Wastage cost and rate]] |

## Two counts, one kitchen

The easiest thing to confuse in this system:

- **Stock-take** ([[Path — Recording a stock-take]]) is the movement log. Its own catalog, its own history, and it **moves `quantity_on_hand` by In − Out**. It answers "what moved?".
- **Tally** ([[Path — Recording a tally]]) walks the *real* inventory and **overwrites `quantity_on_hand`** with what was counted. It answers "what is actually there?". The shelf wins.

Both are recorded by chefs and reviewed by the Owner. Only one of them changes money.
