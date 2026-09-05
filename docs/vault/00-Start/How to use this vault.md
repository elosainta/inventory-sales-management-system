# How to use this vault

Open `docs/vault` as an Obsidian vault. Everything links; nothing is meant to be read top to bottom.

## Two halves

| Half          | Where                            | Who writes it            | Rule                                        |
| ------------- | -------------------------------- | ------------------------ | ------------------------------------------- |
| **Curated**   | every folder except `_generated` | dev / claude             | edit freely — this is where the *why* lives |
| **Generated** | `_generated/`                    | `php artisan vault:sync` | never edit — overwritten on every commit    |

The split exists because facts and explanations rot at different speeds. A route table goes stale the moment someone adds a route, so it is derived from the router itself. The reason a route exists never changes automatically, so it is written by hand. See [[Vault automation]].

## Conventions

**Code references.** Every claim about behaviour cites the file and line it came from, like `app/Models/Sale.php:31-34`. If a line number has drifted, the surrounding text still names the method — search for that.

**Formulas.** Written in LaTeX and rendered by Obsidian, always paired with the exact PHP that implements them and a worked example with real-ish numbers. If the maths and the code ever disagree, the code is what runs and the note is the bug.

**Links.** Wiki-links (`[[` … `]]`) throughout. A link with no note behind it is deliberate — it marks something worth writing.

**Callouts.**

> [!warning] Auto-generated — do not edit

> [!danger] A real trap that has bitten this codebase

> [!note] Context you would otherwise have to dig for

## Reading orders

**"I am new to the codebase."**
[[Architecture overview]] → [[Layering rules]] → [[Request lifecycle]] → any one note in [[Code paths index]].

**"I need to change how a number is calculated."**
[[Formulas index]] → the formula's note → the code path that calls it. Never change a formula in only one caller; [[Sale revenue]] explains why.

**"I need to deploy or fix production."**
[[Deployment]] → [[VPS and hosting]] → [[Backups]].

**"Someone can see something they should not."**
[[Authorization gates]] → [[Gates matrix]] → [[Roles]].

## Graph view

Turn off `_generated` in the graph filter (`-path:_generated`) to see the conceptual structure without the generated tables dominating the picture.
