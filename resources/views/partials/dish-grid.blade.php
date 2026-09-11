{{-- The menu as one card per dish, the same card body the Recipes page shows,
     each opening that dish's own page. Production starts with it.

     Pass: $recipes (ingredients.inventoryItem loaded), 'dishRoute' (the
     per-dish page), 'ability' (who may open it - anyone else sees the cards
     without links), 'hint', 'cta' (the footer on a clickable card), and
     'logHeading' for the list below the grid.

     No Add, no delete and no link into the recipe itself: these pages are
     where dishes are cooked and sold, not written. --}}
@if($recipes->isNotEmpty())
    <style>
        .dish-card[data-recipe-id] { cursor:pointer; box-shadow:0 1px 2px hsla(24,10%,15%,0.04); transition:border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
        .dish-card[data-recipe-id]:hover,
        .dish-card[data-recipe-id]:focus-visible { border-color:hsl(20,60%,65%); box-shadow:0 6px 18px hsla(20,60%,45%,0.16); transform:translateY(-2px); outline:none; }
        .dish-card[data-recipe-id]:focus-visible { box-shadow:0 0 0 3px hsla(20,60%,45%,0.3); }
        .dish-card[data-recipe-id]:active { transform:translateY(0) scale(0.99); box-shadow:0 1px 4px hsla(20,60%,45%,0.15); }
        .dish-card[data-recipe-id]:hover h3 { color:hsl(20,60%,40%); }
        .dish-card-cta { margin-top:auto; padding-top:14px; display:flex; justify-content:space-between; align-items:center; font-size:13px; font-weight:500; color:hsl(20,60%,45%); }
        .dish-card-cta span:last-child { transition:transform 0.15s; }
        .dish-card[data-recipe-id]:hover .dish-card-cta span:last-child { transform:translateX(4px); }
        @media (prefers-reduced-motion: reduce) {
            .dish-card[data-recipe-id], .dish-card-cta span:last-child { transition:none; transform:none !important; }
        }
    </style>
    <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:4px;">{{ __('Dishes') }}</h2>
    @can($ability)
        <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:14px;">{{ $hint }}</p>
    @endcan
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:40px;">
        @foreach($recipes as $recipe)
            @can($ability)
                <a href="{{ route($dishRoute, $recipe) }}" class="dish-card" data-recipe-id="{{ $recipe->id }}"
                   style="display:flex; flex-direction:column; color:inherit; text-decoration:none; background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                    @include('partials.recipe-card-body')
                    <div class="dish-card-cta"><span>{{ $cta }}</span><span aria-hidden="true">→</span></div>
                </a>
            @else
                <div class="dish-card" style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                    @include('partials.recipe-card-body')
                </div>
            @endcan
        @endforeach
    </div>

    <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:14px;">{{ $logHeading }}</h2>
@endif
