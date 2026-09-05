<x-app-shell>

@include('partials.item-picker', ['pickerItems' => $items])

@php
    $statusPill = fn ($status) => match ($status) {
        \App\Models\RndEntry::STATUS_APPROVED => ['Approved', 'hsl(145,45%,32%)', 'hsl(145,45%,95%)', 'hsl(145,35%,80%)'],
        \App\Models\RndEntry::STATUS_REJECTED => ['Rejected', 'hsl(0,55%,45%)',   'hsl(0,70%,97%)',   'hsl(0,50%,88%)'],
        default                               => ['Pending',  'hsl(30,60%,35%)',  'hsl(40,60%,95%)',  'hsl(35,45%,82%)'],
    };
    $qty  = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    $cell = 'padding:9px 14px;';
    $num  = 'padding:9px 14px; text-align:right; font-family:\'JetBrains Mono\',monospace;';
    $foot = 'padding:8px 14px; text-align:right; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:hsl(24,5%,45%); font-weight:600;';
@endphp

    <div class="app-page-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin:0 0 4px;">{{ __('R&D') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; margin:0;">{{ __('A costing sheet per trial: everything the dish was made with, what it came to, and what it would sell for. Recording one takes the ingredients off stock straight away; the Owner approves or rejects the spend.') }}</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
        @can('export-pdf')
        <a href="{{ route('rnd.export-pdf') }}"
           style="padding:10px 16px; background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,35%); border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
            {{ __('Export PDF') }}
        </a>
        @endcan
        @can('manage-rnd')
        <button type="button" onclick="openRnd()"
                style="padding:10px 18px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">
            + {{ __('Make an R&D') }}
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
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,50%); margin-bottom:6px;">{{ __('Waiting for review') }}</div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:600;">{{ $pending->count() }}</div>
        </div>
        <div style="flex:1; min-width:180px; background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px 20px;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,50%); margin-bottom:6px;">{{ __('Pending value') }}</div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:600;">@money($pending->sum('grand_total'))</div>
        </div>
        <div style="flex:1; min-width:180px; background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px 20px;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,50%); margin-bottom:6px;">{{ __('Approved spend') }}</div>
            <div style="font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:600;">@money($approved->sum('grand_total'))</div>
        </div>
    </div>

    {{-- What each dish has cost to arrive at. Dearest first, because that is
         what the Owner scans this page for. --}}
    @if($byMenu->isNotEmpty())
    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
        <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,90%);">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin:0;">{{ __('Spent per menu') }}</h3>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:620px;">
                <thead>
                    <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                        <th style="text-align:left; padding:10px 16px; font-weight:600;">{{ __('Menu') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:80px;">{{ __('Trials') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:120px;">{{ __('Approved') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:120px;">{{ __('Waiting') }}</th>
                        <th style="text-align:right; padding:10px 16px; font-weight:600; width:130px;">{{ __('Total spent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byMenu as $row)
                    <tr style="border-bottom:1px solid hsl(30,15%,94%);">
                        <td style="padding:10px 16px; font-weight:500;">
                            {{ $row['menu'] }}
                            @if($row['recipe'])
                                <a href="{{ route('recipes.show', $row['recipe']) }}" style="font-size:11px; color:hsl(20,60%,38%); text-decoration:none; margin-left:6px;">{{ __('on the menu') }} &rarr;</a>
                            @endif
                        </td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $row['trials'] }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(145,45%,32%);">@money($row['approved'])</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(30,60%,35%);">@money($row['pending'])</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($row['total'])</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:hsl(30,15%,97%); border-top:1px solid hsl(30,15%,90%);">
                        <td style="padding:10px 16px; font-weight:600;">{{ __('All menus') }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">{{ $byMenu->sum('trials') }}</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($byMenu->sum('approved'))</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($byMenu->sum('pending'))</td>
                        <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($byMenu->sum('total'))</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div style="padding:10px 20px; border-top:1px solid hsl(30,15%,94%); font-size:12px; color:hsl(24,5%,50%);">
            {{ __('Grand totals — ingredients plus the miscellaneous overhead — and they include rejected trials, because the ingredients still left the shelf.') }}
        </div>
    </div>
    @endif

    {{-- One costing sheet per trial. Collapsed, because a sheet is a dozen
         lines and the Owner is scanning for the dish and the grand total;
         pending ones start open, since clearing the queue is the job here.
         <details> rather than JS — the browser already does this. --}}
    @forelse($entries as $entry)
        @php([$label, $fg, $bg, $border] = $statusPill($entry->status))
        <details @if($entry->isPending()) open @endif
                 style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; margin-bottom:12px;">
            <summary style="padding:14px 18px; cursor:pointer; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="font-size:15px; font-weight:600; flex:1; min-width:180px;">
                    {{ $entry->menu_name ?: '—' }}
                    <span style="font-weight:400; color:hsl(24,5%,50%); font-size:13px;">{{ $entry->serving_size }}pax</span>
                </span>
                <span style="display:inline-block; padding:3px 9px; border-radius:99px; font-size:11px; font-weight:600; color:{{ $fg }}; background:{{ $bg }}; border:1px solid {{ $border }};">{{ __($label) }}</span>
                <span style="font-size:12px; color:hsl(24,5%,50%);">{{ $entry->purchased_on->format('d M Y') }}</span>
                <span style="font-family:'JetBrains Mono',monospace; font-size:15px; font-weight:700; min-width:96px; text-align:right;">@money($entry->grand_total)</span>
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
                            @foreach($entry->lines as $line)
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
                                <td style="{{ $num }}">@money($entry->total)</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="{{ $foot }}">{{ __('Miscellaneous') }} {{ $qty($entry->misc_percent) }}%</td>
                                <td style="{{ $num }}">@money($entry->misc_amount)</td>
                            </tr>
                            <tr style="background:hsl(30,15%,97%); border-top:2px solid hsl(30,15%,88%);">
                                <td colspan="4" style="{{ $foot }} color:hsl(24,10%,25%);">{{ __('Grand total') }}</td>
                                <td style="{{ $num }} font-weight:700; font-size:15px;">@money($entry->grand_total)</td>
                            </tr>
                            @if($entry->selling_price !== null)
                            <tr>
                                <td colspan="4" style="{{ $foot }}">{{ __('Selling price') }}</td>
                                <td style="{{ $num }}">@money($entry->selling_price)</td>
                            </tr>
                            <tr style="background:hsl(30,15%,97%);">
                                <td colspan="4" style="{{ $foot }} color:hsl(24,10%,25%);">{{ __('Profit per dish') }}</td>
                                <td style="{{ $num }} font-weight:700; color:{{ $entry->profit < 0 ? 'hsl(0,55%,45%)' : 'hsl(145,45%,32%)' }};">@money($entry->profit)</td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                <div style="padding:12px 18px; display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; border-top:1px solid hsl(30,15%,94%);">
                    <div style="font-size:12px; color:hsl(24,5%,50%); flex:1; min-width:200px;">
                        @if($entry->remark)<div style="margin-bottom:3px; color:hsl(24,10%,35%);">{{ $entry->remark }}</div>@endif
                        <div>{{ __('Added by') }} {{ $entry->creator?->name ?? __('a former team member') }}</div>
                        @if($entry->decided_at)
                            <div>{{ __($label) }} {{ __('by') }} {{ $entry->decider?->name ?? __('a former team member') }}</div>
                        @endif
                        @if($entry->recipe)
                            <div style="margin-top:3px;">
                                {{ __('On the menu as') }}
                                <a href="{{ route('recipes.show', $entry->recipe) }}" style="color:hsl(20,60%,38%); text-decoration:none; font-weight:600;">{{ $entry->recipe->name }}</a>
                            </div>
                        @endif
                    </div>

                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        @can('decide-rnd')
                            @if($entry->isPending())
                                <form method="POST" action="{{ route('rnd.approve', $entry) }}" style="display:inline;">
                                    @csrf @method('PATCH')
                                    <button type="submit" style="padding:6px 12px; background:hsl(145,45%,32%); color:white; border:none; border-radius:5px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('rnd.reject', $entry) }}" style="display:inline;"
                                      onsubmit="return confirm('Reject this R&D?')">
                                    @csrf @method('PATCH')
                                    <button type="submit" style="padding:6px 12px; background:white; color:hsl(0,55%,45%); border:1px solid hsl(0,50%,82%); border-radius:5px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('Reject') }}</button>
                                </form>
                            @endif
                        @endcan
                        @if($entry->canBecomeRecipe())
                            @can('manage-recipes')
                            <button type="button"
                                    onclick="makeRecipe({{ Illuminate\Support\Js::from($entry->only(['id', 'menu_name', 'serving_size', 'selling_price'])) }})"
                                    style="padding:6px 12px; background:white; border:1px dashed hsl(20,40%,70%); color:hsl(20,60%,38%); border-radius:5px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('Make a recipe') }}</button>
                            @endcan
                        @endif
                        @can('manage-rnd')
                            @unless($entry->isLocked())
                                <button type="button"
                                        onclick="editEntry({{ Illuminate\Support\Js::from($entry) }})"
                                        style="padding:6px 10px; background:none; border:none; color:hsl(24,10%,40%); font-size:12px; cursor:pointer; text-decoration:underline;">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('rnd.destroy', $entry) }}" style="display:inline;"
                                      onsubmit="return confirm('Remove this R&D?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:16px; padding:4px 6px;">&times;</button>
                                </form>
                            @endunless
                        @endcan
                    </div>
                </div>
            </div>
        </details>
    @empty
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:32px; text-align:center; color:hsl(24,5%,50%); font-size:14px;">
            {{ __('Nothing recorded yet.') }}
        </div>
    @endforelse

    @can('manage-rnd')
    {{-- One modal for adding and editing: two would mean two line editors on
         one page fighting over the same element ids. --}}
    <div id="rnd-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; padding:20px; z-index:50;">
        <div style="background:white; border-radius:10px; padding:24px; width:100%; max-width:760px; max-height:92vh; overflow-y:auto;">
            <h2 id="rnd-modal-title" style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:500; margin:0 0 6px;">{{ __('Make an R&D') }}</h2>
            <p id="rnd-edit-hint" style="font-size:12px; color:hsl(24,5%,50%); margin:0 0 10px; display:none;">
                {{ __('Editing does not move stock again, not even for an ingredient you add here — correct the shelf with a Tally.') }}
            </p>
            <p id="rnd-reject-hint" style="font-size:12px; color:hsl(30,60%,35%); margin:0 0 10px; display:none;">
                {{ __('This one was rejected. Saving your correction sends it back to the Owner.') }}
            </p>
            <form id="rnd-form" method="POST" action="{{ route('rnd.store') }}">
                @csrf
                <input type="hidden" name="_method" id="rnd-method" value="POST">
                @include('rnd.fields')
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" onclick="document.getElementById('rnd-modal').style.display='none'"
                            style="padding:9px 18px; background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; cursor:pointer;">{{ __('Cancel') }}</button>
                    <button type="submit" style="padding:9px 20px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const rndForm = document.getElementById('rnd-form');

        function showRnd(title, editing) {
            document.getElementById('rnd-modal-title').textContent = title;
            document.getElementById('rnd-edit-hint').style.display = editing ? '' : 'none';
            document.getElementById('rnd-modal').style.display     = 'flex';
        }

        function openRnd() {
            // reset() puts every field back to the HTML default, which IS the
            // add-form default — today's date, 1 pax, 30% miscellaneous.
            rndForm.reset();
            rndForm.action = '{{ route('rnd.store') }}';
            document.getElementById('rnd-method').value        = 'POST';
            document.getElementById('rnd-reject-hint').style.display = 'none';
            resetRndLines();
            addRndLine();
            showRnd(@json(__('Make an R&D')), false);
        }

        function editEntry(entry) {
            rndForm.reset();
            rndForm.action = '{{ url('rnd') }}/' + entry.id;
            document.getElementById('rnd-method').value = 'PATCH';

            rndForm.querySelector('[name="menu_name"]').value      = entry.menu_name ?? '';
            rndForm.querySelector('[name="serving_size"]').value   = entry.serving_size;
            rndForm.querySelector('[name="purchased_on"]').value   = entry.purchased_on.slice(0, 10);
            rndForm.querySelector('[name="misc_percent"]').value   = parseFloat(entry.misc_percent);
            rndForm.querySelector('[name="selling_price"]').value  = entry.selling_price === null ? '' : parseFloat(entry.selling_price);
            rndForm.querySelector('[name="remark"]').value         = entry.remark ?? '';

            resetRndLines();
            (entry.lines || []).forEach(addRndLine);
            if (!(entry.lines || []).length) addRndLine();

            document.getElementById('rnd-reject-hint').style.display = entry.status === 'rejected' ? '' : 'none';
            showRnd(@json(__('Edit R&D')), true);
        }
    </script>
    @endcan

    {{-- Write an approved trial up as a dish. The ingredients and quantities
         come off the sheet on the server, so this form only asks for what a
         trial does not already know. --}}
    @can('manage-recipes')
    <div id="recipe-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; padding:20px; z-index:50;">
        <div style="background:white; border-radius:10px; padding:24px; width:100%; max-width:460px;">
            <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:500; margin-bottom:6px;">{{ __('Make a recipe from this trial') }}</h2>
            <p style="font-size:12px; color:hsl(24,5%,50%); margin:0 0 16px;">
                {{ __('Every ingredient on the sheet is carried over, with its quantity and the same miscellaneous percentage. Check them on the Recipes page afterwards — a trial is rarely a finished portion.') }}
            </p>
            <form id="recipe-form" method="POST">
                @csrf
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:5px;">{{ __('Dish name') }}</label>
                    <input type="text" name="name" id="recipe-name" required maxlength="255"
                           style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:5px;">{{ __('Serving size') }}</label>
                        <input type="number" name="serving_size" id="recipe-serving" value="1" min="1" required
                               style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:5px;">{{ __('Selling price (RM)') }}</label>
                        <input type="number" name="selling_price" id="recipe-price" step="0.01" min="0" required
                               style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('recipe-modal').style.display='none'"
                            style="padding:9px 18px; background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; cursor:pointer;">{{ __('Cancel') }}</button>
                    <button type="submit" style="padding:9px 20px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">{{ __('Create recipe') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function makeRecipe(entry) {
            const form = document.getElementById('recipe-form');
            form.action = '{{ url('rnd') }}/' + entry.id + '/recipe';
            // Seeded off the trial — the menu name IS the dish, and the sheet
            // already carries the serving size and the price it was aimed at.
            document.getElementById('recipe-name').value    = entry.menu_name ?? '';
            document.getElementById('recipe-serving').value = entry.serving_size;
            document.getElementById('recipe-price').value   = entry.selling_price === null ? '' : parseFloat(entry.selling_price);
            document.getElementById('recipe-modal').style.display = 'flex';
            document.getElementById('recipe-name').select();
        }
    </script>
    @endcan

</x-app-shell>
