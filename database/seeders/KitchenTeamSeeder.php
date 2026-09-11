<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\SectionTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KitchenTeamSeeder extends Seeder
{
    public function run(): void
    {
        // Owner
        User::firstOrCreate(['email' => 'owner@example.test'], [
            'name'     => 'Alex Tan',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_OWNER,
        ]);

        // Head Chef
        User::firstOrCreate(['email' => 'sam@example.test'], [
            'name'     => 'Sam',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_HEAD_CHEF,
        ]);

        // Junior Chefs
        $juniors = [
            ['email' => 'chef1@example.test', 'name' => 'Amir'],
            ['email' => 'chef2@example.test', 'name' => 'Siti'],
            ['email' => 'chef3@example.test', 'name' => 'Wei'],
        ];

        foreach ($juniors as $data) {
            User::firstOrCreate(['email' => $data['email']], [
                'name'     => $data['name'],
                'password' => Hash::make('password'),
                'role'     => User::ROLE_JUNIOR_CHEF,
            ]);
        }

        // Sections + Tasks
        $sections = [
            [
                'name'        => 'Cold Storage & Fridge',
                'description' => 'Temperature checks, stock rotation, and fridge cleanliness.',
                'chef_email'  => 'chef1@example.test',
                'tasks'       => [
                    ['title' => 'Fridge Temperature Check',  'description' => 'Confirm all fridges are between 1°C and 4°C.'],
                    ['title' => 'Ingredient Labelling',      'description' => 'Ensure all containers are labelled with date and item name.'],
                    ['title' => 'Stock Rotation',            'description' => 'Move older stock to the front, new stock to the back.'],
                    ['title' => 'Fridge Cleanliness',        'description' => 'Wipe down shelves, walls, and door seals.'],
                    ['title' => 'Expired Items Check',       'description' => 'Remove and dispose of any expired ingredients.'],
                ],
            ],
            [
                'name'        => 'Hot Kitchen',
                'description' => 'Equipment preheat, surface sanitation, and safety checks.',
                'chef_email'  => 'chef2@example.test',
                'tasks'       => [
                    ['title' => 'Equipment Preheat',         'description' => 'Preheat ovens, grills, and fryers to correct temperatures.'],
                    ['title' => 'Burner & Grill Clean',      'description' => 'Clean all burners and grill grates before service.'],
                    ['title' => 'Oil Check',                 'description' => 'Check fryer oil quality and top up if needed.'],
                    ['title' => 'Surface Sanitise',          'description' => 'Wipe and sanitise all hot kitchen surfaces.'],
                    ['title' => 'Fire Safety Check',         'description' => 'Confirm fire extinguisher is accessible and unobstructed.'],
                ],
            ],
            [
                'name'        => 'Prep & Mise en Place',
                'description' => 'Vegetable prep, portioning, sauces, and utensil readiness.',
                'chef_email'  => 'chef3@example.test',
                'tasks'       => [
                    ['title' => 'Vegetable Prep',            'description' => 'Wash, cut, and portion vegetables for service.'],
                    ['title' => 'Protein Portioning',        'description' => 'Portion and label all proteins for service.'],
                    ['title' => 'Sauce & Stock Check',       'description' => 'Check sauce levels and prepare if running low.'],
                    ['title' => 'Prep Surface Clean',        'description' => 'Sanitise all prep boards and work surfaces.'],
                    ['title' => 'Utensil Check',             'description' => 'Ensure all prep utensils are clean and in place.'],
                ],
            ],
        ];

        foreach ($sections as $i => $sectionData) {
            $chef = User::where('email', $sectionData['chef_email'])->first();

            $section = Section::firstOrCreate(
                ['user_id' => $chef->id],
                ['name' => $sectionData['name'], 'description' => $sectionData['description']]
            );

            foreach ($sectionData['tasks'] as $order => $task) {
                SectionTask::firstOrCreate(
                    ['section_id' => $section->id, 'title' => $task['title']],
                    ['description' => $task['description'], 'sort_order' => $order + 1]
                );
            }
        }
    }
}
