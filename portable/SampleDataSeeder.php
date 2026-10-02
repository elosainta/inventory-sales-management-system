<?php

namespace Database\Seeders;

use App\Domain\Purchasing\Actions\LogPurchase;
use App\Domain\Recipes\Actions\SaveRecipe;
use App\Domain\Sales\Actions\LogSale;
use App\Domain\Wastage\Actions\LogWastage;
use App\Models\FloatIssuance;
use App\Models\InventoryItem;
use App\Models\SpecialEvent;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Sample data for the offline demo only: portable/build.sh copies it into the
 * bundled app, and the app itself never runs it. Runs on first launch, so every
 * date is relative to the day the demo is opened and the dashboard is never an
 * empty month. The accounts it creates are the ones the sign-in page offers
 * (portable/demo-accounts.blade.php).
 *
 * Goes through the app's own actions (LogPurchase, LogSale, LogWastage,
 * SaveRecipe) so stock, unit costs and plate costs are derived exactly as the
 * app derives them, rather than typed in and out of step.
 */
class SampleDataSeeder extends Seeder
{
    // name => [category, unit, unit cost]
    private const ITEMS = [
        'Chicken thigh'   => ['Meat', 'kg', 14.50],
        'Beef slices'     => ['Meat', 'kg', 38.00],
        'Prawns'          => ['Seafood', 'kg', 42.00],
        'Anchovies'       => ['Seafood', 'kg', 28.00],
        'Eggs'            => ['Dairy', 'pcs', 0.45],
        'Coconut milk'    => ['Dairy', 'L', 9.80],
        'Rice'            => ['Pantry', 'kg', 3.60],
        'Yellow noodles'  => ['Pantry', 'kg', 4.20],
        'Rice vermicelli' => ['Pantry', 'kg', 6.50],
        'Cooking oil'     => ['Pantry', 'L', 6.90],
        'Sambal paste'    => ['Pantry', 'kg', 18.00],
        'Laksa paste'     => ['Pantry', 'kg', 24.00],
        'Soy sauce'       => ['Pantry', 'L', 7.50],
        'Peanuts'         => ['Pantry', 'kg', 12.00],
        'Cucumber'        => ['Vegetables', 'kg', 4.80],
        'Bean sprouts'    => ['Vegetables', 'kg', 3.20],
        'Choy sum'        => ['Vegetables', 'kg', 6.00],
        'Shallots'        => ['Produce', 'kg', 8.50],
        'Garlic'          => ['Produce', 'kg', 9.00],
        'Lime'            => ['Produce', 'kg', 7.20],
        'Chilli'          => ['Produce', 'kg', 16.00],
        'Turmeric powder' => ['Spice', 'kg', 22.00],
        'Curry powder'    => ['Spice', 'kg', 26.00],
        'Bottled water'   => ['Retail', 'btl', 0.90],
    ];

    // supplier => the items it delivers
    private const SUPPLIERS = [
        'Harbour Fresh Seafood'  => ['Prawns', 'Anchovies'],
        'Riverside Poultry'      => ['Chicken thigh', 'Beef slices', 'Eggs'],
        'Green Valley Farm'      => ['Cucumber', 'Bean sprouts', 'Choy sum', 'Shallots', 'Garlic', 'Lime', 'Chilli'],
        'Golden Grain Trading'   => ['Rice', 'Yellow noodles', 'Rice vermicelli', 'Cooking oil', 'Soy sauce', 'Peanuts', 'Coconut milk'],
        'Spice Route Supplies'   => ['Sambal paste', 'Laksa paste', 'Turmeric powder', 'Curry powder', 'Bottled water'],
    ];

    // dish => [selling price, [item => quantity per plate]]
    private const RECIPES = [
        'Nasi Lemak Ayam' => [16.90, ['Rice' => 0.15, 'Coconut milk' => 0.05, 'Chicken thigh' => 0.18, 'Sambal paste' => 0.04, 'Anchovies' => 0.02, 'Peanuts' => 0.02, 'Eggs' => 1, 'Cucumber' => 0.03]],
        'Mee Goreng Mamak' => [13.90, ['Yellow noodles' => 0.2, 'Eggs' => 1, 'Choy sum' => 0.05, 'Bean sprouts' => 0.04, 'Chilli' => 0.01, 'Soy sauce' => 0.02, 'Cooking oil' => 0.03]],
        'Prawn Laksa' => [22.90, ['Rice vermicelli' => 0.12, 'Laksa paste' => 0.06, 'Coconut milk' => 0.12, 'Prawns' => 0.12, 'Bean sprouts' => 0.04, 'Lime' => 0.02, 'Eggs' => 0.5]],
        'Beef Rendang Rice' => [24.90, ['Rice' => 0.15, 'Beef slices' => 0.2, 'Coconut milk' => 0.08, 'Shallots' => 0.03, 'Garlic' => 0.01, 'Curry powder' => 0.01, 'Chilli' => 0.01]],
        'Chicken Curry Rice' => [17.90, ['Rice' => 0.15, 'Chicken thigh' => 0.2, 'Curry powder' => 0.015, 'Turmeric powder' => 0.005, 'Coconut milk' => 0.06, 'Shallots' => 0.02]],
    ];

