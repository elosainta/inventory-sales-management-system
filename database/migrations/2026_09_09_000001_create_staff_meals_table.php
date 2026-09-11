<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the kitchen cooked for its own staff, one costing sheet per meal.
 *
 * Same shape as an R&D trial and a Recipe — ingredient lines, a subtotal, a
 * miscellaneous percentage on top, a grand total — because it is the same
 * question asked of different food: what did this plate cost. The one figure
 * this sheet adds is the cost per head, which is what the Owner is actually
 * after.
 *
 * It is NOT a Recipe, and deliberately has no link to one: staff eat something
 * different every day, so the dish is typed on the header rather than picked
 * from a catalogue that would gain a row nobody sells.
 *
 * No status column: this is a report, not a request — nobody approves lunch.
 * Recording one still deducts its lines from live stock, the same as an R&D
 * trial, because the team has already eaten them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_meals', function (Blueprint $table) {
            $table->id();
            $table->date('meal_date');

            // What was cooked. Free text on purpose — see the class note.
            $table->string('dish');

            // How many staff ate it. The divisor behind cost per head, so it
            // is never zero: the column is unsigned and the request enforces
            // min:1.
            $table->unsignedInteger('pax')->default(1);

            // Matches the kitchen's own sheets, R&D and the Recipes page.
            $table->decimal('misc_percent', 5, 2)->default(30);
            $table->text('remark')->nullable();

            // nullOnDelete like every other kitchen record: deleting an account
            // must not delete what the kitchen spent. audits.user_name keeps
            // the attribution.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The page and the PDF both read this newest-first.
            $table->index('meal_date');
        });

        Schema::create('staff_meal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_meal_id')->constrained('staff_meals')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            // Snapshots, so a renamed or deleted ingredient still reads
            // correctly on an old sheet.
            $table->string('item');
            $table->string('unit')->nullable();

            // Four decimals: a costing sheet routinely says 0.005 kg of dark
            // soy, and two would round that to nothing.
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 2);

            $table->timestamps();

            $table->index('staff_meal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_meal_lines');
        Schema::dropIfExists('staff_meals');
    }
};
