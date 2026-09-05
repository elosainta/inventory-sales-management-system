<x-pdf-base title="Recipes Report">
    <h2>Recipes</h2>
    <p class="subtitle">The dishes on your menu and what each one costs to plate.</p>

    <div class="summary">
        Total recipes: <strong>{{ $recipes->count() }}</strong> &middot;
        Average plate cost: <strong>RM {{ $recipes->count() ? number_format($recipes->avg('plate_cost'), 2) : '0.00' }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Recipe</th>
                <th class="right">Serves</th>
                <th>Ingredients</th>
                <th class="right">Plate Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recipes as $recipe)
                <tr>
                    <td>{{ $recipe->name }}</td>
                    <td class="right">{{ $recipe->serving_size }}</td>
                    <td class="muted">
                        @foreach($recipe->ingredients as $ing)
                            {{ $ing->inventoryItem->name }} ({{ rtrim(rtrim(number_format($ing->quantity, 4), '0'), '.') }} {{ $ing->inventoryItem->unit }})@if(!$loop->last), @endif
                        @endforeach
                        @if($recipe->ingredients->isEmpty()) — @endif
                    </td>
                    <td class="right num">RM {{ number_format($recipe->plate_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-pdf-base>
