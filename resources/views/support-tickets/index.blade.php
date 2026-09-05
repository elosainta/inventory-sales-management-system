<x-app-shell>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="font-size:22px; font-weight:500; color:hsl(24,10%,12%); margin:0 0 4px;">Support Tickets</h1>
        <p style="font-size:14px; color:hsl(24,5%,45%); margin:0;">Reports submitted by the team. Tick a ticket off once it's handled.</p>
    </div>
</div>

{{-- ── Status tabs ── --}}
@php
    $tabs = [
        'open'     => ['label' => 'Open',     'count' => $counts['open']],
        'resolved' => ['label' => 'Resolved', 'count' => $counts['resolved']],
        'all'      => ['label' => 'All',      'count' => $counts['open'] + $counts['resolved']],
    ];
@endphp
<div style="display:flex; gap:6px; margin-bottom:24px; border-bottom:1px solid hsl(30,15%,88%);">
    @foreach($tabs as $key => $tab)
        @php $active = $filter === $key; @endphp
        <a href="{{ route('support-tickets.index', ['status' => $key]) }}"
           style="display:flex; align-items:center; gap:8px; padding:10px 16px; font-size:14px; font-weight:500; text-decoration:none; border-bottom:2px solid {{ $active ? 'hsl(20,60%,45%)' : 'transparent' }}; color:{{ $active ? 'hsl(20,60%,40%)' : 'hsl(24,5%,50%)' }}; margin-bottom:-1px;">
            {{ $tab['label'] }}
            <span style="font-size:12px; font-family:'JetBrains Mono',monospace; background:{{ $active ? 'hsl(20,60%,92%)' : 'hsl(30,15%,93%)' }}; color:{{ $active ? 'hsl(20,60%,38%)' : 'hsl(24,5%,50%)' }}; padding:1px 7px; border-radius:10px;">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</div>

{{-- ── Ticket list ── --}}
@if($tickets->isEmpty())
    <div style="background:white; border:1px solid hsl(30,15%,88%); border-radius:8px; padding:48px; text-align:center; color:hsl(24,5%,55%); font-size:14px;">
        No {{ $filter === 'all' ? '' : $filter }} tickets.
    </div>