    public function run(): void
    {
        $this->call(KitchenTeamSeeder::class);
        $this->call(StockTakeItemSeeder::class);

        foreach ([['admin@example.test', 'Support Admin', User::ROLE_ADMIN], ['parttimer@example.test', 'Jamie', User::ROLE_PART_TIMER]] as [$email, $name, $role]) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password'), 'role' => $role]);
        }

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        Auth::setUser($owner); // the audit log attributes every seeded row to the Owner

        $items = $this->inventory();
        $recipes = $this->recipes($items);
        $this->purchases($items, $owner);
        $this->sales($recipes);
        $this->wastage($items);
        $this->extras();
    }

    private function inventory(): array
    {
        $items = [];
        foreach (self::ITEMS as $name => [$category, $unit, $cost]) {
            $items[$name] = InventoryItem::create([
                'name' => $name, 'category' => $category, 'unit' => $unit,
                'quantity_on_hand' => 0, 'reorder_threshold' => 20, 'unit_cost' => $cost,
            ]);
        }

        return $items;
    }

    private function recipes(array $items): array
    {
        $recipes = [];
        foreach (self::RECIPES as $name => [$price, $ingredients]) {
            $recipes[] = app(SaveRecipe::class)->execute([
                'name' => $name, 'serving_size' => '1 pax', 'selling_price' => $price,
                'ingredients' => collect($ingredients)
                    ->map(fn ($qty, $item) => ['inventory_item_id' => $items[$item]->id, 'quantity' => $qty])
                    ->values()->all(),
            ]);
        }

        return $recipes;
    }

    // Weekly deliveries per supplier for eight weeks plus one yesterday, so the
    // current month always has some; the last two are still unpaid.
    private function purchases(array $items, User $owner): void
    {
        $invoice = 1000;
        foreach (self::SUPPLIERS as $name => $supplied) {
            $supplier = Supplier::create(['name' => $name, 'contact' => '012-345 6789', 'email' => '', 'address' => 'Kuala Lumpur']);

            foreach ([55, 48, 41, 34, 27, 20, 13, 6, 1] as $daysAgo) {
                app(LogPurchase::class)->execute([
                    'supplier_id' => $supplier->id, 'user_id' => $owner->id,
                    'invoice_number' => 'INV-' . $invoice++,
                    'status' => $daysAgo <= 6 ? 'pending' : 'completed',
                    'purchase_date' => now()->subDays($daysAgo)->setTime(9, 30),
                    'lines' => collect($supplied)->map(fn ($item) => [
                        'inventory_item_id' => $items[$item]->id,
                        'quantity' => $this->deliveryQuantity($item, $items[$item]->unit),
                        'unit_price' => round(self::ITEMS[$item][2] * (0.95 + mt_rand(0, 10) / 100), 2),
                    ])->all(),
                ]);
            }
        }
    }

    // A week of what the recipes use (about 60 plates of each dish), plus slack,
    // so the sample month ends with stock on every shelf.
    private function deliveryQuantity(string $item, string $unit): float
    {
        $weekly = collect(self::RECIPES)->sum(fn ($recipe) => ($recipe[1][$item] ?? 0) * 60);

        return $weekly > 0 ? ceil($weekly * 1.2) + 2 : ($unit === 'btl' ? 48 : mt_rand(4, 8));
    }

    // Eight weeks of trade, busier at the weekend: last month is always complete.
    private function sales(array $recipes): void
    {
        mt_srand(42);
        for ($daysAgo = 55; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo);
            $rush = $day->isWeekend() ? 2 : 1;

            foreach ($recipes as $recipe) {
                app(LogSale::class)->execute([
                    'recipe_id' => $recipe->id, 'qty_sold' => mt_rand(3, 9) * $rush,
                    'selling_price' => $recipe->selling_price, 'discount' => 0,
                    'sale_date' => $day->copy()->setTime(13, 0),
                ]);
            }
        }
    }

    private function wastage(array $items): void
    {
        $entries = [['Bean sprouts', 0.8, 'spoilage', 25], ['Prawns', 0.5, 'expired', 18], ['Rice', 1.2, 'over-prepped', 12],
            ['Choy sum', 0.6, 'spoilage', 9], ['Eggs', 12, 'dropped', 40], ['Prawns', 0.4, 'spoilage', 33], ['Chicken thigh', 0.4, 'dropped', 5], ['Lime', 0.3, 'spoilage', 1], ['Coconut milk', 0.5, 'expired', 2]];

        foreach ($entries as [$item, $qty, $reason, $daysAgo]) {
            app(LogWastage::class)->execute([
                'inventory_item_id' => $items[$item]->id, 'quantity_wasted' => $qty,
                'reason' => $reason, 'recorded_date' => now()->subDays($daysAgo)->toDateString(),
            ]);
        }
    }

    private function extras(): void
    {
        SpecialEvent::create(['name' => 'Corporate lunch buffet', 'event_date' => now()->subDays(10)->toDateString(),
            'menu' => 'Nasi Lemak Ayam, Chicken Curry Rice', 'revenue' => 2400, 'cost' => 980]);

        FloatIssuance::create(['amount_given' => 500, 'amount_spent' => 0, 'amount_returned' => 0,
            'status' => 'open', 'issued_date' => now()->subDays(3)->toDateString()]);
    }
}
