<x-app-shell>
    <div class="app-page-header" style="margin-bottom:16px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Prep Checklist') }}</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">{{ __('Who did what, day by day.') }}</p>
    </div>

    @include('partials.prep-tabs', ['active' => 'history'])

    {{-- Day picker. A native date input rather than a calendar library, and a
         GET form, so the chosen day is in the URL and can be sent to someone. --}}
    <form method="GET" action="{{ route('prep.history') }}"
          style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="{{ route('prep.history', ['date' => $date->copy()->subDay()->toDateString()]) }}"
           style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; text-decoration:none; color:hsl(24,10%,25%); font-size:14px;">&larr;</a>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ \App\Models\SectionCheck::kitchenToday()->toDateString() }}"
               onchange="this.form.submit()"
               style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:white;">
        @if($date->lt(\App\Models\SectionCheck::kitchenToday()))
            <a href="{{ route('prep.history', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; text-decoration:none; color:hsl(24,10%,25%); font-size:14px;">&rarr;</a>
        @endif
        <span style="font-size:14px; color:hsl(24,5%,45%); margin-left:4px;">{{ $date->format('l, d M Y') }}</span>
    </form>

    {{-- The days that actually hold work, so the page can be browsed without
         guessing dates into the box. --}}
    @if($recentDays->isNotEmpty())
        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:24px;">
            @foreach($recentDays as $day)
                @php $on = $day->isSameDay($date); @endphp
                <a href="{{ route('prep.history', ['date' => $day->toDateString()]) }}"
                   style="padding:5px 11px; border-radius:20px; font-size:12px; text-decoration:none;
                          border:1px solid {{ $on ? 'hsl(20,60%,45%)' : 'hsl(30,15%,85%)' }};
                          background:{{ $on ? 'hsl(20,60%,45%)' : 'white' }};
                          color:{{ $on ? 'white' : 'hsl(24,10%,25%)' }};">{{ $day->format('d M') }}</a>
            @endforeach
        </div>
    @endif

    @php $doneThatDay = $sections->sum(fn ($s) => $s->tasks->filter(fn ($t) => $t->checks->isNotEmpty())->count()); @endphp

    @if($doneThatDay === 0)
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; text-align:center; padding:56px 24px; color:hsl(24,5%,45%); font-size:14px;">
            {{ __('Nothing was ticked on this day.') }}
        </div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px;">
            @foreach($sections as $section)
                @php
                    $total = $section->tasks->count();
                    $done  = $section->tasks->filter(fn ($t) => $t->checks->isNotEmpty())->count();
                @endphp
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
                    <div style="padding:16px 20px; border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,98%); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-family:'DM Sans',sans-serif; font-size:15px; font-weight:500;">{{ $section->name }}</span>
                        <span style="font-size:12px; font-weight:600; color:hsl(24,5%,45%); font-family:'JetBrains Mono',monospace;">{{ $done }}/{{ $total }}</span>
                    </div>
                    @foreach($section->tasks as $task)
                        @php $check = $task->checks->first(); @endphp
                        <div style="padding:10px 20px; border-bottom:1px solid hsl(30,15%,93%); display:flex; align-items:center; justify-content:space-between; gap:12px;">
                            <div>
                                <div style="font-size:13px; font-weight:500; color:{{ $check ? 'inherit' : 'hsl(24,5%,60%)' }};">{{ $task->title }}</div>
                                @if($check)
                                    <div style="font-size:11px; color:hsl(140,45%,32%); font-weight:600; margin-top:2px;">
                                        {{ __('Done by :name', ['name' => $check->user?->name ?? __('a former team member')]) }}
                                        &middot; {{ $check->time() }}
                                    </div>
                                @else
                                    <div style="font-size:11px; color:hsl(24,5%,60%); margin-top:2px;">{{ __('Not done') }}</div>
                                @endif
                            </div>
                            @if($check && $check->photo_path)
                                <img src="{{ route('prep.task-check.photo', $check) }}"
                                     onclick="openLightbox('{{ route('prep.task-check.photo', $check) }}')"
                                     style="width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid hsl(30,15%,85%); cursor:pointer; flex-shrink:0;">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- Lightbox --}}
    <div id="lightbox" onclick="closeLightbox()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center;">
        <img id="lightbox-img" src="" style="max-width:90vw; max-height:90vh; border-radius:8px; object-fit:contain;">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
    </script>
</x-app-shell>
