<?php

use App\Models\StaffMeal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The staff meals for 5–11 September 2026, keyed in from the kitchen's own
 * spreadsheet ("staffmeal 5-11 sep.xlsx") a month after they were eaten.
 *
 * Entered here rather than through the page because the page deducts every
 * line from live stock on save, and this food left the shelf in September — a
 * deduction today would take it off a second time from stock that has moved
 * since. So no line is linked to an inventory item and nothing is deducted;
 * the remark on each sheet says so.
 *
 * Copied as written, at the Owner's instruction (2026-10-11): the sheet's own
 * slips — babi bakar's subtotal skipping three rows, steam pork's garlic and
 * shallot with quantity and price swapped, masak merah's prices a row out —
 * are kept. Only the totals differ from the spreadsheet, because the app adds
 * every line where the sheet's formulas did not.
 *
 * Skipped on an empty database (a fresh install, the test suite), and per
 * sheet when one with the same date and dish already exists, so a second run
 * adds nothing.
 */
return new class extends Migration
{
    private const REMARK = 'Keyed in from the kitchen\'s sheet "staffmeal 5-11 sep" on 2026-10-11. Stock was not moved.';

    /** date, dish, then each line as item, unit, quantity, unit price. */
    private const MEALS = [
        ['2026-09-08', 'babi bakar', [
            ['pork belly', 'kg', '2.5', '20.5'],
            ['galangal', 'g', '0.01', '8.0'],
            ['ginger', 'g', '0.01', '8.0'],
            ['shallot', 'g', '0.03', '10.0'],
            ['garlic', 'g', '0.002', '12.0'],
            ['coconut milk', 'ml', '0.015', '15.7'],
            ['kaffir leaves', 'g', '0.03', '45.0'],
            ['tamarind paste', 'g', '0.03', '11.5'],
            ['palm sugar', 'g', '0.03', '12.0'],
            ['lemongrass', 'g', '0.02', '6.0'],
            ['cooking oil', 'ml', '0.05', '30.9'],
            ['dried red chilli', 'g', '0.02', '15.9'],
            ['buah keras', 'g', '0.02', '12.0'],
            ['rice', 'kg', '1.0', '5.9'],
        ]],
        ['2026-09-09', 'steam pork', [
            ['porkbelly', 'kg', '2.5', '20.5'],
            ['cooking oil', 'kg', '0.01', '30.9'],
            ['saesame oil', 'kg', '0.01', '13.5'],
            ['salt', 'kg', '0.005', '4.5'],
            ['sugar', 'pkt', '0.002', '2.85'],
            ['belacan', 'kg', '0.015', '12.3'],
            ['calamansi', 'kg', '0.03', '5.0'],
            ['rice', 'kg', '1.0', '5.9'],
            ['garlic', 'kg', '12.0', '0.01'],
            ['shallot', 'kg', '10.0', '0.01'],
        ]],
        ['2026-09-10', 'ayam percik', [
            ['whole chicken', 'kg', '2.5', '10.5'],
            ['cooking oil', 'kg', '0.03', '30.9'],
            ['tumeric powder', 'kg', '0.005', '25.0'],
            ['garlic', 'kg', '0.01', '12.0'],
            ['shallot', 'pkt', '0.02', '10.0'],
            ['onion', 'kg', '0.04', '26.0'],
            ['lemongrass', 'kg', '0.01', '6.0'],
            ['ginger', 'kg', '0.01', '8.0'],
            ['dried chilli', 'kg', '0.005', '16.8'],
            ['ground coriendar', 'kg', '0.002', '20.0'],
            ['cumin', 'kg', '0.002', '20.0'],
            ['fennal', 'kg', '0.002', '20.0'],
        ]],
        ['2026-09-11', 'braised pork', [
            ['pork', 'kg', '1.0', '20.5'],
            ['cabbage', 'kg', '0.3', '4.0'],
            ['ginger', 'kg', '0.05', '8.0'],
            ['light soy sauce', 'kg', '0.01', '6.25'],
            ['dark soy sauce', 'pkt', '0.01', '11.6'],
            ['oyster sauce', 'kg', '0.01', '12.3'],
            ['chinese wine', 'kg', '0.01', '8.5'],
            ['sugar', 'kg', '0.005', '2.85'],
            ['chicken stock', 'kg', '0.002', '38.0'],
            ['cinnamon powder', 'kg', '0.001', '20.0'],
            ['coriendar', 'kg', '0.001', '20.0'],
            ['cumin', 'kg', '0.001', '20.0'],
        ]],
        ['2026-09-05', 'masak merah', [
            ['whole chicken', 'kg', '2.5', '10.5'],
            ['red onion', 'kg', '0.01', '30.9'],
            ['garlic', 'kg', '0.01', '20.0'],
            ['cili padi', 'kg', '0.005', '25.0'],
            ['cooking oil', 'pkt', '0.002', '4.5'],
            ['sos cili', 'kg', '0.015', '4.2'],
            ['sos tiram', 'kg', '0.03', '2.3'],
            ['kicap manis', 'kg', '0.03', '21.0'],
            ['serai', 'kg', '0.03', '5.9'],
            ['sugar', null, '0', '0'],
            ['garam', null, '0', '0'],
        ]],
        ['2026-09-06', 'roasted pork', [
            ['pork belly', 'kg', '1.0', '20.5'],
            ['cooking oil', 'kg', '0.01', '30.9'],
            ['lemongrass', 'kg', '0.01', '6.0'],
            ['salt', 'kg', '0.005', '4.5'],
            ['fish sauce', 'pkt', '0.002', '6.0'],
            ['chilli', 'kg', '0.015', '26.0'],
            ['cili padi', 'kg', '0.03', '30.0'],
            ['shaoxing wine', null, '0', '0'],
        ]],
    ];

    public function up(): void
    {
        if (! DB::table('users')->exists()) {
            echo "  no users yet (fresh database) — staff meals not entered.\n";

            return;
        }

        foreach (self::MEALS as [$date, $dish, $lines]) {
            $this->enter($date, $dish, $lines);
        }
    }

    public function down(): void
    {
        StaffMeal::where('remark', self::REMARK)->get()->each->delete();
    }

    private function enter(string $date, string $dish, array $lines): void
    {
        if (StaffMeal::whereDate('meal_date', $date)->where('dish', $dish)->exists()) {
            echo "  {$date} {$dish}: already entered — left alone.\n";

            return;
        }

        DB::transaction(function () use ($date, $dish, $lines) {
            $meal = StaffMeal::create([
                'meal_date' => $date,
                'dish' => $dish,
                'misc_percent' => 30,
                'remark' => self::REMARK,
            ]);

            foreach ($lines as [$item, $unit, $quantity, $price]) {
                $meal->lines()->create(['item' => $item, 'unit' => $unit, 'quantity' => $quantity, 'unit_price' => $price]);
            }

            echo "  {$date} {$dish}: " . count($lines) . " lines, grand total RM {$meal->grand_total}.\n";
        });
    }
};