@else
    <div style="display:flex; flex-direction:column; gap:12px;">
        @foreach($tickets as $ticket)
            @php $resolved = $ticket->isResolved(); @endphp
            <div style="background:white; border:1px solid {{ $resolved ? 'hsl(30,15%,90%)' : 'hsl(20,60%,82%)' }}; border-radius:8px; overflow:hidden;">
                {{-- Header --}}
                <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid hsl(30,15%,92%); background:{{ $resolved ? 'hsl(40,33%,99%)' : 'hsl(20,60%,98%)' }}; gap:12px; flex-wrap:wrap;">
                    @php
                        $typeStyles = [
                            \App\Models\SupportTicket::TYPE_FEATURE => ['bg' => 'hsl(214,90%,94%)', 'fg' => 'hsl(214,80%,40%)'],
                            \App\Models\SupportTicket::TYPE_BUG     => ['bg' => 'hsl(0,75%,95%)',   'fg' => 'hsl(0,65%,45%)'],
                            \App\Models\SupportTicket::TYPE_OTHER   => ['bg' => 'hsl(30,15%,90%)',  'fg' => 'hsl(24,10%,35%)'],
                        ];
                        $ts = $typeStyles[$ticket->type] ?? null;
                    @endphp
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <span style="font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; color:hsl(20,60%,40%); background:hsl(20,60%,95%); padding:3px 8px; border-radius:4px;">{{ $ticket->ticket_number ?? '—' }}</span>
                        @if($resolved)
                            <span style="font-size:11px; font-weight:600; letter-spacing:0.05em; text-transform:uppercase; color:hsl(142,40%,35%); background:hsl(142,50%,92%); padding:3px 8px; border-radius:4px;">Resolved</span>
                        @else
                            <span style="font-size:11px; font-weight:600; letter-spacing:0.05em; text-transform:uppercase; color:hsl(20,60%,40%); background:hsl(20,60%,90%); padding:3px 8px; border-radius:4px;">Open</span>
                        @endif
                        @if($ts)
                            <span style="font-size:11px; font-weight:600; color:{{ $ts['fg'] }}; background:{{ $ts['bg'] }}; padding:3px 8px; border-radius:4px;">{{ \App\Models\SupportTicket::TYPE_LABELS[$ticket->type] }}</span>
                        @else
                            <span style="font-size:11px; font-weight:500; color:hsl(24,5%,60%); background:transparent; border:1px dashed hsl(30,15%,80%); padding:2px 7px; border-radius:4px;">Untagged</span>
                        @endif
                        <span style="font-size:14px; font-weight:500; color:hsl(24,10%,12%);">{{ $ticket->name }}</span>
                        <span style="font-size:12px; color:hsl(24,5%,55%);">{{ $ticket->email }}</span>
                        @if($ticket->role)
                            <span style="font-size:11px; text-transform:capitalize; color:hsl(24,5%,50%); background:hsl(30,15%,93%); padding:2px 7px; border-radius:4px;">{{ str_replace('_', ' ', $ticket->role) }}</span>
                        @endif
                    </div>
                    <span style="font-size:12px; color:hsl(24,5%,55%);">{{ $ticket->created_at->format('d M Y, g:i A') }}</span>
                </div>
                {{-- Body --}}
                <div style="padding:18px 24px;">
                    <pre style="font-family:'DM Sans',sans-serif; font-size:14px; line-height:1.7; color:hsl(24,10%,20%); white-space:pre-wrap; word-break:break-word; margin:0 0 16px;">{{ $ticket->description }}</pre>

                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                            @if($ticket->media_path)
                                <a href="{{ route('support-tickets.media', $ticket) }}"
                                   style="display:inline-flex; align-items:center; gap:6px; font-size:13px; color:hsl(20,60%,42%); text-decoration:none; font-weight:500;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    {{ $ticket->media_filename ?? 'Attachment' }}
                                </a>
                            @endif
                            @if($resolved && $ticket->resolved_at)
                                <span style="font-size:12px; color:hsl(24,5%,55%);">
                                    Resolved {{ $ticket->resolved_at->diffForHumans() }}{{ $ticket->resolver ? ' by ' . $ticket->resolver->name : '' }}
                                </span>
                            @endif
                        </div>

                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            {{-- Developer-only triage tag (not shown on the user support form) --}}
                            <form method="POST" action="{{ route('support-tickets.type', $ticket) }}" style="display:flex; align-items:center; gap:6px;">
                                @csrf
                                @method('PATCH')
                                <label style="font-size:12px; color:hsl(24,5%,50%);">Tag:</label>
                                <select name="type" onchange="this.form.submit()"
                                        style="font-size:13px; padding:6px 10px; border:1px solid hsl(30,15%,80%); border-radius:6px; background:white; cursor:pointer; font-family:'DM Sans',sans-serif;">
                                    <option value="" @selected(is_null($ticket->type))>Untagged</option>
                                    @foreach(\App\Models\SupportTicket::TYPE_LABELS as $val => $label)
                                        <option value="{{ $val }}" @selected($ticket->type === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>

                            <form method="POST" action="{{ route('support-tickets.toggle', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:'DM Sans',sans-serif;
                                        @if($resolved) background:white; color:hsl(24,5%,45%); border:1px solid hsl(30,15%,80%);
                                        @else background:hsl(142,50%,38%); color:white; border:1px solid hsl(142,50%,34%); @endif">
                                    @if($resolved)
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                                        Reopen
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        Mark Resolved
                                    @endif
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Pagination ── --}}
    @if($tickets->hasPages())
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; font-size:13px;">
            <span style="color:hsl(24,5%,55%);">
                Showing {{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} of {{ $tickets->total() }}
            </span>
            <div style="display:flex; gap:8px;">
                @if($tickets->onFirstPage())
                    <span style="padding:7px 14px; border:1px solid hsl(30,15%,88%); border-radius:6px; color:hsl(24,5%,70%);">Previous</span>
                @else
                    <a href="{{ $tickets->previousPageUrl() }}" style="padding:7px 14px; border:1px solid hsl(30,15%,82%); border-radius:6px; color:hsl(24,10%,20%); text-decoration:none;">Previous</a>
                @endif
                @if($tickets->hasMorePages())
                    <a href="{{ $tickets->nextPageUrl() }}" style="padding:7px 14px; border:1px solid hsl(30,15%,82%); border-radius:6px; color:hsl(24,10%,20%); text-decoration:none;">Next</a>
                @else
                    <span style="padding:7px 14px; border:1px solid hsl(30,15%,88%); border-radius:6px; color:hsl(24,5%,70%);">Next</span>
                @endif
            </div>
        </div>
    @endif
@endif

</x-app-shell>
