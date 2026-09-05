<?php

namespace App\Support;

/**
 * Plain-language release history for the "About System" page.
 *
 * These notes are curated by hand but map one-to-one onto the Git commit
 * history of the private repository this snapshot was published from — see
 * TOTAL_COMMITS below for the count, FIRST_COMMIT for where it starts. Every
 * commit is folded into exactly one release below. (This public repository is
 * a squashed snapshot, so those two constants describe the development history
 * rather than anything you can `git log` here.) Raw commit messages are
 * written for developers, so
 * we rewrite them here in language any team member can follow. When you cut a
 * new release, add an entry to the TOP of all() and bump CURRENT_VERSION and
 * TOTAL_COMMITS (the count includes the release commit itself).
 *
 * The count is deliberately NOT repeated in this docblock — it drifted for
 * several releases while sitting two lines above the constant that was right.
 *
 * VERSIONING — Minecraft-style, not semver. The minor number just counts up and
 * is NOT a decimal: 1.8 → 1.9 → 1.10 → 1.11 → … → 1.21. So 1.8 is the ninth
 * release of the 1.x line, not "80% of the way to 2.0" — there is no shrinking
 * budget of numbers to run out of. Bump the minor for a normal release, the
 * patch (1.8.1) for a fix-only follow-up. 2.0 is reserved for a rewrite of what
 * the system fundamentally is, and is not on the roadmap.
 *
 * Each release: 'version', 'date' (Y-m-d), 'summary', and any of
 * 'added' / 'improved' / 'removed' (arrays of plain sentences). 'commits' is a
 * short reference to the Git range it covers, shown as a small technical note.
 */
class ReleaseNotes
{
    public const CURRENT_VERSION = '1.15.4';

    /** The very first commit, for the "since" line in the header. */
    public const FIRST_COMMIT      = '047cdf3';
    public const FIRST_RELEASE_DATE = '2026-04-29';

    /** Total commits behind the app, in the private repository it is developed in. */
    public const TOTAL_COMMITS = 309;

