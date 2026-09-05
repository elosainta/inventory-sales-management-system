<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * One named junior chef edits recipes; the others do not.
 *
 * Hard-coded on the Owner's instruction — the per-user permission system was
 * removed, so there is no per-person switch left. The test that earns its
 * place is the last one: the grant is keyed on id AND name so that reusing id
 * 13 for somebody else grants nothing. A hard-coded exception that fails OPEN
 * would hand a future account the right to rewrite plate costs.
 */
class RecipeExceptionTest extends TestCase
{
    use RefreshDatabase;

    private function juniorNamed(string $name, ?int $id = null): User
    {
        return User::factory()->create(array_filter([
            'id'   => $id,
            'name' => $name,
            'role' => User::ROLE_JUNIOR_CHEF,
        ]));
    }

    public function test_the_named_junior_chef_reads_and_edits_recipes(): void
    {
        $riley = $this->juniorNamed('Riley Chen', 13);

        $this->assertTrue(Gate::forUser($riley)->allows('view-recipes'));
        $this->assertTrue(Gate::forUser($riley)->allows('manage-recipes'));
    }

    public function test_other_junior_chefs_still_cannot(): void
    {
        $chris = $this->juniorNamed('Chris', 10);

        $this->assertFalse(Gate::forUser($chris)->allows('view-recipes'));
        $this->assertFalse(Gate::forUser($chris)->allows('manage-recipes'));
    }

    public function test_the_exception_fails_closed_if_the_id_is_reused(): void
    {
        // The failure mode of hard-coding a person: the account is deleted and
        // id 13 comes back around. Matching the name too is what stops a
        // stranger inheriting it.
        $someoneElse = $this->juniorNamed('A New Starter', 13);

        $this->assertFalse(Gate::forUser($someoneElse)->allows('view-recipes'));
        $this->assertFalse(Gate::forUser($someoneElse)->allows('manage-recipes'));
    }

    public function test_the_exception_does_not_leak_anything_else(): void
    {
        $riley = $this->juniorNamed('Riley Chen', 13);

        foreach (['view-sales', 'view-purchases', 'view-dashboard', 'view-suppliers', 'export-pdf'] as $gate) {
            $this->assertFalse(Gate::forUser($riley)->allows($gate), "the recipe exception must not carry {$gate}");
        }
    }

    public function test_an_unsaved_user_does_not_inherit_the_exception(): void
    {
        // Both sides of the comparison are null on a user with no id and no
        // name, and `null === null` is true. The vault's gates matrix builds
        // exactly that user to evaluate gates, and reported every junior chef
        // as holding manage-recipes because of it.
        $this->assertFalse((new User)->hasRecipeException());
        $this->assertFalse((new User(['role' => User::ROLE_JUNIOR_CHEF]))->hasRecipeException());
    }
}
