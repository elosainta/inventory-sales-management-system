<?php

namespace Database\Seeders;

use App\Models\StockTakeItem;
use Illuminate\Database\Seeder;

class StockTakeItemSeeder extends Seeder
{
    /**
     * Seed the editable Pantry catalog from Sam's raw stock-take notes
     * (FEATURE-IDEAS.txt, 27-Jun-2026). Idempotent: keyed on (section, name)
     * so re-running never duplicates. Units are best-guess defaults — the team
     * edits them in-app. The Kitchen catalog was seeded here until 2026-08-21;
     * that section was retired unused, so re-seeding it only re-created rows
     * nothing could reach.
     */
    public function run(): void
    {
        $pantry = [
            'Oyster sauce' => 'bottle', 'Sweet sauce' => 'bottle', 'Worcestershire' => 'bottle',
            'Light soya' => 'bottle', 'Thick soya' => 'bottle', 'Vinegar' => 'bottle',
            'Sesame oil' => 'bottle', 'Red palm oil' => 'bottle', 'Hua tiao chiew' => 'bottle',
            'Sweet creamer' => 'can', 'Evaporated creamer' => 'can', 'Carribean jerk' => 'pkt',
            'Louisiana Cajun' => 'pkt', 'Whole kernel corn' => 'can', 'Black pepper coarse' => 'pkt',
            'Paprika powder' => 'pkt', 'Castor sugar' => 'pkt', 'Fennel powder' => 'pkt',
            'Garlic flakes' => 'pkt', 'Jintan putih' => 'pkt', 'Curing salt' => 'pkt',
            'Buah keras' => 'kg', 'Egg' => 'tray', 'Caputo pasta flour' => 'pkt',
            'Onion powder' => 'pkt', 'Multi purpose flour' => 'pkt', 'Chicken stock' => 'pkt',
            'Golden salted egg powder' => 'pkt', 'Tapioca starch' => 'pkt', 'Corn flour' => 'pkt',
            'Bread crumb' => 'pkt', 'Sichuan green peppercorn' => 'pkt', 'Dried sichuan pepper' => 'pkt',
            'Red chili powder' => 'kg', 'Garam masala B' => 'kg', 'Turmeric' => 'kg',
            'Serbuk ketumbar' => 'kg', 'White sesame' => 'kg', 'Kacang dhali' => 'kg',
            'Split green bean' => 'pkt', 'Serbuk roti Japan' => 'pkt', 'Black sesame' => 'pkt',
            'Spicy black bean sauce' => 'jar', 'Wasabi powder' => 'pkt', 'Wakame' => 'pkt',
            'Ground almond' => 'pkt', 'Pani puri flour' => 'pkt', 'Desiccated coconut' => 'pkt',
            'Rendang mix' => 'pkt', 'Five spices powder' => 'pkt', 'Coconut paste' => 'pkt',
            'Pea powder' => 'pkt', 'Tamarind paste' => 'pkt', 'Red onion' => 'kg',
        ];

        $this->seedSection(StockTakeItem::SECTION_PANTRY, $pantry);
    }

    private function seedSection(string $section, array $items): void
    {
        $order = 0;
        foreach ($items as $name => $unit) {
            StockTakeItem::updateOrCreate(
                ['section' => $section, 'name' => $name],
                ['default_unit' => $unit, 'sort_order' => $order++],
            );
        }
    }
}