    /**
     * @return array<int, array<string, mixed>> newest release first
     */
    public static function all(): array
    {
        return [
            [
                'version' => '1.15.4',
                'date'    => '2026-09-03',
                'summary' => 'Sending an invoice to Bukku gives up less easily.',
                'fixed'   => [
                    'If Bukku refuses the details of a stock line, the bill is now sent again in a simpler form instead of failing, so nobody is left holding an invoice they cannot file. It is only retried when Bukku says the details were wrong — never when Bukku might have already saved it, which would create the same bill twice.',
                ],
                'commits' => '59e7674..HEAD',
            ],
            [
                'version' => '1.15.3',
                'date'    => '2026-09-03',
                'summary' => 'Invoice scan puts stock on the right account.',
                'fixed'   => [
                    'A scanned invoice line matched to a stock product was going to the books as a general expense unless the reviewer also remembered to change the account beside it. The account now comes off the product itself, the same way the bills written from the old Telegram bot did, so buying stock reads as buying stock.',
                    'Sending an invoice with the invoice number left blank failed with an error instead of creating the bill.',
                ],
                'commits' => '58fbc51..HEAD',
            ],
            [
                'version' => '1.15.2',
                'date'    => '2026-09-03',
                'summary' => 'Slices can now be a unit.',
                'added'   => [
                    'Slices as a unit on an inventory item, alongside kg, pcs, tray and the rest — for the things the kitchen counts in slices rather than by weight or by packet.',
                ],
                'commits' => '591881a..HEAD',
            ],
            [
                'version' => '1.15.1',
                'date'    => '2026-09-03',
                'summary' => 'R&D no longer asks for an invoice number.',
                'removed' => [
                    'The invoice number field on an R&D. It was left over from when a trial was described as a purchase, and it is not one — the trial is done with stock the kitchen already bought, and whichever invoice that stock arrived on belongs to the purchase, not to the experiment.',
                ],
                'commits' => '809ce3a..HEAD',
            ],
            [
                'version' => '1.15',
                'date'    => '2026-09-03',
                'summary' => 'An R&D trial is now a costing sheet, not a single line.',
                'added' => [
                    'One R&D records the whole dish: every ingredient it was made with, each with its price per unit, the unit, and how much was used. Same shape as the costing sheets the kitchen already writes by hand.',
                    'The bottom of the sheet adds up as you type — total, a miscellaneous percentage on top (30% by default, the same overhead Recipes uses), the grand total, the selling price you are aiming at, and the profit per dish.',
                    'The number of servings the sheet is costed for, so a trial says whether it is a one-pax plate or a batch.',
                    'Each trial on the page opens and closes on its own, so the list stays scannable — dish, status, date and grand total on the line, the full sheet inside. Ones waiting on the Owner start open.',
                ],
                'improved' => [
                    'Recording a trial takes every ingredient on the sheet off stock, not just one. An ingredient listed twice comes off once for the total of the two.',
                    'Turning an approved trial into a recipe now carries over every ingredient with its quantity, and the same miscellaneous percentage, instead of the single item it used to.',
                    'Spent per menu, the PDF and global search all read the grand total, so what a dish cost to develop is the same figure everywhere.',
                ],
                'commits' => 'f5baf36..HEAD',
            ],
            [
                'version' => '1.14.3',
                'date'    => '2026-09-03',
                'summary' => 'R&D shows what each menu has cost to develop.',
                'added' => [
                    'A Spent per menu table at the top of the R&D page: every dish with how many trials it took, what has been approved, what is still waiting, and the total — most expensive first. The same table opens the PDF.',
                    'A dish that has made it onto the menu links straight to its recipe from that table.',
                ],
                'improved' => [
                    'The total for a dish includes rejected trials. Rejecting one means the kitchen does not pay for it, but the ingredient still came off the shelf — a total that left those out would understate what the dish cost to arrive at. The table says so underneath.',
                ],
                'commits' => '59734f8..HEAD',
            ],
            [
                'version' => '1.14.2',
                'date'    => '2026-09-03',
                'summary' => 'R&D now records the menu it was for as well as the item used.',
                'added' => [
                    'A Menu name on every R&D — the dish the trial was for. The list shows it alongside the item used, so you can tell at a glance which experiment each ingredient belonged to instead of reading a list of ingredients.',
                    'The menu name is searchable, appears on the PDF, and is what the Make a recipe form starts with when a trial becomes a dish.',
                ],
                'commits' => '49a63bf..HEAD',
            ],
            [
                'version' => '1.14.1',
                'date'    => '2026-09-03',
                'summary' => 'The R&D page calls things R&D rather than purchases.',
                'changed' => [
                    'The button now reads Make an R&D instead of Record a purchase, and the messages that follow say R&D too — approved, rejected, updated, removed. Nothing about how it works has changed.',
                ],
                'commits' => '6741019..HEAD',
            ],
            [
                'version' => '1.14.0',
                'date'    => '2026-09-03',
                'summary' => 'An approved R&D trial can be turned into a real recipe, and R&D has a PDF export.',
                'added' => [
                    'Once the Owner has approved a trial, a Make a recipe button appears on it. Give the dish a name, a serving size and a price, and it becomes a recipe on the Recipes page — the ingredient and how much of it was used come straight from the trial.',
                    'The R&D list now has a Became column showing which trials turned into a dish, linking to it. A trial can only become one recipe.',
                    'An Export PDF button on R&D. The sheet lists every entry with its status, who decided it, what it cost, and what it became, with totals for approved, waiting and rejected.',
                ],
                'improved' => [
                    'A trial is rarely a finished portion, so the recipe it creates is a starting point — check the quantities on the Recipes page before it goes on the menu. The form says so.',
                ],
                'commits' => '75b5d79..HEAD',
            ],
            [
                'version' => '1.13.2',
                'date'    => '2026-09-03',
                'summary' => 'R&D entries turn up in the search box.',
                'added' => [
                    'Searching now looks through R&D as well — by ingredient, invoice number or remark. Results show the date, the invoice, whether it is still waiting, who recorded it and what it came to.',
                    'An entry stays findable under the name it was recorded with, so renaming an ingredient does not lose the spending filed against the old name.',
                ],
                'commits' => '2209344..HEAD',
            ],
            [
                'version' => '1.13.1',
                'date'    => '2026-09-03',
                'summary' => 'R&D now uses inventory: pick the ingredient, and recording it takes it off stock.',
                'changed' => [
                    'The Item field on R&D is now a search over Inventory instead of free text — start typing and pick the ingredient. This means an R&D entry always points at something real, and the name on it stays right even if the ingredient is renamed later.',
                    'Recording an R&D entry now takes the quantity off stock immediately, without waiting for the Owner. The chef has already taken it off the shelf, so the shelf should say so.',
                ],
                'improved' => [
                    'What it costs to buy the ingredient is not changed, so no recipe price moves because of an experiment.',
                    'Editing, rejecting or deleting an R&D entry does not move stock a second time — the same as Sales, Wastage and Production. If the quantity was wrong, correct the shelf with a Tally. The page says so where you edit.',
                ],
                'commits' => '10e6227..HEAD',
            ],
            [
                'version' => '1.13.0',
                'date'    => '2026-09-03',
                'summary' => 'New R&D tab: record what was bought to try out, and the Owner approves or rejects it.',
                'added' => [
                    'An R&D page in the menu. Record what was bought, the invoice number, how many, the unit price, the date and a remark. The total works itself out.',
                    'The Owner sees Approve and Reject on anything waiting, with a running count of what is pending, what that comes to, and what has been approved so far. Every decision records who made it and when.',
                    'Chefs, Admin and the Owner can all add and correct entries — anyone can fix a colleague\'s typo, and the history records who changed what.',
                ],
                'improved' => [
                    'Once the Owner approves a purchase it is locked: nobody can change the price or delete it afterwards. An approval you can edit is not an approval.',
                    'A rejected purchase can be corrected, and saving the correction sends it back to the Owner for another look rather than leaving it rejected with new numbers.',
                ],
                'commits' => '7fdc26e..HEAD',
            ],
            [
                'version' => '1.12.2',
                'date'    => '2026-09-03',
                'summary' => 'Part Timer accounts were shown a refusal page when they opened the site or tapped the logo.',
                'fixed' => [
                    'A Part Timer who went to example.com, or tapped the isms logo at the top of the menu, was sent to the Dashboard — a page that account is not allowed to open — and got a refusal page instead. They now land on the Prep Checklist. Signing in was never affected, which is why nobody reported it; it was found in the web server log.',
                    'The rule for where each account lands after signing in was written out in three places and they had drifted apart. There is one now, so a new kind of account can no longer be sent somewhere it cannot open.',
                ],
                'commits' => '3cbffa1..HEAD',
            ],
            [
                'version' => '1.12.1',
                'date'    => '2026-09-03',
                'summary' => 'The Open order block is off the stock-take form.',
                'removed' => [
                    'The Open order section on a new stock-take. Sheets already recorded with one still show it when you open them — only the way to add a new one is gone.',
                ],
                'commits' => 'f5d39cf..HEAD',
            ],
            [
                'version' => '1.12.0',
                'date'    => '2026-09-03',
                'summary' => 'The tally sheet starts empty too — search, or add a whole category at once.',
                'changed' => [
                    'A new tally check no longer prints all 237 items across eight category cards. It starts empty with a search box, the same as the stock-take sheet.',
                    'Counting a whole shelf is still one tap: with the search box empty, a button for each category — Produce, Meat, Dairy and the rest — puts every item in it on the sheet at once, in shelf order.',
                    'The paragraph of instructions at the top of the tally sheet has been removed.',
                ],
                'removed' => [
                    'The + Add another item button on the stock-take sheet, and the free-text line it added.',
                    'The Note field on the stock-take sheet. Notes already recorded on past sheets are untouched and still show.',
                ],
                'commits' => '66bf55a..HEAD',
            ],
            [
                'version' => '1.11.9',
                'date'    => '2026-09-03',
                'summary' => 'A new stock-take starts empty — search for what moved instead of scrolling the whole list.',
                'changed' => [
                    'The new stock-take sheet no longer prints all 57 pantry lines. It starts empty with a search box: type an ingredient, tap it, and it goes on the sheet ready for an In or Out figure. Counting four things no longer means scrolling past fifty-three that did not move.',
                    'The search now covers the full inventory, not just the pantry list — pantry is inventory. Anything already on the pantry list that is not linked to inventory is still searchable and still marked count only.',
                    'The paragraph of instructions at the top of the sheet has been removed.',
                ],
                'commits' => '2adc9c5..HEAD',
            ],
            [
                'version' => '1.11.8',
                'date'    => '2026-09-03',
                'summary' => 'Add an ingredient straight from the Purchases form when it is not on the shelf yet.',
                'added' => [
                    'On Purchases and Market, if you type an ingredient that is not in Inventory, a small panel now offers to add it — pick a category and a unit, press Add, and it is on the list and selected. You no longer have to abandon a half-filled purchase to go to the Inventory page and start again.',
                    'A newly added ingredient becomes available on every other line of the same form, so buying two of something new works.',
                ],
                'improved' => [
                    'The new ingredient starts at zero quantity and zero cost — the purchase you are writing is what puts the first of it on the shelf and sets what it costs, exactly as it does for an existing ingredient.',
                    'The category and unit lists were typed out in four different places and could disagree. There is now one list behind all of them, so adding a unit is a single change.',
                ],
                'commits' => '4522674..HEAD',
            ],
            [
                'version' => '1.11.7',
                'date'    => '2026-09-03',
                'summary' => 'Admin accounts can now open every screen except the financial Dashboard.',
                'changed' => [
                    'The Admin (support) account now reaches every part of the system except the Dashboard — Sales, Purchases, Market, Recipes, Production, Petty Cash, Invoice Scan, Suppliers, Events, Sections, the Audit Log, Login History, Leave and Feedback are all open, and Stock-take and Tally can now be recorded, not only read. Previously Admin was held to a small set of screens with no money on them, which meant a reported problem often could not be looked at directly.',
                    'The Dashboard stays closed to Admin. So does anything that would let an Admin take over the Owner account: they still cannot reset, delete, rename or change the language of an Owner or another Admin, and they cannot promote anyone to Owner.',
                    'Peer feedback stays anonymous. An Admin opening Feedback sees the same "From a teammate" view everyone else does — only the Owner sees who sent what.',
                ],
                'fixed' => [
                    'An internal engineering document listed every Junior Chef as able to edit Recipes. No account ever could — the document was generated with a placeholder account that slipped through the check. The check is tightened and the document is correct.',
                    'Support Tickets now appears in the Owner menu. The Owner has always been allowed to open that page but there was no link to it — the same kind of gap that hid Inventory last week.',
                    'The Admin menu was a second hand-written list that had to be kept in step with permissions by hand. It has been deleted; every account now gets one menu built from what that account is actually allowed to open, so a link can no longer go missing or lead to a refusal page.',
                ],
                'commits' => '2b89a33..HEAD',
            ],
            [
                'version' => '1.11.6',
                'date'    => '2026-09-02',
                'summary' => 'Part Timer accounts no longer see Profile, Leave or Feedback.',
                'fixed' => [
                    'A Part Timer was still shown Leave and Feedback in the menu even though the account was never allowed to open either — clicking one gave a refusal page. Those two links were written into the sidebar by hand and were not checking permission at all. The whole menu now checks, top to bottom, and the test that guards it covers the lower section as well as the main list.',
                ],
                'changed' => [
                    'Part Timer accounts no longer have a Profile page. Note this also means they cannot change their own password — an Owner or Admin needs to set it for them from the Users page.',
                ],
                'commits' => '9292cb1..HEAD',
            ],
            [
                'version' => '1.11.5',
                'date'    => '2026-09-02',
                'summary' => 'A Part Timer account type, and Riley can now work with recipes.',
                'added' => [
                    'There is a new Part Timer role for casual kitchen help. It sees the Prep Checklist and Prep Overview, Stock-take, Wastage and Inventory — and nothing else. No leave, no peer feedback, no takings, no supplier bills, no recipe costings. Set it from the Users page like any other role.',
                    'On Inventory a Part Timer can key figures in but not take them away: they can add an ingredient that is missing and correct a quantity that is wrong, but they cannot rename an ingredient, change what it costs, or delete one. Wastage they can record and correct in full.',
                    'Riley can now open and edit Recipes. This is set on his account specifically — the other junior chefs are unchanged.',
                ],
                'commits' => 'f3e18a4..9292cb1',
            ],
            [
                'version' => '1.11.4',
                'date'    => '2026-09-01',
                'summary' => 'Nine pages the Owner was allowed to open were missing from the Owner’s own menu.',
                'fixed' => [
                    'The Owner’s sidebar was a separate, hand-kept list, and it had fallen behind: Inventory, Sales, Purchases, Market, Production, Wastage, Suppliers, Events and Login History were all permitted to the Owner and none of them had a link. The pages worked the whole time — there was simply no way to reach them without knowing the web address. Every one of them is now in the menu.',
                    'The cause was having two menus to keep in step by hand. There is now one menu, built from who is allowed to see what, so a page can no longer go missing from it. The Owner’s menu is in a slightly different order as a result, and the first item reads “Dashboard” rather than “Overview”.',
                ],
                'improved' => [
                    'The support account can now open Inventory, and correct a figure on it. Support is normally kept away from anything showing money, and this page does show stock cost and value — it is a deliberate exception, because a wrong stock figure is one of the most common things reported and it cannot be looked into blind. Everything else to do with money stays closed to that account: no takings, no supplier bills, no petty cash, no recipe costings and no audit log.',
                ],
                'commits' => 'f55bf47..f3e18a4',
            ],
            [
                'version' => '1.11.3',
                'date'    => '2026-09-01',
                'summary' => 'The sign-in screen now appears in Bahasa Indonesia for whoever set that language.',
                'improved' => [
                    'Staff who chose Bahasa Indonesia were still shown an English sign-in screen — the system could not know your language until after you had signed in, which is too late for the page asking you to sign in. It now remembers your choice on that device, so the sign-in screen greets you in your own language from the second time onwards. It is the first screen of the day, and it was the one screen the setting never reached.',
                    'The sign-in error messages are translated too — a wrong password, a missing email, and the “too many attempts, try again in N seconds” lockout all now appear in Bahasa Indonesia, along with the countdown on the button itself.',
                    'The setting follows the device, so if two people share a phone, whoever signed in last sets the language of the sign-in screen. Changing your language in Profile still works exactly as before.',
                ],
                'commits' => '771bd18..f55bf47',
            ],
            [
                'version' => '1.11.2',
                'date'    => '2026-09-01',
                'summary' => 'The support account can now open the kitchen screens, so a reported problem can be looked at rather than guessed at.',
                'improved' => [
                    'The support account could previously only see support tickets and the user list, which made most reported problems impossible to look into — the person helping you could not open the screen you were describing. It can now open the Prep Checklist and Prep Overview, Stock-take, Tally and the Daily Report, and tick a prep task in order to reproduce a fault.',
                    'It still cannot see anything to do with money — no takings, no costs, no stock values, no petty cash, no supplier bills and no audit log — and it cannot see leave applications or peer feedback. It also cannot record a stock-take or a tally, because those change real stock figures. Where it does tick a prep task, its name appears on the row like anyone else’s, so nothing happens anonymously.',
                ],
                'commits' => 'f0ca219..771bd18',
            ],
            [
                'version' => '1.11.1',
                'date'    => '2026-09-01',
                'summary' => 'The monthly peer-feedback email now arrives even in a month when nobody gave feedback.',
                'improved' => [
                    'The Owner’s monthly peer-feedback email used to be skipped entirely when no ratings had been submitted that month. Since almost every month so far has been empty, that meant no email — which looks exactly the same as an email that failed to send. It now always arrives: either the full report, or a one-line note saying nothing was submitted. Silence now means something is actually wrong.',
                    'That email is also retried if it fails on the first attempt. It is sent once a month, so a momentary hiccup with the mail provider used to lose the whole month’s report until the next one came round. The rest of the system already retried its emails this way.',
                    'The nightly database backup now also runs on the server itself, once an hour, copying to separate offsite storage. Until now every backup was taken by one laptop, so a laptop that was off, asleep or away meant no backup was taken at all — there is a real 26-hour gap on record. The server copy runs whether anyone’s machine is switched on or not.',
                    'Scheduled jobs now write down what they did. Previously a job that finished cleanly left no record of which path it took, so answering “did that email actually go out?” meant piecing it together from the database.',
                ],
                'commits' => '5e4e22a..f0ca219',
            ],
            [
                'version' => '1.11.0',
                'date'    => '2026-08-30',
                'summary' => 'Invoice Scan (beta): photograph a supplier invoice on the website and send it to Bukku as a bill, without leaving the website.',
                'added' => [
                    'A new Invoice Scan page for the Owner and Head Chefs. Upload a photo or PDF of a supplier invoice, and the system reads the supplier, the invoice number, the date and every line off the page and fills a form in for you.',
                    'Nothing reaches the accounts on its own. The scan only fills the form; you check the numbers against the paper, correct anything it misread, and press Send to Bukku. The bill then appears in Bukku with the photo attached to it, and the bill reference comes straight back to the page.',
                    'Each line can be pointed at a stock product, which files it against inventory the way a hand-entered bill would. Lines left unmapped go to the general expense account instead.',
                    'The page keeps every scan, so you can see what was read, who checked it, and which bill it became. An invoice that has already been sent cannot be sent a second time.',
                ],
                'improved' => [
                    'This replaces the round trip that used to go out through a chat app and come back the same way. Everything now happens in one place, on a screen big enough to actually read the lines, and the record is something anyone with access can find later rather than a message in one person’s chat history.',
                    'Prep work now outlives the person who did it. Deleting a team member used to delete whatever they had ticked off the prep checklist along with them — including the same day’s — so finished work could suddenly read as never done. Their ticks now stay on the record and simply stop carrying a name, which is what every other kitchen record already did.',
                    'Tightened up how new accounts are created, so an account can never come into being without a role. Nothing visible changes; it closes off a way a half-set-up account could have appeared with access nobody chose to give it.',
                ],
                'commits' => 'ea45cc1..5e4e22a',
            ],
            [
                'version' => '1.10.56',
                'date'    => '2026-08-29',
                'summary' => 'The five long-standing test failures are gone; the automated checks pass in full for the first time.',
                'improved' => [
                    'The system has a suite of 99 automated checks that run before anything ships. Five had been failing for a long time, which is corrosive: once some red is normal, a real break hides among it. All five turned out to be the checks themselves being wrong rather than the system. They tested behaviour this app deliberately does not have, or depended on the settings of one particular developer machine. None was a fault anyone using the system could ever have seen, and nothing about how the system behaves was changed to make them pass.',
                ],
                'commits' => '8c9d132..ea45cc1',
            ],
            [
                'version' => '1.10.55',
                'date'    => '2026-08-29',
                'summary' => 'The appliance-check feature is deleted, not just hidden.',
                'removed' => [
                    'Last release took the Appliances block off the screen. This one removes the machinery behind it as well -- the pages, the upload, the list of which appliances belong to which section, and the table it saved to. Nothing was lost, because it never saved anything: not one appliance photo was ever recorded in the whole time it existed. Prep tasks are now the only place anything is ticked off, which is how the kitchen was already working.',
                ],
                'commits' => 'b613a0f..8c9d132',
            ],
            [
                'version' => '1.10.54',
                'date'    => '2026-08-29',
                'summary' => 'The Appliances block is gone from the checklist -- it asked for the same photos your tasks already do.',
                'removed' => [
                    'The Appliances list has been taken off the prep checklist and the Prep Overview. It asked for a photo of the exhaust fan, gas, aircond, oven and fryer in Others, and of the chiller in the Frying, Hot and Pass sections -- all of which are already prep tasks with their own photo. Chefs were being asked for the same picture twice. Nothing is lost: no appliance photo was ever recorded, so there is no history to keep. Your tasks are unchanged and remain the one place anything is ticked off.',
                ],
                'commits' => '8bfcab2..b613a0f',
            ],
            [
                'version' => '1.10.53',
                'date'    => '2026-08-29',
                'summary' => 'Turning OFF "Requires photo proof" on a task now sticks. It never did before.',
                'improved' => [
                    'On the Sections page you could tick "Requires photo proof" on a task, but unticking it did nothing -- save the task and the setting came straight back on. That is why every one of the ten tasks in the kitchen demanded a photo and none could be set to a simple tick-off. The box now works both ways.',
                    'Existing tasks are untouched and still ask for a photo. Edit any task and untick the box and it will now stay unticked.',
                ],
                'commits' => '2c5e6df..8bfcab2',
            ],
            [
                'version' => '1.10.52',
                'date'    => '2026-08-29',
                'summary' => 'The sun rise egg now uses a whole slice of sourdough.',
                'improved' => [
                    'The recipe asked for 0.6 of a slice. That was the honest conversion of the 0.05 of a packet it used to say, but it is not how anyone cooks it, and the Owner has confirmed it is one whole slice. Selling the dish now takes one slice off the sourdough stock instead of six tenths. The plate cost is unchanged, because sourdough is currently carried at RM0.00.',
                ],
                'commits' => '3fee47e..2c5e6df',
            ],
            [
                'version' => '1.10.51',
                'date'    => '2026-08-29',
                'summary' => 'Chicken sausage and sourdough are counted in pieces, so recipes can ask for them the way the kitchen talks.',
                'improved' => [
                    'Chicken sausage and sourdough were held in packets, which meant a recipe could not simply ask for two sausages or a slice of bread. Both are now counted in pieces: 12 packets of chicken sausage became 60 pieces, and 5 packets of sourdough became 60 slices, using 5 sausages and 12 slices to a packet.',
                    'The money did not move. The price per packet was divided by the pack size at the same time as the count was multiplied, so the stock is worth exactly what it was worth before -- chicken sausage stays at RM201.60 -- and the Inventory Value figure on the Overview is unchanged.',
                ],
                'added' => [
                    'One thing to look at. The "sun rise egg" recipe asked for 0.05 of a packet of sourdough, which converts to 0.6 of a slice -- the same bread as before, but it was probably meant to be one whole slice. Changing it is one field on the Recipes page.',
                    'The plain SAUSAGE item is sold by the kilo, not the packet, and is unchanged at 10 kg. Only the chicken sausage was meant to move.',
                ],
                'commits' => '1b7829b..3fee47e',
            ],
            [
                'version' => '1.10.50',
                'date'    => '2026-08-29',
                'summary' => 'Appliance checks appear on the checklist for the first time -- the section names they looked for never existed.',
                'improved' => [
                    'The system kept a second kind of check, per appliance, that nobody had ever seen. It looked up which appliances a section has by the section name, and the three names it looked for -- "Hot Kitchen", "Cold Storage & Fridge" and "Prep & Mise en Place" -- are not names this kitchen uses. Every lookup came back empty, so no appliance ever appeared and not a single appliance check was recorded in four months. The list now uses the real sections: Frying, Hot and Pass Section each show a Chiller, and Others shows Exhaust Fan, Gas, Aircond, Oven and Fryer. Floor and Pantry have no appliances.',
                    'Renaming a section no longer breaks the list over a capital letter or a stray space. An exact-match rename to something else does still empty it, so if appliances disappear from a section, check whether it was renamed.',
                ],
                'added' => [
                    'Please note these appliance rows sit alongside the prep tasks that already cover the same equipment -- the "Chiller Check" tasks and the five "Turn off ..." tasks. Until one side is removed, those items are photographed twice.',
                ],
                'commits' => '741861f..1b7829b',
            ],
            [
                'version' => '1.10.49',
                'date'    => '2026-08-28',
                'summary' => 'Prep tasks are shared work now: anyone can tick one, and the system records who did.',
                'improved' => [
                    'Sections are no longer handed out one chef at a time. Everyone in the kitchen -- Junior Chefs, Head Chefs and the Owner -- opens the same Prep Checklist and sees every section on it. Whoever is standing in front of a task can tick it or upload its photo. This was the real reason the checklist went unused: of the six sections set up, three were assigned to nobody, so nobody could tick them, and a chef who had not been given a section signed in to an empty page.',
                    'Every completed task and appliance photo now shows the name of the person who did it, taken from the account they signed in with. Nobody types a name and nobody can put someone else down for work they did not do. The Owner sees the same names on Prep Overview, next to each task and along the top of each section.',
                    'If a task has already been ticked today and someone does it again -- a better photo, say -- the record updates in place and the name moves to whoever just did it. One task, one day, one line.',
                ],
                'removed' => [
                    'The "Assign to Chef" dropdown is gone from the Sections page, and the Section column and picker are gone from the Users page. They decided nothing once anyone could do any task, and a control that changes nothing is worse than no control. Past assignments are still on record.',
                ],
                'commits' => '44619e8..741861f',
            ],
            [
                'version' => '1.10.48',
                'date'    => '2026-08-28',
                'summary' => 'A spring clean: unused parts of the system taken out, with no change to anything the kitchen uses.',
                'removed' => [
                    'The "Viewer" role has been taken out. It was built early on as a read-only account whose access you granted page by page, and it was never once used -- no Viewer account was ever created and no page was ever granted. It has been removed from the role list on the Users page, along with the box of page tick-marks that only appeared when you chose it. Nobody loses access, because nobody had it.',
                    'Two sets of made-up practice data that had been left in the codebase -- invented banquet bookings and a batch of fake purchases and sales -- have been deleted. Nothing referred to them and nothing loaded them; the training accounts get their data by copying the real system instead.',
                ],
                'improved' => [
                    'Roughly four hundred lines of code that nothing called have been deleted, including two starter files left behind by the original setup and a piece of styling no page used. Less to read is less to get wrong later.',
                    'Two development-only tools that came with the original project setup have been uninstalled. They were only ever used to create the app in the first place and have not been needed since.',
                ],
                'commits' => 'fdf5594..44619e8',
            ],
            [
                'version' => '1.10.47',
                'date'    => '2026-08-28',
                'summary' => 'The side menu now reads in English for everyone, including Bahasa Indonesia accounts.',
                'improved' => [
                    'The menu down the side of every page is back to English on all accounts. It is the vocabulary the team uses out loud -- when someone says "open Stock-take", it should say Stock-take on every screen in the kitchen, whichever language that person has chosen. The pages themselves are still fully translated, so an account set to Bahasa Indonesia reads the forms, headings, buttons and hints in Bahasa Indonesia as before; only the signposts are fixed.',
                ],
                'commits' => 'e5cb641..fdf5594',
            ],
            [
                'version' => '1.10.46',
                'date'    => '2026-08-28',
                'summary' => 'The last four pages are translated, and the Support page no longer promises a file size it will refuse.',
                'improved' => [
                    'Leave, Feedback, Support and About are now in Bahasa Indonesia for accounts set to it. Together with the last release, every page an Indonesian-speaking account can open is now translated.',
                    'The Support page said attachments could be up to 1 GB, but the system has only accepted 128 MB since an earlier release. Anyone attaching a large video would have waited through the whole upload only to be refused at the end. The page now states the real limit.',
                ],
                'commits' => 'e72698a..e5cb641',
            ],
            [
                'version' => '1.10.45',
                'date'    => '2026-08-28',
                'summary' => 'Staff set to Bahasa Indonesia now see the system in Bahasa Indonesia.',
                'improved' => [
                    'An account set to Bahasa Indonesia only ever got one translated page -- the prep checklist. Every other screen, including the menu down the side of every page, stayed in English. That is fixed: the menu, the Stock-take sheet and its history, the Tally check sheet and its history, Log Production, and the whole Profile page are now translated, along with the buttons, column headings and hint text on each.',
                    'The Profile page was a particular case: it had been built ready for translation, but the Indonesian wording was never written, so it quietly showed English. It now reads in Bahasa Indonesia throughout.',
                    'Accounts left on English are completely unaffected.',
                ],
                'commits' => '9ca380c..e72698a',
            ],
            [
                'version' => '1.10.44',
                'date'    => '2026-08-28',
                'summary' => 'Buttons and boxes are easier to hit with a thumb on a phone.',
                'improved' => [
                    'Buttons, text boxes and tick boxes were sized for a mouse pointer, coming out around a third smaller than the minimum Apple and Google recommend for a finger. Everything you tap on a phone is now comfortably thumb-sized, including the small close crosses on pop-ups, which were especially easy to miss. Nothing changes on a computer.',
                    'The three-dot menu on a row of the Users page used to open off the bottom of the screen when the row was low down, with no way to scroll to it. It now opens upward when there is no room below.',
                ],
                'commits' => '37829f9..9ca380c',
            ],
            [
                'version' => '1.10.43',
                'date'    => '2026-08-28',
                'summary' => 'Pop-up forms can now be finished on a phone.',
                'improved' => [
                    'On a phone, a long pop-up form -- Log Sale, Log Purchase with several lines, Add Recipe -- ran off the top and bottom of the screen with no way to scroll it, so the Save button simply could not be reached. Every pop-up in the system now fits the screen and scrolls inside itself. This affected most of the pop-ups in the app; the dashboard ones already behaved correctly.',
                    'The search box on the Stock-take count sheet now sizes itself to the screen instead of a fixed width that only just fitted a phone.',
                ],
                'commits' => '026ce4c..37829f9',
            ],
            [
                'version' => '1.10.42',
                'date'    => '2026-08-28',
                'summary' => 'Fixes the New stock-take page, which would not open after the last release.',
                'improved' => [
                    'The New stock-take page returned a server error instead of loading. It was a mistake in how the ingredient list was written into the page in 1.10.41, introduced within the hour and fixed here. The page is back to normal and nothing that had already been recorded was affected. A check that opens the page has been added, so this particular fault cannot reach the kitchen unnoticed again.',
                ],
                'commits' => 'ee0f6f3..026ce4c',
            ],
            [
                'version' => '1.10.41',
                'date'    => '2026-08-28',
                'summary' => 'The count sheet has one search box again instead of two.',
                'improved' => [
                    'Last release put an "Add from inventory" field next to the existing search box, which left two near-identical boxes on the same card and no obvious way to tell which one to type into. There is now a single box. It filters the count sheet as before, and anything it cannot find there it offers just below as ingredients you can tap to add and count. Searching for "onion" will filter the sheet to Red onion and still offer Spring onion underneath, so you never have to know in advance whether something is already on the sheet.',
                ],
                'commits' => 'e34bd1d..ee0f6f3',
            ],
            [
                'version' => '1.10.40',
                'date'    => '2026-08-28',
                'summary' => 'Chefs can now count any ingredient, not just the ones already printed on the sheet.',
                'added' => [
                    'The Stock-take count sheet has an "Add from inventory" box that searches every ingredient the kitchen carries. Pick one and it drops onto the sheet with its unit and current stock, ready for In and Out -- and it moves stock properly, exactly like the items already on the sheet. Before this, an ingredient missing from the sheet could not be counted at all without a manager stopping to edit the item list on another page first.',
                    'An ingredient added this way stays on the sheet for next time, so the list quietly completes itself as the kitchen uses it. Counting the same thing twice does not create a duplicate.',
                ],
                'commits' => '4469fcc..e34bd1d',
            ],
            [
                'version' => '1.10.39',
                'date'    => '2026-08-28',
                'summary' => 'Searching the count sheet for something that is not on it now explains why.',
                'improved' => [
                    'Searching the Stock-take count sheet for an ingredient that has not been added to it -- "spring onion", for example -- used to empty the table and say nothing, which looked like the search was broken. It now tells you no item on the sheet matches what you typed, and points managers straight to the page where they can add it and link it to inventory so it moves stock. Junior chefs are told to ask a manager instead. The count sheet is a shortlist of what gets walked and counted, not the full ingredient list, so items do need adding to it once.',
                ],
                'commits' => '98bdcb9..4469fcc',
            ],
            [
                'version' => '1.10.38',
                'date'    => '2026-08-27',
                'summary' => 'Deleting a staff account now tells you exactly what will be lost first.',
                'improved' => [
                    'The confirmation for deleting a staff account used to ask "Are you sure?" without saying what actually goes. It now explains up front that the account\'s own records go with it, and asks you to download anything you want to keep before you continue. Kitchen and financial history -- purchases, production, daily reports, sections and the audit log -- is never deleted with a person; it stays on file and simply stops being attributed to them.',
                ],
                'commits' => '356b75a..98bdcb9',
            ],
            [
                'version' => '1.10.37',
                'date'    => '2026-08-27',
                'summary' => 'Every page now loads noticeably lighter, especially on a phone.',
                'improved' => [
                    'The sign-in screen was the only page built on a heavyweight page framework, and the cost of it was being paid on every single page of the system -- roughly 317 KB of extra code downloaded each time anyone opened anything. The sign-in screen has been rebuilt to work the same way as the rest of the system, so that download is now gone entirely. Pages should feel quicker, most noticeably on a phone or a slow connection. Signing in looks and behaves exactly as before.',
                ],
                'commits' => '0f536a6..356b75a',
            ],
            [
                'version' => '1.10.36',
                'date'    => '2026-08-27',
                'summary' => 'The item search box on the Stock-take count sheet is bigger.',
                'improved' => [
                    'The "Search items" box on the Stock-take count sheet is now the same size as the search field on Inventory -- it was noticeably smaller before.',
                ],
                'commits' => '59339f9..HEAD',
            ],
            [
                'version' => '1.10.35',
                'date'    => '2026-08-27',
                'summary' => 'A missing Bahasa Indonesia translation on the prep checklist is filled in.',
                'improved' => [
                    'The "Mark Done" button on the prep checklist now shows in Bahasa Indonesia ("Tandai Selesai") instead of falling back to English -- the only string on that page that had been missed.',
                ],
                'commits' => 'c883b4f..HEAD',
            ],
            [
                'version' => '1.10.34',
                'date'    => '2026-08-27',
                'summary' => 'A cleanup tool for audit-log entries written before last release\'s privacy fix.',
                'improved' => [
                    'An internal maintenance command can now clean up old audit-log entries that predate the password-log fix in 1.10.33, without deleting the entries themselves.',
                ],
                'commits' => 'e303219..HEAD',
            ],
            [
                'version' => '1.10.33',
                'date'    => '2026-08-27',
                'summary' => 'A data-privacy pass: uploaded photos are now kept private, and old records don\'t pile up forever.',
                'improved' => [
                    'Purchase and market-purchase receipts, and prep/appliance check photos, are now stored privately and only reachable by someone with permission to view that record -- previously they were reachable by anyone with the link.',
                    'Changing a password no longer writes the password itself into the audit log.',
                    'Login history now clears out automatically after 6 months instead of growing forever.',
                ],
                'commits' => '79eae91..HEAD',
            ],
            [
                'version' => '1.10.32',
                'date'    => '2026-08-27',
                'summary' => 'Sections can now be assigned to specific days of the week.',
                'added' => [
                    'A section can be marked active on only certain days -- pick any combination of Monday through Sunday from the Edit Section screen. Leaving every day unpicked keeps the old always-on behavior.',
                ],
                'commits' => '800e174..HEAD',
            ],
            [
                'version' => '1.10.31',
                'date'    => '2026-08-27',
                'summary' => 'The Inventory PDF is rebuilt the same way as Purchases -- one page per supplier.',
                'improved' => [
                    'Exporting Inventory to PDF now groups every ingredient by the supplier it was most recently bought from, one supplier per page, ending in a total for that supplier. Ingredients never bought through a supplier order -- market purchases, produced dishes -- land together on a trailing "No supplier" page instead of being left out.',
                ],
                'commits' => 'c975379..HEAD',
            ],
            [
                'version' => '1.10.30',
                'date'    => '2026-08-27',
                'summary' => 'The Purchases PDF is rebuilt around suppliers -- one page each, ingredient by ingredient.',
                'improved' => [
                    'Exporting Purchases to PDF now groups every ingredient bought by supplier, one supplier per page, ending in a total for that supplier -- built for handing a page to each supplier rather than reading one long list of orders.',
                ],
                'commits' => '0a6625c..HEAD',
            ],
            [
                'version' => '1.10.29',
                'date'    => '2026-08-27',
                'summary' => 'Ingredients are now picked by typing a name, not scrolling a dropdown; Head Chefs can edit sections and tasks.',
                'added' => [
                    'A Head Chef can now open the section & task editor directly from Prep Overview, instead of needing the Owner to make every change.',
                ],
                'improved' => [
                    'Choosing an ingredient on Purchases, Market Purchases, and the Stock-take catalog is now a type-to-search field instead of a long dropdown -- much faster on a phone with a hundred-plus ingredients in the list.',
                ],
                'commits' => '6104c90..HEAD',
            ],
            [
                'version' => '1.10.28',
                'date'    => '2026-08-26',
                'summary' => 'Production quantities are now whole dishes only.',
                'improved' => [
                    'How many were made is now entered as a whole number -- 1, 2, 3 and so on. Half a dish is not something the kitchen can cook, and allowing it only invited a stray decimal point to take an odd amount of ingredients off the shelf. Typing 0 next to a dish means none were made and is treated the same as leaving it blank, so tabbing down the list and zeroing what was not cooked works exactly as you would expect.',
                ],
                'commits' => '40a1fc6..HEAD',
            ],
            [
                'version' => '1.10.27',
                'date'    => '2026-08-26',
                'summary' => 'Production has been rebuilt around your recipes. A chef records the dishes they cooked and how many of each, and the system works out every ingredient that came off the shelf.',
                'improved' => [
                    'Logging production used to mean listing inventory items one at a time, each with its own quantity and cost -- for a dish with ten ingredients, that was ten lines to key in and ten chances to mistype. Now the screen lists your recipes, and you simply type how many of each dish were made. The recipe already knows what goes into it, so the ingredients work themselves out.',
                    'Everything cooked in a day goes in as one entry. Type a quantity next to each dish worked on -- three of one, two of another -- and save once. Each dish is still recorded separately in the history, so any one of them can be removed later without disturbing the rest.',
                    'Before saving, the screen shows exactly what will come off the shelf, and marks in red anything there is not enough stock for. If an ingredient is used by two of the dishes being logged, it is shown as one combined amount. Split across two lines it can look like there is plenty of each, when in fact the shelf cannot cover both.',
                    'Both the recipe list and the production history can be searched, so finding a dish among twenty-five does not mean scrolling. Anything already typed against a dish stays put when the search is narrowed.',
                    'A recipe can now name the finished dish it produces, set on the Recipes page. When it does, cooking a batch turns raw ingredients into that finished dish, and selling it takes the finished dish off stock. Recipes without one carry on exactly as before, so nothing changes until you choose to set it up dish by dish.',
                    'Cooking with an ingredient no longer changes what that ingredient is recorded as costing. Previously a cost typed on a production line was written back over the inventory record, so one mistyped figure could quietly reprice that ingredient everywhere it was used.',
                    'The stock-take sheet can now be searched the same way, for finding an item quickly on a long sheet.',
                ],
                'commits' => '53e4635..HEAD',
            ],
            [
                'version' => '1.10.26',
                'date'    => '2026-08-26',
                'summary' => 'The stock-take sheet has been rebuilt to work off live stock: what you bring in and take out now changes the inventory figures directly.',
                'improved' => [
                    'The stock-take sheet used to be the paper sheet copied onto a screen -- you carried the closing figure from the last sheet forward by hand, typed a closing figure at the end, and only the Out column actually changed anything. The columns are now Current stock, In, Out and Balance. Current stock is whatever the system holds for that item at that moment; you do not type it, so it cannot be thrown off by a copying mistake. You enter only what came in and what went out. The balance updates as you type and becomes the new stock figure when you save.',
                    'Stock now moves both ways from a count. Deliveries and anything else arriving are entered as In and are added to stock. Before this the In column was written down but changed nothing, so the system drifted further below what was really on the shelves every time something arrived.',
                    'A line that is not connected to an inventory item is now marked "count only" on the saved sheet. Those lines record a count but move no stock, and previously there was no way to tell them apart from lines that did -- so a count could look recorded while the stock figure never moved. Which items are connected is set on the Manage items screen.',
                    'The sheet now records only the items that actually moved, instead of every item on the list. For a full picture of what is on every shelf, use the Tally check.',
                    'The Prep date column has been taken off the sheet. Dates already recorded are kept.',
                ],
                'commits' => 'd6fe752..HEAD',
            ],
            [
                'version' => '1.10.25',
                'date'    => '2026-08-23',
                'summary' => 'Sensible limits on file attachments, and the training accounts no longer hold a copy of real staff and financial records.',
                'improved' => [
                    'The training (demo) logins run against a separate practice copy of the system. That copy was being made by duplicating everything from the real one -- which meant it also held the real staff accounts, the full record of financial changes, real sales and purchases, and the sign-in history. Since a training login is meant to be handed to someone, that was more than it should ever have contained. The practice copy now leaves all of that behind and keeps only the day-to-day kitchen data that makes training useful. Staff passwords are scrambled in the practice copy as well.',
                    'File attachments had no sensible ceiling. A leave application or peer feedback allowed files up to 2 GB each with no limit on how many, and a support report allowed 1 GB. The largest file anyone has ever actually sent is 30 MB. Leave and feedback are now up to 5 files of 50 MB each, and support reports up to 128 MB -- comfortably more than anyone needs, while making it impossible for one person to fill the server disk and take the system down.',
                    'A setting that keeps training activity from touching the real system was described in our notes but had never actually been switched on. It was working anyway, by luck of the order things run in, rather than because it was configured. It is now set explicitly, in a place that cannot be lost when the server is reconfigured.',
                ],
                'commits' => 'd64e616..HEAD',
            ],
            [
                'version' => '1.10.24',
                'date'    => '2026-08-23',
                'summary' => 'The sign-in record now shows where each sign-in came from, and lists each one only once.',
                'improved' => [
                    'The sign-in history had a column for the address a person signed in from, but it was never being filled in -- every entry was blank. It now records that address, so if a sign-in ever looks wrong, there is something to check it against.',
                    'The system sits behind a delivery network that relays every visit, and it had been reading that relay as the visitor. It now reads the real address of whoever is actually connecting. This also fixes the limits that stop someone hammering the sign-in or support forms: those limits count attempts per address, and until now every visitor was being counted as the same handful of relay addresses.',
                    'Each sign-in was being written into the history twice, so the list was double the length it should have been. Every sign-in is now recorded once. Existing duplicate entries from before this update are still there and are harmless.',
                ],
                'commits' => 'd64e616..HEAD',
            ],
            [
                'version' => '1.10.23',
                'date'    => '2026-08-23',
                'summary' => 'Added a security policy header that blocks the browser from loading scripts or styles from anywhere unexpected.',
                'improved' => [
                    'Every page now tells the browser exactly which sources it is allowed to load code, fonts and styling from. This closes off a common way attackers try to hijack a page -- by getting the browser to run something from a server that has nothing to do with this system.',
                ],
                'commits' => 'd64e616..HEAD',
            ],
            [
                'version' => '1.10.22',
                'date'    => '2026-08-22',
                'summary' => 'The "isms" name is now written in the same handwriting everywhere.',
                'improved' => [
                    'The handwritten "isms" name at the top of the sidebar and on the sign-in page was set in a different handwriting font to the one used on this page and on the maintenance screen. They now all use the same one, so the brand looks consistent wherever it appears.',
                    'The page was also asking the browser for a weight of that handwriting it had never downloaded, so the browser was thickening the letters itself. It now loads the correct weight.',
                ],
                'commits' => 'd64e616..HEAD',
            ],
            [
                'version' => '1.10.21',
                'date'    => '2026-08-22',
                'summary' => 'Corrected the engineering handbook, and fixed the update count on this page.',
                'improved' => [
                    'The internal handbook that documents how the system works had fallen behind two weeks of changes. Several notes still said a stock-take never touches live stock, which stopped being true when the Out column was wired up to deduct from inventory. Those notes now describe what the system actually does. Nothing in the kitchen app itself changed.',
                    'The "Total updates" figure on this page was one behind the real history. It is correct again.',
                ],
                'commits' => 'e6f314f..HEAD',
            ],
            [
                'version' => '1.10.20',
                'date'    => '2026-08-22',
                'summary' => 'Removed the Owner-level demo account.',
                'removed' => [
                    'The training account "Demo Account" (demo@example.test) has been deleted. It was one of three practice logins that run against a separate copy of the data, so nothing it ever did touched the real kitchen records. Its history in the audit log stays, still named, so any past entry can still be traced.',
                    'The two remaining practice logins — the Head Chef and Junior Chef ones — are unchanged and still work. There is no longer an Owner-level practice login, so there is no way to demonstrate the financial dashboard without using a real Owner account.',
                ],
                'commits' => 'd4a2149..HEAD',
            ],
            [
                'version' => '1.10.19',
                'date'    => '2026-08-22',
                'summary' => 'Tidied the sign-in page.',
                'removed' => [
                    'The small grey line under the sign-in box that read "Inventory, Sales and Management System · the region" has been taken off. The name is already on the card above it, so the line only repeated itself. Signing in works exactly as before.',
                ],
                'commits' => 'b4ab937..d4a2149',
            ],
            [
                'version' => '1.10.18',
                'date'    => '2026-08-22',
                'summary' => 'Removed a half-finished sign-up route that never worked, and the empty table on the Users page that went with it.',
                'removed' => [
                    'The Users page had a "Pending Invitations" table. It could only ever be empty: the part of the system that would have filled it was never finished, so in the whole life of the app not one row was ever written to it. An empty table that can never fill is just something to wonder about, so it is gone. Adding a team member works exactly as before — the Add Team Member button emails the new person their login.',
                    'Two web addresses for accepting an invitation have been taken off the system. They were the other half of the same unfinished idea and they could never have worked, but unlike the rest of the app they did not require you to be signed in. Nothing could be done with them, and now they are not there at all. This is housekeeping, not a response to any incident.',
                ],
                'commits' => '28c884c..b4ab937',
            ],
            [
                'version' => '1.10.17',
                'date'    => '2026-08-21',
                'summary' => 'Junior chefs can now see the inventory. Only the Owner and Head Chefs can change it.',
                'added' => [
                    'Junior chefs can now open the Inventory page and see what is on hand. Until now it was closed to them, which meant a chef prepping a section had no way of checking stock without asking a Head Chef. Inventory now appears in their sidebar.',
                    'They can look, not touch. The buttons for adding an ingredient, editing one, or removing one do not appear for a junior chef, and the PDF download stays with the Owner and the Head Chefs. Nothing about who can change stock has altered.',
                    'The page shows what each ingredient costs and what the stock is worth, so junior chefs will see those figures too. Flagging it plainly rather than leaving it to be discovered.',
                ],
                'removed' => [
                    'The Users page had a tick box for granting Inventory access to a viewer account. Inventory is open to the whole kitchen now, so that box decided nothing whether ticked or not, and it has been taken out. The other page tick boxes still work as before.',
                ],
                'commits' => '1b03036..28c884c',
            ],
            [
                'version' => '1.10.16',
                'date'    => '2026-08-21',
                'summary' => 'The retired Kitchen item list is now properly gone, not just hidden.',
                'removed' => [
                    'When the Kitchen stock-take section was retired earlier today, its list of 64 item names was left sitting in the database rather than removed. Nothing showed them, which also meant nobody could tidy them up by hand — they were simply stuck there. They have now been deleted for good.',
                    'Nothing that was ever recorded is affected. Both stock-takes on record are Pantry counts and neither refers to any of the removed names; the checks were run against the live system before anything was deleted. The Pantry list of 54 items is untouched, and ingredient stock figures were never involved.',
                ],
                'commits' => '92dd164..HEAD',
            ],
            [
                'version' => '1.10.15',
                'date'    => '2026-08-21',
                'summary' => 'Nothing changes in the system itself — this one tightens how updates get checked before they reach you.',
                'improved' => [
                    'There is no change to any screen, figure or button in this release. It is recorded only because every version here maps to real work in the code, and leaving it out would make the count at the top of this page wrong.',
                    'What changed is the checklist used when publishing an update. The step covering who is allowed into which part of the system was written as a reminder to check rather than as a check that actually runs, so it was easy to skip. It is now a real test that reports, for each role in the kitchen, whether that person can get in.',
                    'This is not theoretical. Earlier today the maintenance screen was reported as blocking the Admin. Running the test showed the Owner and the Admin were both allowed in all along, and the true problem was something else: anyone already signed in had no way to leave that screen. Guessing would have fixed the wrong thing.',
                ],
                'commits' => '92dd164..HEAD',
            ],
            [
                'version' => '1.10.14',
                'date'    => '2026-08-21',
                'summary' => 'The maintenance screen no longer traps you with a link that does nothing.',
                'improved' => [
                    'While the system is under maintenance, everyone except the Owner and the Admin sees a holding page. That page invited you to sign in, but the link could never work: only someone already signed in ever reaches that page, and the sign-in page turns signed-in people away, sending them back to the page they came from, which is the holding page again. Pressing the link simply returned you to where you started, with nothing to explain why.',
                    'The holding page now tells you which account you are signed in as and gives you a Sign out button that works, so you can come back as the Owner or the Admin. If you are not signed in at all, you still get the sign-in link.',
                    'The wording said to sign in if you are the owner. Admins can use the system during maintenance as well, and always could, so the page now says Owner or Admin.',
                ],
                'commits' => '9e79311..HEAD',
            ],
            [
                'version' => '1.10.13',
                'date'    => '2026-08-21',
                'summary' => 'The stock-take sheet drops the Kitchen section nobody used.',
                'removed' => [
                    'The stock-take page offered a choice between Pantry and Kitchen. Kitchen was never used — every count on record is a Pantry count — so the choice only ever added a step. The Pantry and Kitchen buttons are gone, along with the separate Add Kitchen count button on the stock-take list, and a new sheet now opens straight into the Pantry count.',
                    'The line reading Closing stock = what you counted on the shelf now has been removed from the count sheet header, as requested.',
                    'The Kitchen catalog of 64 item names is no longer offered anywhere. The names themselves are still in the database for now and are not shown on any screen; removing them for good is a separate step.',
                    'Nothing recorded is affected. Both stock-takes on record are Pantry counts, they open and read exactly as before, and the Pantry catalog of 54 items is untouched. An old bookmark pointing at the Kitchen sheet now opens the Pantry sheet instead of showing an error.',
                ],
                'commits' => 'af3822d..HEAD',
            ],
            [
                'version' => '1.10.12',
                'date'    => '2026-08-21',
                'summary' => 'Pages that were unreadable on a phone now stack properly.',
                'improved' => [
                    'Prep Overview was close to unusable on a phone. The section cards were locked to three side-by-side columns no matter how narrow the screen was, which on a normal phone leaves about 96 pixels per card: section names broke mid-word and the appliance status was cut off past the edge of the card. The cards now sit one above the other on a phone, two across on a tablet, and three across on a computer as before.',
                    'Six other pages had the same fault and would have looked the same on a phone: Recipes, Recipe detail, Suppliers, Special Events and Petty Cash. All are fixed together. The summary boxes at the top of those pages now sit two across on a phone rather than four, which is what the main dashboard has always done.',
                    'Nothing changes on a computer. The desktop layout was measured before and after and is identical, down to the pixel.',
                ],
                'commits' => '6798c92..HEAD',
            ],
            [
                'version' => '1.10.11',
                'date'    => '2026-08-21',
                'summary' => 'Removing an ingredient explains itself instead of failing, and a refused email can no longer cost you a reminder or a batch.',
                'improved' => [
                    'Removing an ingredient that is still in use showed a blank Server Error page. There was nothing wrong with the request: the ingredient was named in four recipes, and the system correctly refused to delete something the recipes still point at. It simply had no way to say so, so it fell over instead. It now names the reason on the page you are already on, for example: GARLIC FALKE is still used by 4 recipes. Remove it from those first.',
                    'The same check covers purchases, market purchases, wastage entries and production batches, not only recipes. Two of those were quietly worse than the error page: an ingredient used only in market purchases or production batches would delete successfully and take that history out with it. Now nothing is removed while any record still refers to it.',
                    'A refused email can no longer cost you a reminder. The 9am and 5pm reminders are sent by email first and only then saved to your in-app notifications, so when the email service turned one away, the reminder vanished from both at once with nothing on screen to show for it. The system now tries three times before giving up, treats each Head Chef separately so one failure cannot stop the other being told, and records a clear failure when it truly cannot send.',
                    'Logging a production batch is no longer at risk from a failed email. The low-stock alert was being sent as part of saving the batch, so an email failure would have undone the whole batch and the chef would have lost work they had already entered. Saving the batch and sending the alert are now independent: the batch is kept regardless.',
                    'The notes for version 1.10.9 have been corrected. They stated that the missing closing reminder on 20 August was caused by the clock drift fixed in that release. The server record shows it was not — that reminder was sent on time and the email service refused it, which is the fault addressed above. The 1.10.9 entry now says so.',
                ],
                'commits' => '5ec2e28..HEAD',
            ],
            [
                'version' => '1.10.10',
                'date'    => '2026-08-21',
                'summary' => 'Housekeeping. Three sign-in screens that could never be opened have been removed.',
                'removed' => [
                    'The system was built on a standard starter kit, and that kit came with three sign-in screens ISMS never used: a public sign-up page, an email-confirmation page, and a page asking you to re-type your password before a sensitive action. None of the three could be reached — no link, button or web address led to any of them — but the code sat in the system anyway, and anyone reading it would reasonably assume those screens were live. All three are now gone.',
                    'Removing them also settled a small untruth. The dashboard claimed to require a confirmed email address before letting you in. That check could never fail, because the system was never set up to confirm email addresses in the first place, so it was a promise with nothing behind it. The claim has been removed. Who can open the dashboard has not changed.',
                    'Nothing on your side changes. Signing in, signing out, changing your password and editing your profile all behave exactly as before, and accounts are still created by invitation from the Users page. Email addresses already on file were left untouched.',
                ],
                'commits' => '2b7d484..HEAD',
            ],
            [
                'version' => '1.10.9',
                'date'    => '2026-08-21',
                'summary' => 'Reminders can no longer be skipped without anyone noticing.',
                'improved' => [
                    'The 9am and 5pm reminders could be skipped without anyone noticing. The part of the system that watches the clock was checking the time, waiting a minute, then checking again — so each cycle took slightly longer than a minute and slowly slid out of step. Once it slid far enough to step over a minute completely, any reminder due in that minute was never sent. No error, no warning; the reminder simply did not arrive.',
                    'Reminders now run from the clock itself rather than a running tally, so a minute can no longer be stepped over. Correction to what was first published here: we originally said the closing reminder that went missing on 20 August was lost to this drift. The server record later showed otherwise. That reminder was sent on time, and the email service refused to accept it, which is a different fault entirely and is dealt with in a later release. The drift described above was real and worth removing, but it is not what happened on 20 August.',
                    'The About page also had the previous release dated 19 August when it was actually published on the 21st. Corrected, and every other release date was checked against its own record at the same time.',
                ],
                'commits' => '79e1799..HEAD',
            ],
            [
                'version' => '1.10.8',
                'date'    => '2026-08-21',
                'summary' => 'Routine upkeep of the software the system is built on. Nothing visible changes.',
                'improved' => [
                    'Nothing in the app looks or behaves differently. The framework the system is built on, and the parts that carry pages to your browser, were nine and two versions behind respectively, and have been brought up to date.',
                    'No security problem was outstanding. Both dependency checks came back clean before this and clean after it. This is routine upkeep, done while it is still small, rather than a fix for anything that was wrong.',
                    'Everything was re-checked afterwards: every page template still compiles, all 121 addresses in the system still register, the database answers, and a full inventory PDF still generates correctly.',
                ],
                'commits' => 'ed21685..HEAD',
            ],
            [
                'version' => '1.10.7',
                'date'    => '2026-08-19',
                'summary' => 'An empty month no longer reads as though your records have disappeared.',
                'improved' => [
                    'Sales, purchases, wastage, market purchases and production all open on the current month. When that month happens to be empty, the page used to say "No purchases yet. Log your first one." or "No sales logged yet." — which reads as your records being gone, rather than as a month with nothing in it.',
                    'It now tells you the difference. If there are records on file it says nothing is here for this period and how many exist elsewhere, so you know to change the month. Only a genuinely empty list still invites you to log your first one.',
                    'Nothing was ever lost. Every purchase and every sale that has been entered is still on record and always was. This was the page describing them wrongly, not the figures themselves.',
                ],
                'commits' => 'ff894d0..HEAD',
            ],
            [
                'version' => '1.10.6',
                'date'    => '2026-08-19',
                'summary' => 'Behind the scenes only: putting a change live now takes minutes instead of the best part of ten.',
                'improved' => [
                    'Nothing in the app looks or behaves differently. This release is entirely about how long it takes to put a change live, which had crept up to around eight minutes, almost all of it spent rebuilding parts that had not changed.',
                    'Installing the code libraries the system depends on now takes 13 seconds instead of roughly seven minutes. They were being rebuilt from scratch every time, as a workaround for a supplier file that was broken back in June and has since been fixed.',
                    'That step is also skipped entirely from now on unless the libraries themselves change, which is rare. Previously editing a single page meant redoing the whole thing. In practice this means a fix can reach the kitchen in a couple of minutes rather than the best part of ten.',
                ],
                'commits' => 'a9b48bb..HEAD',
            ],
            [
                'version' => '1.10.5',
                'date'    => '2026-08-19',
                'summary' => 'A record dated in an earlier month no longer disappears the moment you save it.',
                'improved' => [
                    'Logging anything dated outside the current month used to look like it had failed. The save worked and the record was stored correctly, but you were then shown a list filtered to this month, where it was nowhere to be seen. Anyone catching up on a period they had fallen behind on would watch every entry vanish and reasonably decide the system was not saving. You now stay on the month you were working in, and the record you just entered is there.',
                    'The same applies across sales, purchases, wastage, market purchases and production. All five behaved this way, and all five now keep your place. Whatever month or filter you had the list set to survives the save.',
                    'The value of an ingredient is now worked out in one place only. Ten spots around the system were each recalculating it with the rounding that caused the drift corrected in 1.10, even though the correct figure overwrote them a moment later. Nothing about your figures changes. The wrong sum is simply no longer sitting there waiting to be copied into the next thing someone builds.',
                ],
                'commits' => '1498e59..HEAD',
            ],
            [
                'version' => '1.10.4',
                'date'    => '2026-08-19',
                'summary' => 'Saving an ingredient no longer throws you back to the top of the inventory list.',
                'improved' => [
                    'Keying an amount into an ingredient and saving used to send you back to the very top of the inventory. With over two hundred ingredients on the list that meant scrolling back down to your place after every single edit. The page now returns you to the row you just changed.',
                    'Saving also used to clear whatever category or search you had the list narrowed to, quietly putting the full list back. Your filter now survives the save, so you can work through one category without setting it again each time.',
                ],
                'commits' => 'd89c896..HEAD',
            ],
            [
                'version' => '1.10.3',
                'date'    => '2026-08-19',
                'summary' => 'Housekeeping. Nothing in the app looks or works differently.',
                'improved' => [
                    'The server upgrade described in the last release needed one more fix before it would install, so it only actually reached the server after that note was written. It is on the server now, and the safety fixes to the PDF and email components went up with it.',
                    'The engineering handbook now explains the symbols used in the formulas section, so the sums behind the figures can be read without guessing what each mark means.',
                    'The deployment tool now reports which version it just put live. It had been printing two lines of a comment instead, which meant nobody could confirm from it what had actually gone up.',
                ],
                'commits' => 'c27722c..HEAD',
            ],
            [
                'version' => '1.10.2',
                'date'    => '2026-08-14',
                'summary' => 'Housekeeping. The parts that build your PDFs and send your email were updated to their newest safe versions.',
                'improved' => [
                    'The component that builds every PDF in the system — inventory, sales, purchases, wastage, recipes, events, feedback — was updated. The version we were on had six published faults, including one that could be used to read files off the server through a crafted image, and three that could tie the server up long enough to stop it answering. Every PDF was regenerated and checked against the real data after the update.',
                    'The component that carries our email out to the sending service was updated, closing seven published faults of its own. Nothing about how reminders or reports reach you has changed.',
                    'The server now runs the same version of PHP the system is built and tested on. It had been one version behind, which meant the code was being written on one and run on another.',
                    'Six faults in the tooling that builds the pages were also cleared. That tooling never runs on the server, so this was tidying rather than exposure.',
                ],
                'commits' => 'a761e25..HEAD',
            ],
            [
                'version' => '1.10.1',
                'date'    => '2026-08-13',
                'summary' => 'Signing in no longer drops you on a page your account is not allowed to see.',
                'improved' => [
                    'Signing in now takes you to your own starting page — the section checklist for a junior chef, the support queue for the support account, the Overview for the Owner and Head Chefs. Before, everyone except junior chefs was sent to the Overview, so the support account was refused entry the moment it signed in.',
                    'If your browser was last pointed at a page your account cannot open, signing in no longer sends you back to it. Chefs were being shown an "unauthorized" message straight after logging in, through no fault of their own. The trade-off is that if you are signed out while working, you now come back to your starting page rather than the exact page you were on.',
                ],
                'commits' => '0e10f29..HEAD',
            ],
            [
                'version' => '1.10',
                'date'    => '2026-08-13',
                'summary' => 'Open orders can now be food that is not on the menu, junior chefs can log production and have their stock-take come off the shelves, and the inventory value adds up exactly.',
                'added'   => [
                    'An open order no longer has to be an existing recipe. Tick the box and type the dish in by hand, so a one-off meal cooked for a staff member can finally be recorded as what it was. Because there is no recipe behind it, nothing is taken off your stock for it.',
                    'Junior chefs can now log production, the same as the Head Chef. They cannot remove a batch once it is logged — that stays with the Owner and Head Chef.',
                    'A recorded stock-take now takes its Out column off your live stock, so what the sheet says was used actually leaves the inventory. Each item on the Pantry and Kitchen lists says which ingredient it draws from, set from the new "Deducts from" box on the item list; anything left blank is counted but moves no stock.',
                ],
                'improved' => [
                    'The inventory value now adds up exactly. Twenty-five ingredients had drifted a few cents away from their own quantity and price, because a value was worked out before the quantity was rounded, and the gap grew slightly every time stock moved. Every ingredient has been put right and the figure is now worked out fresh on every change, so it cannot drift again.',
                    'The daily report is now owned by whoever writes it first. With more than one Head Chef on the team, the second person to open it is told the day is already done and shown what was written, instead of quietly replacing it. Whoever wrote it can still go back and correct their own.',
                    'The sales list, the sales PDF, the Overview and search all show an open order by the dish that was typed in, so an off-menu meal reads as itself rather than as a blank.',
                ],
                'commits' => '3bc74dd..HEAD',
            ],
            [
                'version' => '1.9',
                'date'    => '2026-08-07',
                'summary' => 'You can now record a staff open order — an extra meal a staff member orders from the kitchen, usually at a discount.',
                'added'   => [
                    'The "More" menu on the Users page can now set a person\'s display language to English or Bahasa Malaysia, so a chef who has not found the setting in their own Profile no longer has to be walked through it. As before, this changes the prep checklist page; reports and PDFs stay in English.',
                    'The Log Sale form has an "Open order" tick box for food a staff member orders from the kitchen. It is recorded without a name against it, so nobody is singled out on the sales list.',
                    'Any sale can now carry a discount in Ringgit. The takings recorded are the price less the discount, and a discount larger than the sale is simply treated as free rather than as money owed.',
                    'The sales list marks open orders with a small label and shows the discount underneath, so a quiet day is not mistaken for poor trade.',
                ],
                'improved' => [
                    'The sales PDF now has a Discount column, plus totals for discounts given and open orders served, so the month reads correctly at a glance.',
                    'An open order still counts as a normal kitchen dish, so the ingredients come off your stock and the figures on the Overview stay accurate.',
                    'Removing someone from the Users page now sits behind a "More" menu and asks you twice before it goes through, so an account cannot be lost to a stray click.',
                    'Support admins can now remove a staff account themselves instead of waiting on the Owner. They still cannot remove an Owner, another admin, or their own account.',
                ],
                'commits' => 'fb627fc → HEAD',
            ],
            [
                'version' => '1.8.1',
                'date'    => '2026-07-22',
                'summary' => 'Fixed the squashed, unstyled page you got by pressing the back button after signing in.',
                'improved' => [
                    'Pressing the browser\'s back button after signing in no longer shows a shrunken, badly laid-out copy of the page. The sign-in screen is no longer kept in the browser\'s memory, so going back now simply takes you to your home page as it should. Previously the only way out was to reload.',
                ],
                'commits' => 'e51391e → HEAD',
            ],
            [
                'version' => '1.8',
                'date'    => '2026-07-22',
                'summary' => 'Gross Margin now reflects what your dishes actually cost to make, and a stock count updates your real ingredient figures.',
                'added'   => [
                    'The Owner can now open Prep Overview and see how the kitchen sections are getting on for the day.',
                ],
                'improved' => [
                    'The Gross Margin figure on the Overview now compares your sales against the recipe cost of the dishes you actually sold. Before it was measured against everything you bought that month, which made the figure swing wildly depending on when deliveries landed.',
                    'Recording a tally now updates your live ingredient quantities to match what you physically counted, so the shelf and the system agree once the count is saved.',
                    'Clicking the "isms" logo now takes you back to your own home page, whichever kind of account you are signed in on.',
                    'Several ingredient costs and recipe figures were corrected.',
                ],
                'commits' => '6a81d53 → HEAD',
            ],
            [
                'version' => '1.7',
                'date'    => '2026-07-17',
                'summary' => 'The Overview now shows your whole ingredient list, and recording a purchase works again.',
                'added'   => [
                    'A Junior Chef demo/training account, so a junior chef can practise on the demo copy of the kitchen without touching real data.',
                ],
                'improved' => [
                    'The Inventory panel on the Overview now lists every ingredient you have, not just the first seven — scroll inside the panel to see the whole list, and the column headings stay in view as you go.',
                    'Because of the same change, you can now log wastage against any ingredient straight from the Overview. Before, only a handful were offered in the list.',
                    'Recording a purchase no longer fails with an error.',
                    'On the demo copy, low-stock warnings now only reflect that account\'s own demo world.',
                ],
                'commits' => 'feafeb5 → HEAD',
            ],
            [
                'version' => '1.6',
                'date'    => '2026-07-06',
                'summary' => 'The demo accounts are now a real, safe playground — and logging sales got easier.',
                'added'   => [
                    'The demo/training accounts now work on their own private copy of the kitchen. They can add, edit and delete anything and see each other\'s changes, but nothing they do ever touches the real data. An admin can reset the demo copy back to a fresh clone of the live kitchen at any time.',
                    'When logging a sale you can now attach photos (a receipt, the POS screen or the plate).',
                ],
                'improved' => [
                    'The sales list is now grouped by day, and the price fills in automatically from the recipe.',
                    'Low-stock alerts now fire when you record production, so the warning reflects what you actually have on the shelf.',
                    'Signing in is more secure: a wrong password can no longer slip through, and you can show or hide what you type.',
                ],
                'commits' => '9d32159 → HEAD',
            ],
            [
                'version' => '1.5',
                'date'    => '2026-07-05',
                'summary' => 'You can now see exactly what the system does and how it has changed.',
                'added'   => [
                    'This page — a plain-language history of every update, from the very first build to today, with what was added, improved or removed each time.',
                ],
                'commits' => 'aa3378e → HEAD',
            ],
            [
                'version' => '1.4',
                'date'    => '2026-07-05',
                'summary' => 'Team members can now give each other honest feedback, privately.',
                'added'   => [
                    'Peer Feedback: rate your teammates on five questions (cleanliness, safety, organisation, teamwork and communication) with an optional note and file attachments. Your name is hidden from the person you rate — only the Owner can see who sent what.',
                    'The Owner gets a monthly performance summary by team member and by week, plus a PDF export and an automatic end-of-month email.',
                ],
                'removed' => [
                    'The old Compliance reports page was retired and replaced by Peer Feedback. Past compliance records are kept and still appear in the audit log.',
                ],
                'commits' => '0993c9a',
            ],
            [
                'version' => '1.3',
                'date'    => '2026-07-04',
                'summary' => 'Staff can request time off, and there is now a safe way to explore the system.',
                'added'   => [
                    'Leave applications: request time off with a reason and supporting documents (photos, videos or PDFs). The Owner approves or rejects each request.',
                    'Hidden demo accounts (an Owner-level and a Head-Chef-level one) for safe training. They can click around and try everything, but nothing they change is ever saved.',
                ],
                'commits' => '9870830 → c745a94',
            ],
            [
                'version' => '1.2',
                'date'    => '2026-07-03',
                'summary' => 'More accurate food costing and fewer recipe mistakes.',
                'added'   => [
                    'Log food you make in-house (like sauces and stocks) straight into inventory.',
                    'Add an adjustable overhead percentage to a recipe so the plate cost reflects real running costs.',
                    'Price ingredients per piece by recording how many pieces come in a pack.',
                ],
                'improved' => [
                    'Recipes now warn you and merge the rows if the same ingredient is added twice, instead of crashing when saving.',
                ],
                'commits' => '8f8cdac → eb0d69f',
            ],
            [
                'version' => '1.1',
                'date'    => '2026-07-01',
                'summary' => 'Tighter, simpler sign-in.',
                'improved' => [
                    'Stronger login protection, with a show/hide button for your password.',
                ],
                'removed' => [
                    'Password reset by email was removed — an administrator resets passwords instead, which is safer for a small team.',
                ],
                'commits' => '2a7aefb → db2a980',
            ],
            [
                'version' => '1.0',
                'date'    => '2026-06-29',
                'summary' => 'The system went live for the kitchen, with support tickets and a secure setup.',
                'added'   => [
                    'Support Tickets: report a problem and track it to resolution, handled by a new restricted Admin role that only sees tickets and users.',
                    'Administrators can reset a team member’s password.',
                    'Reliable email delivery so alerts and reports actually arrive.',
                    'More inventory categories and units (Retail, Vegetables, bottles, gallons, pieces, trays).',
                ],
                'improved' => [
                    'The Head Chef can now view and manage Petty Cash.',
                    'Secure HTTPS connection with hardened security settings.',
                    'Fixed the Profile page crash and made low-stock alerts more accurate and dismissible.',
                ],
                'commits' => '39f4113 → 7464a06',
            ],
            [
                'version' => '0.9',
                'date'    => '2026-06-08',
                'summary' => 'Everyday tools for buying and reporting.',
                'added'   => [
                    'Market Purchases: record ad-hoc ingredient buys from the market, keeping inventory costs in sync.',
                    'Daily Report: the Head Chef writes one each day and the Owner reads it.',
                ],
                'removed' => [
                    'A weekly kitchen rotation schedule was trialled and then removed as it was not needed.',
                ],
                'commits' => '0f2c268 → bec8d12',
            ],
            [
                'version' => '0.8',
                'date'    => '2026-05-31',
                'summary' => 'Getting help and keeping a full record.',
                'added'   => [
                    'Report a problem to the developer through a support form, with file attachments.',
                    'Clear pop-up notifications in the corner when something succeeds or fails.',
                ],
                'improved' => [
                    'A complete audit trail now records changes across every part of the system.',
                ],
                'commits' => '89afadc → d1de477',
            ],
            [
                'version' => '0.7',
                'date'    => '2026-05-27',
                'summary' => 'Ready to deploy, in two languages, with owner controls.',
                'added'   => [
                    'One-click deployment so the app can run on its own secure server.',
                    'Bahasa Malaysia language option for the kitchen checklist.',
                    'The Owner can create and organise kitchen sections and tasks, and assign a chef to each.',
                    'Automatic sign-out after 30 minutes of inactivity, and a maintenance mode.',
                ],
                'improved' => [
                    'Tightened who can see and do what, closing several security gaps.',
                ],
                'commits' => 'dac7f3a → 142e014',
            ],
            [
                'version' => '0.6',
                'date'    => '2026-05-21',
                'summary' => 'A fresh, modern look.',
                'improved' => [
                    'The whole app was rebuilt on a faster, more modern foundation with refreshed fonts and colours.',
                    'A redesigned Owner dashboard and a proper layout for mobile phones.',
                ],
                'commits' => '485fded → 16d6185',
            ],
            [
                'version' => '0.5',
                'date'    => '2026-05-12',
                'summary' => 'The kitchen floor comes online.',
                'added'   => [
                    'Section checklists for junior chefs, including appliance photo checks that reset each day.',
                    'A prep overview for the Head Chef, with reminders at 9am and 5pm.',
                    'Special events tracking, login history, and a page to manage team members.',
                    'Live inventory: selling or wasting an item automatically reduces stock, with an alert when something runs low.',
                ],
                'commits' => '2eb7b22 → e95e347',
            ],
            [
                'version' => '0.1',
                'date'    => '2026-05-01',
                'summary' => 'The first build — the financial backbone.',
                'added'   => [
                    'Track the money: sales, purchases, wastage and petty cash, all in Malaysian Ringgit.',
                    'Recipes that work out the cost of each plate automatically.',
                    'Suppliers and inventory, PDF exports, and receipt photo uploads.',
                    'Three roles — Owner, Head Chef and Junior Chef — with a full audit log.',
                ],
                'commits' => '047cdf3 → 3082960',
            ],
        ];
    }
}
