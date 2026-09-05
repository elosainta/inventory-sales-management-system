<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Hidden training accounts (Head Chef and Junior Chef). At runtime they are
     * swapped onto a separate sandbox database by the UseDemoDatabase
     * middleware, so they can create/edit/delete freely and see each other's
     * changes without ever affecting real data. Only Admins can see them in the
     * Users list.
     */
    public function run(): void
    {
        // The Owner-level demo account was deleted on 2026-08-22 and is not
        // re-seeded — re-adding it here would silently bring it back.

        User::firstOrCreate(
            ['email' => 'demochef@example.test'],
            [
                'name'     => 'Demo Head Chef',
                'password' => Hash::make('welcome1234'),
                'role'     => User::ROLE_HEAD_CHEF,
                'is_demo'  => true,
            ],
        );

        User::firstOrCreate(
            ['email' => 'demojunior@example.test'],
            [
                'name'     => 'Demo Junior Chef',
                'password' => Hash::make('welcome1234'),
                'role'     => User::ROLE_JUNIOR_CHEF,
                'is_demo'  => true,
            ],
        );
    }
}
