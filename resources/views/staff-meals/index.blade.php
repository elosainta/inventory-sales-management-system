<x-app-shell>

@include('partials.item-picker', ['pickerItems' => $items])

@php
    $qty  = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    $cell = 'padding:9px 14px;';
    $num  = 'padding:9px 14px; text-align:right; font-family:\'JetBrains Mono\',monospace;';
    $foot = 'padding:8px 14px; text-align:right; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:hsl(24,5%,45%); font-weight:600;';

    // This month, off the collection the page already holds.
    $thisMonth = $meals->filter(fn ($meal) => $meal->meal_date->isSameMonth(now()));
@endphp

    <div class="app-page-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin:0 0 4px;">{{ __("Staff's Meal") }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; margin:0;">{{ __('A costing sheet per meal: what was cooked for the team that day, what it was made with, and what it came to. Recording one takes the ingredients off stock straight away.') }}</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
        @can('export-pdf')
        <a href="{{ route('staff-meals.export-pdf') }}"
           style="padding:10px 16px; background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,35%); border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
            {{ __('Export PDF') }}
        </a>
        @endcan
        @can('manage-staff-meal')
        <button type="button" onclick="openStaffMeal()"
                style="padding:10px 18px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">
            + {{ __('Record a meal') }}
        </button>
        @endcan
        </div>
    </div>

    @if(session('success'))
        <div style="margin-bottom:16px; padding:12px 16px; background:hsl(145,45%,96%); border:1px solid hsl(145,35%,82%); border-radius:6px; color:hsl(145,45%,28%); font-size:14px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="margin-bottom:16px; padding:12px 16px; background:hsl(0,70%,96%); border:1px solid hsl(0,60%,85%); border-radius:6px; color:hsl(0,55%,38%); font-size:14px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-bottom:16px; padding:12px 16px; background:hsl(0,70%,96%); border:1px solid hsl(0,60%,85%); border-radius:6px; color:hsl(0,55%,38%); font-size:14px;">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
        <div style="flex:1; min-width:180px; background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px 20px;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,50%); margin-bottom:6px;">{{ __('Meals this month') }}</div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:600;">{{ $thisMonth->count() }}</div>
        </div>
        <div style="flex:1; min-width:180px; background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px 20px;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,50%); margin-bottom:6px;">{{ __('Spent this month') }}</div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:600;">@money($thisMonth->sum('grand_total'))</div>
        </div>
    </div>

    {{-- What feeding the team costs, month by month. The report half of the
         page: the sheets below are the working, this is the answer. --}}
    @if($byMonth->isNotEmpty())
    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
        <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,90%);">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin:0;">{{ __('Spent per month') }}</h3>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:560px;">
                <thead>
                    <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                        <th style="text-align:left; padding:10px 16px; font-weight:600;">{{ __('Month') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:90px;">{{ __('Meals') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:130px;">{{ __('Total spent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byMonth as $row)
                    <tr style="border-bottom:1px solid hsl(30,15%,94%);">
                        <td style="padding:10px 16px; font-weight:500;">{{ $row['month'] }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $row['meals'] }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($row['total'])</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:hsl(30,15%,97%); border-top:1px solid hsl(30,15%,90%);">
                        <td style="padding:10px 16px; font-weight:600;">{{ __('All months') }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">{{ $byMonth->sum('meals') }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($byMonth->sum('total'))</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- One costing sheet per meal, newest first. <details> rather than JS —
         the browser already does this, and the Owner is scanning the summary
         lines for the day, the dish and what it came to. --}}
    @forelse($meals as $meal)
        <details style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; margin-bottom:12px;">
            <summary style="padding:14px 18px; cursor:pointer; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="font-size:12px; color:hsl(24,5%,50%); min-width:92px;">{{ $meal->meal_date->format('d M Y') }}</span>
                <span style="font-size:15px; font-weight:600; flex:1; min-width:180px;">
                    {{ $meal->dish }}
                </span>
                <span style="font-family:'JetBrains Mono',monospace; font-size:15px; font-weight:700; min-width:96px; text-align:right;">@money($meal->grand_total)</span>
            </summary>

            <div style="border-top:1px solid hsl(30,15%,92%);">
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:600px;">
                        <thead>
                            <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                                <th style="text-align:left; {{ $cell }} font-weight:600;">{{ __('Ingredient') }}</th>
                                <th style="text-align:right; {{ $cell }} font-weight:600; width:130px;">{{ __('Price per unit') }}</th>
                                <th style="text-align:left; {{ $cell }} font-weight:600; width:80px;">{{ __('Unit') }}</th>
                                <th style="text-align:right; {{ $cell }} font-weight:600; width:110px;">{{ __('Qty used') }}</th>
                                <th style="text-align:right; {{ $cell }} font-weight:600; width:110px;">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meal->lines as $line)
                            <tr style="border-bottom:1px solid hsl(30,15%,95%);">
                                <td style="{{ $cell }}">{{ $line->item }}</td>
                                <td style="{{ $num }}">@money($line->unit_price)</td>
                                <td style="{{ $cell }} color:hsl(24,5%,50%);">{{ $line->unit ?: '—' }}</td>
                                <td style="{{ $num }}">{{ $qty($line->quantity) }}</td>
                                <td style="{{ $num }}">@money($line->total)</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="{{ $foot }}">{{ __('Total') }}</td>
                                <td style="{{ $num }}">@money($meal->total)</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="{{ $foot }}">{{ __('Miscellaneous') }} {{ $qty($meal->misc_percent) }}%</td>
                                <td style="{{ $num }}">@money($meal->misc_amount)</td>
                            </tr>
                            <tr style="background:hsl(30,15%,97%); border-top:2px solid hsl(30,15%,88%);">
                                <td colspan="4" style="{{ $foot }} color:hsl(24,10%,25%);">{{ __('Grand total') }}</td>
                                <td style="{{ $num }} font-weight:700; font-size:15px;">@money($meal->grand_total)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="padding:12px 18px; display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; border-top:1px solid hsl(30,15%,94%);">
                    <div style="font-size:12px; color:hsl(24,5%,50%); flex:1; min-width:200px;">
                        @if($meal->remark)<div style="margin-bottom:3px; color:hsl(24,10%,35%);">{{ $meal->remark }}</div>@endif
                        <div>{{ __('Recorded by') }} {{ $meal->creator?->name ?? __('a former team member') }}</div>
                    </div>

                    @can('manage-staff-meal')
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <button type="button"
                                onclick="editStaffMeal({{ Illuminate\Support\Js::from($meal) }})"
                                style="padding:6px 10px; background:none; border:none; color:hsl(24,10%,40%); font-size:12px; cursor:pointer; text-decoration:underline;">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('staff-meals.destroy', $meal) }}" style="display:inline;"
                              onsubmit="return confirm('Remove this staff meal? The stock it used is not put back.')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:16px; padding:4px 6px;">&times;</button>
                        </form>
                    </div>
                    @endcan
                </div>
            </div>
        </details>
    @empty
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:32px; text-align:center; color:hsl(24,5%,50%); font-size:14px;">
            {{ __('Nothing recorded yet.') }}
        </div>
    @endforelse

    @can('manage-staff-meal')
    {{-- One modal for adding and editing: two would mean two line editors on
         one page fighting over the same element ids. --}}
    <div id="meal-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; padding:20px; z-index:50;">
        <div style="background:white; border-radius:10px; padding:24px; width:100%; max-width:760px; max-height:92vh; overflow-y:auto;">
            <h2 id="meal-modal-title" style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:500; margin:0 0 6px;">{{ __('Record a staff meal') }}</h2>
            <p id="meal-edit-hint" style="font-size:12px; color:hsl(24,5%,50%); margin:0 0 10px; display:none;">
                {{ __('Editing does not move stock again, not even for an ingredient you add here — correct the shelf with a Tally.') }}
            </p>
            <form id="meal-form" method="POST" action="{{ route('staff-meals.store') }}">
                @csrf
                <input type="hidden" name="_method" id="meal-method" value="POST">
                @include('staff-meals.fields')
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('meal-modal').style.display='none'"
                            style="padding:9px 18px; background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; cursor:pointer;">{{ __('Cancel') }}</button>
                    <button type="submit" style="padding:9px 20px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const mealForm = document.getElementById('meal-form');

        function showStaffMeal(title, editing) {
            document.getElementById('meal-modal-title').textContent = title;
            document.getElementById('meal-edit-hint').style.display = editing ? '' : 'none';
            document.getElementById('meal-modal').style.display     = 'flex';
        }

        function openStaffMeal() {
            // reset() puts every field back to the HTML default, which IS the
            // add-form default — today's date and 30% miscellaneous.
            mealForm.reset();
            mealForm.action = '{{ route('staff-meals.store') }}';
            document.getElementById('meal-method').value = 'POST';
            resetStaffMealLines();
            addStaffMealLine();
            showStaffMeal(@json(__('Record a staff meal')), false);
        }

        function editStaffMeal(meal) {
            mealForm.reset();
            mealForm.action = '{{ url('staff-meals') }}/' + meal.id;
            document.getElementById('meal-method').value = 'PATCH';

            mealForm.querySelector('[name="meal_date"]').value    = meal.meal_date.slice(0, 10);
            mealForm.querySelector('[name="dish"]').value         = meal.dish ?? '';
            mealForm.querySelector('[name="misc_percent"]').value = parseFloat(meal.misc_percent);
            mealForm.querySelector('[name="remark"]').value       = meal.remark ?? '';

            resetStaffMealLines();
            (meal.lines || []).forEach(addStaffMealLine);
            if (!(meal.lines || []).length) addStaffMealLine();

            showStaffMeal(@json(__('Edit staff meal')), true);
        }
    </script>
    @endcan

</x-app-shell>
