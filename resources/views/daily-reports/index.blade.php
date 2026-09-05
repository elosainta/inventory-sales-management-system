<x-app-shell>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="font-size:22px; font-weight:500; color:hsl(24,10%,12%); margin:0 0 4px;">Daily Report</h1>
        <p style="font-size:14px; color:hsl(24,5%,45%); margin:0;">
            @can('write-daily-report')
                Write your daily briefing for the owner.
            @else
                Head chef's daily briefings.
            @endcan
        </p>
    </div>
</div>

@can('write-daily-report')
{{-- ── Write / Edit today's report ── --}}
<div style="background:white; border:1px solid hsl(30,15%,88%); border-radius:8px; padding:24px; margin-bottom:28px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
        <div style="width:8px; height:8px; border-radius:50%; background:hsl(20,60%,45%);"></div>
        <span style="font-size:14px; font-weight:500; color:hsl(24,10%,12%);">
            Today — {{ today()->format('l, d M Y') }}
        </span>
        @if($today)
            <span style="font-size:12px; color:hsl(24,5%,55%); margin-left:4px;">Last saved {{ $today->updated_at->diffForHumans() }}</span>
        @endif
    </div>
    @if($today && $today->user_id !== auth()->id())
        {{-- Someone else already wrote today's. Show it, don't offer to replace it. --}}
        <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:12px;">
            Already done — written by {{ $today->user?->name ?? 'a Head Chef' }}.
        </p>
        <div style="padding:14px 16px; border:1px solid hsl(30,15%,88%); border-radius:6px; background:hsl(40,33%,99%); font-size:14px; line-height:1.6; white-space:pre-wrap;">{{ $today->body }}</div>
    @else
    <form method="POST" action="{{ route('daily-report.store') }}">
        @csrf
        <textarea
            name="body"
            rows="10"
            placeholder="Hello Mr. Josh,&#10;&#10;- Today's briefing..."
            style="width:100%; padding:14px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; font-family:'DM Sans',sans-serif; color:hsl(24,10%,15%); background:hsl(40,33%,99%); resize:vertical; outline:none; box-sizing:border-box; line-height:1.6;"
            onfocus="this.style.borderColor='hsl(20,60%,45%)'"
            onblur="this.style.borderColor='hsl(30,15%,85%)'"
        >{{ old('body', $today?->body) }}</textarea>
        @error('body')
            <p style="margin:6px 0 0; font-size:13px; color:#dc2626;">{{ $message }}</p>
        @enderror
        <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <button type="submit"
                    style="padding:10px 24px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer; font-family:'DM Sans',sans-serif;">
                {{ $today ? 'Update Report' : 'Submit Report' }}
            </button>
        </div>
    </form>
    @endif
</div>
@endcan

{{-- ── Month filter ── --}}
<div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
    <span style="font-size:13px; font-weight:500; color:hsl(24,5%,45%);">Month:</span>
    <form method="GET" action="{{ route('daily-report.index') }}" style="display:flex; gap:8px; align-items:center;">
        <input type="month" name="month" value="{{ $month }}"
               style="padding:7px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px; font-family:'DM Sans',sans-serif; color:hsl(24,10%,15%); background:white; cursor:pointer;"
               onchange="this.form.submit()">
    </form>
</div>

{{-- ── Report list ── --}}
@if($reports->isEmpty())
    <div style="background:white; border:1px solid hsl(30,15%,88%); border-radius:8px; padding:48px; text-align:center; color:hsl(24,5%,55%); font-size:14px;">
        No reports for this month.
    </div>
@else
    <div style="display:flex; flex-direction:column; gap:12px;">
        @foreach($reports as $report)
            @php
                $isToday = $report->report_date->isToday();
            @endphp
            <div style="background:white; border:1px solid {{ $isToday ? 'hsl(20,60%,60%)' : 'hsl(30,15%,88%)' }}; border-radius:8px; overflow:hidden;">
                {{-- Header --}}
                <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid hsl(30,15%,92%); background:{{ $isToday ? 'hsl(20,60%,97%)' : 'hsl(40,33%,99%)' }}; cursor:pointer;"
                     onclick="toggleReport({{ $report->id }})">
                    <div style="display:flex; align-items:center; gap:12px;">
                        @if($isToday)
                            <span style="font-size:11px; font-weight:600; letter-spacing:0.05em; text-transform:uppercase; color:hsl(20,60%,40%); background:hsl(20,60%,90%); padding:3px 8px; border-radius:4px;">Today</span>
                        @endif
                        <span style="font-size:14px; font-weight:500; color:hsl(24,10%,12%);">
                            {{ $report->report_date->format('l, d M Y') }}
                        </span>
                        <span style="font-size:12px; color:hsl(24,5%,55%);">
                            by {{ $report->user?->name ?? '—' }}
                        </span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:12px; color:hsl(24,5%,55%);">{{ $report->updated_at->format('g:i A') }}</span>
                        <svg id="chevron-{{ $report->id }}" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(24,5%,55%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition:transform 0.2s; {{ $isToday ? 'transform:rotate(180deg)' : '' }}">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                </div>
                {{-- Body --}}
                <div id="report-body-{{ $report->id }}" style="display:{{ $isToday ? 'block' : 'none' }}; padding:20px 24px;">
                    <pre style="font-family:'DM Sans',sans-serif; font-size:14px; line-height:1.7; color:hsl(24,10%,20%); white-space:pre-wrap; word-break:break-word; margin:0;">{{ $report->body }}</pre>
                </div>
            </div>
        @endforeach
    </div>
@endif

<script>
    function toggleReport(id) {
        var body    = document.getElementById('report-body-' + id);
        var chevron = document.getElementById('chevron-' + id);
        var open    = body.style.display === 'none';
        body.style.display    = open ? 'block' : 'none';
        chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
    }
</script>

</x-app-shell>
