<x-app-shell>
    @php
        // Progress is the whole kitchen's, across every section, because the
        // work is no longer split per person.
        $grandTotal = $sections->sum(fn ($s) => $s->tasks->count());
        $grandDone  = $taskChecks->count();
        $percent    = $grandTotal > 0 ? round($grandDone / $grandTotal * 100) : 0;
    @endphp

    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Prep Checklist') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">{{ now()->format('l, d M Y') }}</p>
        </div>
    </div>

    @if($sections->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            {{ __('No sections have been set up yet. Ask your Head Chef.') }}
        </div>
    @else
        {{-- Progress bar --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span style="font-size:14px; font-weight:500;">{{ __("Today's Progress") }}</span>
                <span style="font-size:14px; font-weight:600;">{{ $grandDone }} / {{ $grandTotal }}</span>
            </div>
            <div style="background:hsl(30,15%,92%); border-radius:99px; height:10px; overflow:hidden;">
                <div style="background:hsl(20,60%,45%); height:100%; width:{{ $percent }}%; border-radius:99px; transition:width 0.3s;"></div>
            </div>
        </div>

        <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:28px;">
            {{ __('Any task here can be done by anyone. Whoever ticks it or uploads the photo is recorded against it automatically.') }}
        </p>

        @foreach($sections as $section)
            <div style="margin-bottom:32px;">
                <div style="display:flex; align-items:baseline; gap:10px; margin-bottom:12px;">
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:19px; font-weight:500;">{{ $section->name }}</h2>
                    @if($section->description)
                        <span style="font-size:13px; color:hsl(24,5%,50%);">{{ $section->description }}</span>
                    @endif
                </div>

                {{-- Prep tasks --}}
                @if($section->tasks->isEmpty())
                    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:18px 20px; font-size:13px; color:hsl(24,5%,50%);">
                        {{ __('No tasks in this section yet.') }}
                    </div>
                @else
                    <p style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:hsl(24,5%,50%); margin-bottom:10px;">{{ __('Prep Tasks') }}</p>
                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
                        @foreach($section->tasks as $task)
                            @php $check = $taskChecks->get($task->id); @endphp
                            <div style="background:white; border:1px solid hsl(30,15%,{{ $check ? '80%' : '90%' }}); border-radius:8px; padding:18px 20px; opacity:{{ $check ? '0.75' : '1' }};">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:14px; min-width:0;">
                                        {{-- Tick circle --}}
                                        <div style="width:26px; height:26px; border-radius:50%; border:2px solid {{ $check ? 'hsl(140,60%,40%)' : 'hsl(30,15%,75%)' }}; background:{{ $check ? 'hsl(140,60%,40%)' : 'white' }}; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                                            @if($check)
                                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                                    <path d="M2 6l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div style="min-width:0;">
                                            <div style="font-size:15px; font-weight:600; {{ $check ? 'text-decoration:line-through; color:hsl(24,5%,55%);' : '' }}">{{ $task->title }}</div>
                                            @if($task->description)
                                                <div style="font-size:13px; color:hsl(24,5%,50%); margin-top:3px;">{{ $task->description }}</div>
                                            @endif
                                            {{-- Who did it. The whole point of the change: the name is
                                                 taken off the account that ticked, never typed in. --}}
                                            @if($check)
                                                <div style="font-size:12px; color:hsl(140,45%,32%); font-weight:600; margin-top:4px;">
                                                    {{ __('Done by :name', ['name' => $check->user?->name ?? __('a former team member')]) }}
                                                    · {{ $check->updated_at->format('H:i') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                        @if($check && $check->photo_path)
                                            <img src="{{ route('prep.task-check.photo', $check) }}"
                                                 onclick="openLightbox('{{ route('prep.task-check.photo', $check) }}')"
                                                 style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid hsl(30,15%,85%); cursor:pointer;">
                                        @endif
                                        @if($task->requires_photo)
                                            <form method="POST" action="{{ route('prep.task-check') }}" enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="task_id" value="{{ $task->id }}">
                                                <label style="cursor:pointer; padding:6px 14px; background:{{ $check ? 'hsl(30,15%,94%)' : 'hsl(20,60%,45%)' }}; color:{{ $check ? 'hsl(24,5%,40%)' : 'white' }}; border-radius:6px; font-size:13px; font-weight:600; white-space:nowrap;">
                                                    {{ $check ? __('Re-upload') : __('Upload Photo') }}
                                                    <input type="file" name="photo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                                                </label>
                                            </form>
                                        @elseif(!$check)
                                            <form method="POST" action="{{ route('prep.task-check') }}">
                                                @csrf
                                                <input type="hidden" name="task_id" value="{{ $task->id }}">
                                                <button type="submit" style="padding:6px 14px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;">
                                                    {{ __('Mark Done') }}
                                                </button>
                                            </form>
                                        @else
                                            <span style="font-size:12px; color:hsl(140,60%,35%); font-weight:600;">✓ {{ __('Done') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        @endforeach
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
