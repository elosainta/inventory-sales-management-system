<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Abilities the blanket Admin grant above does not decide.
     *
     * Not a deny list — these fall through to their own Gate::define, which
     * may still allow Admin (toggle-maintenance does, for a non-demo account).
     */
    private const ADMIN_EXCEPT = ['view-dashboard', 'toggle-maintenance', 'decide-rnd'];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Money helper directive (already added earlier)
        Blade::directive('money', function (string $expression) {
            return "<?php echo \App\Support\Money::format($expression); ?>";
        });

        // ---------- Authorization Gates ----------

        // Admin reaches every feature except the financial dashboard, on the
        // Owner's instruction as at 2026-09-03. This supersedes the money-and-
        // personal-data boundary drawn in 1.11.2: support now reads and writes
        // sales, purchases, recipes, production, petty cash, invoice scan,
        // leave and peer feedback as well as the operational screens.
        //
        // Written as a Gate::before rather than as `|| $user->isAdmin()` on
        // twenty-five closures, because "every feature" has to include the
        // next one. A hand-maintained list of twenty-five would drift the
        // first time a module is added — which is the exact failure this
        // codebase has already shipped twice with hand-written nav lists.
        //
        // Two abilities fall through to their own closure instead of being
        // granted outright:
        //   view-dashboard     the one exclusion the Owner named.
        //   toggle-maintenance already grants Admin, but carries a `! is_demo`
        //                      guard — a blanket true would let a demo admin
        //                      503 the live app.
        //
        // What this does NOT lift: the anti-escalation guards on the Users
        // page. Those are explicit isAdmin() checks in UserController, not
        // gates, so an Admin still cannot reset, delete, re-language or
        // re-role an Owner or another Admin, and cannot grant the owner or
        // admin role to anyone. Reaching every feature is access; taking over
        // the Owner's account is not a feature — and without that limit the
        // dashboard exclusion above is one password reset and one promotion
        // away from meaningless.
        Gate::before(function (User $user, string $ability) {
            return $user->isAdmin() && ! in_array($ability, self::ADMIN_EXCEPT, true)
                ? true
                : null;   // null = no opinion, fall through to the gate itself
        });


        // Manager-only: full create/edit/delete on operational data.
        Gate::define('manage-suppliers',  fn (User $user) => $user->isManager());
        // The `|| isAdmin()` here and on record-inventory below is redundant
        // since 1.11.7 — Gate::before already grants it — and is left in place
        // so the closure still says what it means if that blanket is ever
        // narrowed again.
        Gate::define('manage-inventory',  fn (User $user) => $user->isManager() || $user->isAdmin());

        // Keying stock in, as opposed to owning the catalogue. A part timer may
        // add an item and correct a quantity; renaming, repricing and deleting
        // stay on manage-inventory above. InventoryItemController::update
        // narrows the payload to quantity_on_hand for anyone who holds only
        // this gate — without that narrowing, "can key in" would be full edit
        // with a different name on it.
        Gate::define('record-inventory',  fn (User $user) => $user->isManager() || $user->isAdmin() || $user->isPartTimer());
        Gate::define('manage-purchases',  fn (User $user) => $user->isManager());
        Gate::define('manage-wastage',    fn (User $user) => $user->isManager() || $user->isPartTimer());
        Gate::define('manage-sales',      fn (User $user) => $user->isManager());
        Gate::define('manage-recipes',    fn (User $user) => $user->isManager() || $user->hasRecipeException());

        // Inventory is readable by the whole kitchen — a junior chef needs to see
        // what is on hand for the section they are prepping.
        //
        // Admin was let in here first, in 1.11.4, as the single deliberate hole
        // in a money boundary that has since been removed altogether — see the
        // Gate::before at the top. The reason it was the first hole is still
        // the reason the whole boundary went: an inventory figure is what
        // support actually gets called about, and diagnosing one blind is
        // guesswork.
        Gate::define('view-inventory', fn () => true);

        // Read access to the commercial pages. Managers only — a junior chef
        // reads inventory (see view-inventory above) and nothing else priced.
        Gate::define('view-suppliers', fn (User $user) => $user->isManager());
        Gate::define('view-purchases', fn (User $user) => $user->isManager());
        Gate::define('view-wastage',   fn (User $user) => $user->isManager() || $user->isPartTimer());
        Gate::define('view-sales',     fn (User $user) => $user->isManager());
        // Riley reads and edits recipes despite being a junior chef — see
        // User::RECIPE_EXCEPTIONS for why this is hard-coded and how it fails.
        Gate::define('view-recipes',   fn (User $user) => $user->isManager() || $user->hasRecipeException());

        // Managers (Owner + Head Chef): petty cash handling.
        Gate::define('manage-float',   fn (User $user) => $user->isManager());

        // Owner-only: trust and money handling.
        Gate::define('view-audit-log', fn (User $user) => $user->isOwner());
        Gate::define('manage-events',  fn (User $user) => $user->isOwner());
        Gate::define('manage-users',        fn (User $user) => $user->isOwner());
        // Head Chefs run the sections day to day - they are the ones who know a
        // task has changed - so they edit them alongside the Owner. Deliberately
        // not owner-only: it sat here under "trust and money handling", which a
        // prep task is not. Reaching it is a button on Prep Overview.
        Gate::define('manage-sections',     fn (User $user) => $user->isManager());
        // Demo/training owners are excluded: maintenance state lives in the
        // live-pinned cache, so a demo toggle would 503 the real app.
        Gate::define('toggle-maintenance',  fn (User $user) => ($user->isOwner() || $user->isAdmin()) && ! $user->is_demo);

        // Support tickets — Owner and Admin review/resolve user-submitted reports.
        Gate::define('view-support-tickets',   fn (User $user) => $user->isOwner() || $user->isAdmin());
        Gate::define('manage-support-tickets', fn (User $user) => $user->isOwner() || $user->isAdmin());

        // User list + password resets — Owner has full user management; Admin may
        // view the list and reset passwords (but NOT for owner/admin accounts — see
        // UserController::updatePassword guard — to prevent privilege escalation).
        Gate::define('view-users',            fn (User $user) => $user->isOwner() || $user->isAdmin());
        Gate::define('manage-user-passwords', fn (User $user) => $user->isOwner() || $user->isAdmin());
        // Deletion is deliberately its own gate rather than folding Admin into
        // manage-users, which would also hand them editing, role changes and
        // invitations. Same escalation limit as passwords: an Admin may not
        // delete an Owner or another Admin (see UserController::destroy).
        Gate::define('delete-users',          fn (User $user) => $user->isOwner() || $user->isAdmin());
        // Setting someone's display language is a display preference, not a
        // privilege, so it gets its own gate rather than riding on manage-users.
        // It still carries the same escalation limit as passwords and deletion —
        // an Admin may not reach an Owner or another Admin (see
        // UserController::updateLanguage) — so nobody can flip a superior's UI.
        Gate::define('manage-user-language',  fn (User $user) => $user->isOwner() || $user->isAdmin());
        // The prep checklist is shared work, not a personal worklist: any task
        // may be done by whoever is standing in front of it, and the account
        // that ticks it is stamped on the row. So everyone gets in.
        //
        // This replaced `view-my-section` (junior chefs only, one assigned
        // section each) in 1.10.49. Sections are no longer assigned to a person.
        Gate::define('view-checklist',  fn () => true);
        Gate::define('overview-checklist', fn (User $user) => $user->isManager() || $user->isAdmin() || $user->isPartTimer());
        Gate::define('delete-entries',     fn (User $user) => $user->isManager());
        Gate::define('export-pdf',         fn (User $user) => $user->isManager());
        Gate::define('view-dashboard',     fn (User $user) => $user->isManager());
        // Low-stock alerts are sent to head chefs (see LogSale), so they must be
        // able to dismiss their own. Owners may too. Dismiss only ever marks the
        // current user's own notifications read, so there is no escalation risk.
        Gate::define('dismiss-low-stock',  fn (User $user) => $user->isManager());
        Gate::define('search-global',      fn (User $user) => ! in_array($user->role, [User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER], true));
        Gate::define('submit-support',        fn (User $user) => true);
        // Peer feedback (replaced the Compliance module): staff rate each other
        // anonymously; only the Owner sees identities and exports the report.
        Gate::define('view-feedback',       fn (User $user) => ! $user->isAdmin() && ! $user->isPartTimer());
        Gate::define('submit-feedback',     fn (User $user) => ! $user->isAdmin() && ! $user->isOwner() && ! $user->isPartTimer());
        Gate::define('export-feedback-pdf', fn (User $user) => $user->isOwner());
        Gate::define('manage-market-purchases', fn (User $user) => $user->isManager());
        Gate::define('view-market-purchases',   fn (User $user) => $user->isManager());
        // Junior chefs log the batches they cook, same as the Head Chef.
        // Deleting a batch stays with delete-entries. (Admin passes both of
        // these via Gate::before, despite what the closures say.)
        Gate::define('manage-production', fn (User $user) => ! $user->isAdmin() && ! $user->isPartTimer());
        Gate::define('view-production',   fn (User $user) => ! $user->isAdmin() && ! $user->isPartTimer());
        // Invoice scan (BETA): a photo becomes a purchase bill on the
        // company's books. Managers and Admin - a junior chef or part timer has
        // no business posting to it. Nothing reaches Bukku without a human
        // pressing Send, which is the safety story this screen rests on.
        Gate::define('use-invoice-scan', fn (User $user) => $user->isManager());

        // R&D purchases: something bought to try out. Chefs, Admin and the
        // Owner record and correct them; the Owner alone decides.
        //
        // decide-rnd is in ADMIN_EXCEPT above, so it falls through to this
        // closure rather than being granted by the blanket Admin grant. The
        // Owner named themselves for the approval, and an approval is the one
        // thing on this page that is not clerical.
        Gate::define('view-rnd',   fn (User $user) => ! $user->isPartTimer());
        Gate::define('manage-rnd', fn (User $user) => ! $user->isPartTimer());
        Gate::define('decide-rnd', fn (User $user) => $user->isOwner());

        Gate::define('write-daily-report', fn (User $user) => $user->isHeadChef());
        Gate::define('view-daily-report',  fn (User $user) => $user->isManager() || $user->isAdmin());

        // Leave applications: staff apply, Owner decides. Admin reads them as
        // of 1.11.7 (Gate::before) but does not decide - decide-leave is the
        // Owner's, and Gate::before is what makes that read a real widening
        // over personal data, not a technicality.
        Gate::define('view-leave',   fn (User $user) => ! $user->isAdmin() && ! $user->isPartTimer());
        Gate::define('submit-leave', fn (User $user) => ! $user->isAdmin() && ! $user->isOwner() && ! $user->isPartTimer());
        Gate::define('decide-leave', fn (User $user) => $user->isOwner());

        // About / release notes: plain-language system history, open to anyone signed in.
        Gate::define('view-about', fn (User $user) => true);

        // Self-service account settings: own name, own password, own language,
        // and deleting your own account. Withheld from part timers on the
        // Owner's instruction.
        //
        // The consequence is that a part timer cannot rotate their own
        // password, and accounts start on a shared default — an Owner or Admin
        // has to reset it for them from the Users page. That is the trade this
        // gate makes; it is not an oversight.
        Gate::define('edit-profile', fn (User $user) => ! $user->isPartTimer());

        // Pantry/Kitchen stock-take: chefs walk the shelves and record counts,
        // managers review. A separate manual record — never touches live stock.
        Gate::define('view-stock-take',         fn () => true);
        Gate::define('record-stock-take',       fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
        Gate::define('manage-stock-take-items', fn (User $user) => $user->isManager());

        // Inventory tally check: a manual count against live inventory. Chefs record
        // what they physically counted; the Owner reviews the variance vs system
        // numbers afterwards. This feature never writes to live stock.
        Gate::define('view-tally',   fn (User $user) => ! $user->isPartTimer());
        Gate::define('record-tally', fn (User $user) => ! $user->isAdmin() && ! $user->isOwner() && ! $user->isPartTimer());
    }
}