{{-- The body of a recipe card: name, serving, ingredients and, for whoever
     may see menu pricing, what it costs and earns. Shared by the Recipes page
     and the Production page so a dish reads the same on both.

     Expects $recipe with ingredients.inventoryItem loaded. The price block is
     view-recipes only: Production is a chef's page, and a junior chef is kept
     off every page that prices the menu. --}}
<h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">
    {{ $recipe->name }}
</h3>
<p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:8px;">
    {{ __('Serves') }} {{ $recipe->serving_size }} · {{ $recipe->ingredients->count() }} {{ $recipe->ingredients->count() === 1 ? __('ingredient') : __('ingredients') }}
</p>
@if($recipe->ingredients->isNotEmpty())
    <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:12px; line-height:1.6;">
        @foreach($recipe->ingredients as $ing)
            {{ $ing->inventoryItem->name }} ({{ rtrim(rtrim(number_format($ing->quantity, 4), '0'), '.') }} {{ $ing->inventoryItem->unit }})@if(!$loop->last), @endif
        @endforeach
    </div>
@endif
@can('view-recipes')
<div style="border-top:1px solid hsl(30,15%,90%); padding-top:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);">Plate Cost</span>
        <span style="font-size:16px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($recipe->plate_cost)</span>
    </div>
    @if($recipe->selling_price > 0)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);">Selling Price</span>
        <span style="font-size:16px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($recipe->selling_price)</span>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:6px; border-top:1px solid hsl(30,15%,93%);">
        <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(142,50%,35%);">Profit Per Dish</span>
        <span style="font-size:19px; font-weight:600; font-family:'DM Sans',sans-serif; color:hsl(142,50%,35%);">@money($recipe->profit)</span>
    </div>
    @endif
</div>
@endcan
