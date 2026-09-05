<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Prep Overview</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">All sections for {{ now()->format('l, d M Y') }}</p>
        </div>
        {{-- A Head Chef has no Sections entry in the sidebar, so this is their way
             in to editing one. Same gate as the page itself enforces. --}}
        @can('manage-sections')
            <a href="{{ route('sections.index') }}"
               style="background-color:hsl(20,60%,45%); color:white; padding:9px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; white-space:nowrap;">
                Edit sections &amp; tasks
            </a>
        @endcan
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px;">
        @foreach($sections as $section)
            @php
                $total    = $section->tasks->count();
                $done     = $section->tasks->filter(fn($t) => $t->checks->isNotEmpty())->count();
                $percent  = $total > 0 ? round($done / $total * 100) : 0;

                $complete = $done === $total && $total > 0;
            @endphp
            <div style="background:white; border:1px solid hsl(30,15%,{{ $complete ? '75%' : '90%' }}); border-radius:8px; overflow:hidden;">
                {{-- Section header --}}
                <div style="padding:16px 20px; border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,{{ $complete ? '96%' : '98%' }});">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <span style="font-family:'DM Sans',sans-serif; font-size:15px; font-weight:500;">{{ $section->name }}</span>
                        <span style="font-size:12px; font-weight:600; padding:2px 8px; border-radius:4px;
                            background:{{ $complete ? '#dcfce7' : '#fef9c3' }};
                            color:{{ $complete ? '#166534' : '#854d0e' }};">
                            {{ $complete ? 'Complete' : $done . '/' . $total }}
                        </span>
                    </div>
                    {{-- Who worked this section today. Sections are not assigned to a
                         person any more, so this is the set of people who actually
                         ticked something, not a roster line. --}}
                    @php
                        $workers = $section->tasks->pluck('checks')->flatten()
                            ->map(fn ($c) => $c->user?->name)
                            ->filter()->unique()->values();
                    @endphp
                    <div style="font-size:12px; color:hsl(24,5%,50%);">
                        {{ $workers->isEmpty() ? 'Nobody yet today' : $workers->implode(', ') }}
                    </div>
                    {{-- Progress bar --}}
                    @php $overallPct = $total > 0 ? round($done / $total * 100) : 0; @endphp
                    <div style="background:hsl(30,15%,88%); border-radius:99px; height:6px; margin-top:10px; overflow:hidden;">
                        <div style="background:{{ $complete ? 'hsl(140,60%,40%)' : 'hsl(20,60%,45%)' }}; height:100%; width:{{ $overallPct }}%; border-radius:99px;"></div>
                    </div>
                </div>

                {{-- Prep task rows --}}
                <div>
                    <div style="padding:8px 20px 4px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:hsl(24,5%,55%);">Prep Tasks</div>
                    @foreach($section->tasks as $task)
                        @php
                            $taskCheck = $task->checks->first();
                            $checked   = (bool) $taskCheck;
                        @endphp
                        <div style="padding:10px 20px; border-bottom:1px solid hsl(30,15%,93%); display:flex; align-items:center; justify-content:space-between; gap:12px; opacity:{{ $checked ? '0.55' : '1' }};">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:16px; height:16px; border-radius:50%; border:2px solid {{ $checked ? 'hsl(140,60%,40%)' : 'hsl(30,15%,75%)' }}; background:{{ $checked ? 'hsl(140,60%,40%)' : 'white' }}; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                                    @if($checked)
                                        <svg width="8" height="8" viewBox="0 0 12 12" fill="none">
                                            <path d="M2 6l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <div style="font-size:13px; font-weight:500; {{ $checked ? 'text-decoration:line-through; color:hsl(24,5%,55%);' : '' }}">{{ $task->title }}</div>
                                    @if($taskCheck)
                                        <div style="font-size:11px; color:hsl(140,45%,32%); font-weight:600; margin-top:2px;">{{ $taskCheck->user?->name ?? '—' }}</div>
                                    @endif
                                </div>
                            </div>
                            @if($taskCheck && $taskCheck->photo_path)
                                <img src="{{ route('prep.task-check.photo', $taskCheck) }}"
                                     onclick="openLightbox('{{ route('prep.task-check.photo', $taskCheck) }}')"
                                     style="width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid hsl(30,15%,85%); cursor:pointer; flex-shrink:0;">
                            @endif
                        </div>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>

    {{-- Lightbox --}}
    <div id="lightbox" onclick="closeLightbox()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center;">
        <img id="lightbox-img" src="" style="max-width:90vw; max-height:90vh; border-radius:8px; object-fit:contain;">
    </div>

    <script>
        function openLightbox(src) {
            const lb = document.getElementById('lightbox');
            document.getElementById('lightbox-img').src = src;
            lb.style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
    </script>
</x-app-shell>
