<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Wastage</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">The cost of what doesn't make it to the plate.</p>
        </div>
        <div style="display:flex; gap:8px;">
            @can('export-pdf')
            <a href="{{ route('wastage.export-pdf') }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @endcan
            @can('manage-wastage')
            <button onclick="document.getElementById('add-modal').style.display='flex'"
                    style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                + Log Wastage
            </button>
            @endcan
        </div>
    </div>
    @include('partials.period-filter', ['route' => 'wastage.index', 'totalLabel' => 'Total lost', 'total' => $totalLost])
    @if($entries->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            @if($totalOnRecord > 0)
                Nothing here for this period. There {{ $totalOnRecord === 1 ? 'is' : 'are' }}
                <strong>{{ $totalOnRecord }}</strong> {{ \Illuminate\Support\Str::plural('wastage entry', $totalOnRecord) }} on record — change the month above to see them.
            @else
                No wastage logged yet.
            @endif
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Ingredient</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Quantity</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Reason</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Cost Lost</th>
                        @canany(['manage-wastage', 'delete-entries'])
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $entry->recorded_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px;">{{ $entry->inventoryItem->name }}</td>
                            <td style="padding:12px 16px;">{{ $entry->quantity_wasted }} {{ $entry->inventoryItem->unit }}</td>
                            <td style="padding:12px 16px;">
                                <span style="background:hsl(30,15%,92%); font-size:12px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:capitalize;">
                                    {{ $entry->reason }}
                                </span>
                            </td>
                            <td style="padding:12px 16px; font-weight:500; color:hsl(0,70%,50%);">
                                @money($entry->cost_lost)
                            </td>
                            @canany(['manage-wastage', 'delete-entries'])
                            <td style="padding:12px 16px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    @can('manage-wastage')
                                    <button onclick="openEditModal(this)"
                                            data-edit-url="{{ route('wastage.update', $entry) }}"
                                            data-item-id="{{ $entry->inventory_item_id }}"
                                            data-qty="{{ $entry->quantity_wasted }}"
                                            data-reason="{{ $entry->reason }}"
                                            data-date="{{ $entry->recorded_date->format('Y-m-d') }}"
                                            style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); padding:4px;" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    @endcan
                                    @can('delete-entries')
                                    <form action="{{ route('wastage.destroy', $entry) }}" method="POST"
                                          onsubmit="return confirm('Remove this entry?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @can('manage-wastage')
    {{-- Edit Modal --}}
    <div id="edit-modal"
         onclick="if(event.target===this)document.getElementById('edit-modal').style.display='none'"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Wastage</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Correct a mistake — inventory won't be re-adjusted.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form id="edit-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Ingredient</label>
                    <select id="edit-item" name="inventory_item_id" required
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select ingredient</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Quantity</label>
                        <input type="number" id="edit-qty" name="quantity_wasted" step="0.01" min="0.01" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Reason</label>
                        <select id="edit-reason" name="reason" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="spoilage">Spoilage</option>
                            <option value="expired">Expired</option>
                            <option value="burnt">Burnt</option>
                            <option value="dropped">Dropped</option>
                            <option value="over-prepped">Over-Prepped</option>
                            <option value="contaminated">Contaminated</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" id="edit-date" name="recorded_date" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('edit-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(btn) {
            var d = btn.dataset;
            document.getElementById('edit-form').action = d.editUrl;
            document.getElementById('edit-item').value = d.itemId;
            document.getElementById('edit-qty').value = d.qty;
            document.getElementById('edit-reason').value = d.reason;
            document.getElementById('edit-date').value = d.date;
            document.getElementById('edit-modal').style.display = 'flex';
            document.getElementById('edit-item').focus();
        }
    </script>

    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Log Wastage</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Record what was lost so you can find the pattern.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('wastage.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Ingredient</label>
                    <select name="inventory_item_id" required
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select ingredient</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Quantity</label>
                        <input type="number" name="quantity_wasted" value="0" step="0.01" min="0.01" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Reason</label>
                        <select name="reason" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="spoilage">Spoilage</option>
                            <option value="expired">Expired</option>
                            <option value="burnt">Burnt</option>
                            <option value="dropped">Dropped</option>
                            <option value="over-prepped">Over-Prepped</option>
                            <option value="contaminated">Contaminated</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="recorded_date" value="{{ date('Y-m-d') }}" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Log wastage
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-shell>