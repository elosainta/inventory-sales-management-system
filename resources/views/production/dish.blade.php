@php
    $fmt   = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0';
    $made  = (int) old('quantity_produced', 1) ?: 1;
    $input = 'width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;';
@endphp

<x-app-shell>
    <a href="{{ route('production.index') }}"
       style="display:inline-block; margin-bottom:16px; font-size:14px; color:hsl(24,5%,45%); text-decoration:none;">
        &larr; {{ __('Back to production') }}
    </a>

    {{-- Header, as on the recipe page --}}
    <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:28px;">
        <div style="width:56px; height:56px; border-radius:8px; background:hsl(20,60%,90%); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <span style="font-size:28px;">📖</span>
        </div>
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:4px;">{{ $recipe->name }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">
                {{ __('Serves') }} {{ $recipe->serving_size }} · {{ $items->count() }} {{ $items->count() === 1 ? __('ingredient') : __('ingredients') }}
            </p>
        </div>
    </div>

    @if($errors->any())
        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px; color:#991b1b;">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('production.dish.store', $recipe) }}">
        @csrf

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px; margin-bottom:28px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:14px;">
                <div>
                    <label for="quantity_produced" style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">{{ __('How many made') }}</label>
                    <input type="number" id="quantity_produced" name="quantity_produced" value="{{ $made }}" min="1" step="1" required
                           style="{{ $input }} font-family:'JetBrains Mono',monospace;">
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">{{ __('Produced By') }}</label>
                    <input type="text" name="produced_by" value="{{ old('produced_by') }}" placeholder="{{ __('Staff name') }}" required maxlength="100" style="{{ $input }}">
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">{{ __('Date') }}</label>
                    <input type="date" name="production_date" value="{{ old('production_date', date('Y-m-d')) }}" required style="{{ $input }}">
                </div>
            </div>
            <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                {{ __('Notes') }} <span style="color:hsl(24,5%,45%); font-weight:400;">({{ __('optional') }})</span>
            </label>
            <input type="text" name="notes" value="{{ old('notes') }}" placeholder="{{ __('e.g. prepped for dinner service') }}" maxlength="500" style="{{ $input }}">
        </div>

        <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400; margin-bottom:4px;">{{ __('What comes off the shelf') }}</h2>
        <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:14px;">
            {{ __('Type what each ingredient actually took — a box left at 0 takes nothing off the shelf.') }}
        </p>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="overflow-x:auto;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px; min-width:560px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Ingredient') }}</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Category') }}</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600; width:190px;">{{ __('Quantity') }}</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">{{ __('Recipe says') }}</th>
                        {{-- Costs are a manager's: a junior chef is kept off every page that prices the menu. --}}
                        @can('view-recipes')
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Unit Cost</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Subtotal</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php $per = $perDish[$item->id]; @endphp
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:10px 16px; font-weight:500;">{{ $item->name }}</td>
                            <td style="padding:10px 16px;">
                                <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; background:hsl(30,15%,94%); padding:2px 8px; border-radius:4px;">{{ $item->category }}</span>
                            </td>
                            <td style="padding:10px 16px; text-align:right;">
                                <div style="display:flex; gap:6px; align-items:center; justify-content:flex-end;">
                                    {{-- Starts at 0, not at the recipe amount (the Owner, 2026-09-11):
                                         the chef types only what they used, and selecting on focus
                                         means typing replaces the 0 rather than following it. --}}
                                    <input type="number" name="used[{{ $item->id }}]" class="qty-used" required min="0" step="0.0001"
                                           value="{{ old('used.' . $item->id, 0) }}" onfocus="this.select()"
                                           data-per-dish="{{ $fmt($per) }}" data-unit-cost="{{ (float) $item->unit_cost }}"
                                           aria-label="{{ $item->name }}"
                                           style="width:110px; padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; text-align:right; font-family:'JetBrains Mono',monospace;">
                                    <span style="font-size:13px; color:hsl(24,5%,45%); min-width:34px; text-align:left;">{{ $item->unit }}</span>
                                </div>
                            </td>
                            <td style="padding:10px 16px; text-align:right; color:hsl(24,5%,45%); font-family:'JetBrains Mono',monospace; font-size:13px;">
                                <span class="recipe-says">{{ $fmt($per * $made) }}</span> {{ $item->unit }}
                            </td>
                            @can('view-recipes')
                            <td style="padding:10px 16px; text-align:right;">@money($item->unit_cost)</td>
                            <td style="padding:10px 16px; text-align:right; font-weight:500;" class="line-subtotal">@money((float) old('used.' . $item->id, 0) * (float) $item->unit_cost)</td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ route('production.index') }}"
               style="padding:9px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; color:hsl(24,10%,20%); text-decoration:none; background:white;">{{ __('Cancel') }}</a>
            <button type="submit"
                    style="background:hsl(20,60%,45%); color:white; padding:9px 20px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">{{ __('Log Production') }}</button>
        </div>
    </form>

    <script>
        (function () {
            const made = document.getElementById('quantity_produced');
            let last = parseInt(made.value, 10) || 1;
            const fmt = (n) => String(Math.round(n * 10000) / 10000);

            // An amount typed in that differs from the recipe is shown as such,
            // so a typo reads as a change. A box still at 0 is not flagged -
            // every box starts there.
            function mark(input) {
                const n        = parseInt(made.value, 10) || 1;
                const expected = parseFloat(input.dataset.perDish) * n;
                const used     = parseFloat(input.value) || 0;
                const off      = used > 0 && Math.abs(used - expected) > 0.00005;
                input.style.borderColor = off ? '#d97706' : 'hsl(30,15%,85%)';
                input.style.background  = off ? '#fffbeb' : 'white';

                const sub = input.closest('tr').querySelector('.line-subtotal');
                if (sub) { sub.textContent = 'RM ' + (used * parseFloat(input.dataset.unitCost)).toFixed(2); }
            }

            // More dishes, more of everything: every box scales, so an amount
            // corrected for one dish stays corrected for three.
            made.addEventListener('input', function () {
                const n = parseInt(made.value, 10) || 0;
                if (n < 1) { return; }
                document.querySelectorAll('.qty-used').forEach(function (input) {
                    const v = parseFloat(input.value);
                    input.value = fmt((isNaN(v) ? 0 : v) / last * n);
                    input.closest('tr').querySelector('.recipe-says').textContent = fmt(parseFloat(input.dataset.perDish) * n);
                    mark(input);
                });
                last = n;
            });

            document.addEventListener('input', function (e) {
                if (e.target.classList.contains('qty-used')) { mark(e.target); }
            });

            document.querySelectorAll('.qty-used').forEach(mark);
        })();
    </script>
</x-app-shell>
