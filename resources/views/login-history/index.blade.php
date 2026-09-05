<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Login History</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Every login recorded across all accounts.</p>
        </div>
    </div>

    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">User</th>
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">Role</th>
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($histories as $entry)
                    @php
                        $roleColors = [
                            'owner'       => ['bg' => '#fef9c3', 'text' => '#854d0e'],
                            'head_chef'   => ['bg' => '#dbeafe', 'text' => '#1e40af'],
                            'junior_chef' => ['bg' => '#f3e8ff', 'text' => '#6b21a8'],
                            'viewer'      => ['bg' => 'hsl(30,15%,92%)', 'text' => 'hsl(24,10%,30%)'],
                        ];
                        $c = $roleColors[$entry->user->role ?? 'viewer'] ?? $roleColors['viewer'];
                    @endphp
                    <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                        <td style="padding:14px 16px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:32px; height:32px; border-radius:50%; background:hsl(20,60%,45%); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0;">
                                    {{ strtoupper(substr($entry->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:500;">{{ $entry->user->name ?? '—' }}</div>
                                    <div style="font-size:12px; color:hsl(24,5%,50%);">{{ $entry->user->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:14px 16px;">
                            <span style="font-size:12px; font-weight:600; padding:3px 10px; border-radius:4px; background:{{ $c['bg'] }}; color:{{ $c['text'] }}; text-transform:capitalize;">
                                {{ str_replace('_', ' ', $entry->user->role ?? '—') }}
                            </span>
                        </td>
                        <td style="padding:14px 16px; color:hsl(24,5%,50%); font-size:13px;">
                            {{ $entry->logged_in_at->format('d M Y, H:i:s') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="padding:48px 16px; text-align:center; color:hsl(24,5%,45%);">
                            No login records yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($histories->hasPages())
        <div style="margin-top:20px;">
            {{ $histories->links() }}
        </div>
    @endif
</x-app-shell>
