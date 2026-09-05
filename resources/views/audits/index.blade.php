<x-app-shell>
    <div style="margin-bottom:32px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Audit Log</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">Every change to the system, who made it, and when.</p>
    </div>

    @if($audits->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No activity recorded yet.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">When</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Who</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Action</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">On</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Record ID</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($audits as $audit)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">
                                {{ $audit->created_at->format('M d, Y H:i') }}
                            </td>
                            <td style="padding:12px 16px;">
                                {{ $audit->user?->name ?? $audit->user_name ?? 'System' }}
                            </td>
                            <td style="padding:12px 16px;">
                                @php
                                    $color = match($audit->action) {
                                        'created' => '#dcfce7|#166534',
                                        'updated' => '#fef9c3|#854d0e',
                                        'deleted' => '#fee2e2|#991b1b',
                                        default   => '#e5e7eb|#374151',
                                    };
                                    [$bg, $fg] = explode('|', $color);
                                @endphp
                                <span style="background:{{ $bg }}; color:{{ $fg }}; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; text-transform:capitalize;">
                                    {{ $audit->action }}
                                </span>
                            </td>
                            <td style="padding:12px 16px;">
                                @php
                                    $label = match(class_basename($audit->auditable_type)) {
                                        'Purchase'         => 'Purchase',
                                        'PurchaseLine'     => 'Purchase Line',
                                        'Sale'             => 'Sale',
                                        'WastageEntry'     => 'Wastage Entry',
                                        'FloatIssuance'    => 'Float Issuance',
                                        'InventoryItem'    => 'Inventory Item',
                                        'Supplier'         => 'Supplier',
                                        'Recipe'           => 'Recipe',
                                        'RecipeIngredient' => 'Recipe Ingredient',
                                        'SpecialEvent'     => 'Special Event',
                                        'Section'          => 'Section',
                                        'SectionTask'      => 'Section Task',
                                        'ComplianceReport' => 'Compliance Report',
                                        'FeedbackEntry'    => 'Feedback',
                                        'StockTake'        => 'Stock-take',
                                        'StockTakeItem'    => 'Stock-take Item',
                                        'InventoryTally'   => 'Tally Check',
                                        'User'             => 'User',
                                        default            => class_basename($audit->auditable_type),
                                    };
                                @endphp
                                {{ $label }}
                            </td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">
                                #{{ $audit->auditable_id }}
                                @if($audit->after)
                                    @php $name = $audit->after['name'] ?? $audit->after['title'] ?? $audit->after['message'] ?? null; @endphp
                                    @if($name)
                                        <span style="color:hsl(24,10%,30%);"> — {{ Str::limit($name, 40) }}</span>
                                    @endif
                                @elseif($audit->before)
                                    @php $name = $audit->before['name'] ?? $audit->before['title'] ?? $audit->before['message'] ?? null; @endphp
                                    @if($name)
                                        <span style="color:hsl(24,10%,30%);"> — {{ Str::limit($name, 40) }}</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:24px;">
            {{ $audits->links() }}
        </div>
    @endif
</x-app-shell>