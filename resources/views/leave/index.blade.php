<x-app-shell>
    @php
        $statusStyles = [
            'pending'  => ['bg' => '#FEF3C7', 'text' => '#92400E', 'label' => 'Pending'],
            'approved' => ['bg' => '#D1FAE5', 'text' => '#065F46', 'label' => 'Approved'],
            'rejected' => ['bg' => '#FEE2E2', 'text' => '#991B1B', 'label' => 'Rejected'],
        ];
        $roleColors = [
            'owner'       => ['bg' => '#fef9c3', 'text' => '#854d0e'],
            'head_chef'   => ['bg' => '#dbeafe', 'text' => '#1e40af'],
            'junior_chef' => ['bg' => '#f3e8ff', 'text' => '#6b21a8'],
            'viewer'      => ['bg' => 'hsl(30,15%,92%)', 'text' => 'hsl(24,10%,30%)'],
        ];
    @endphp

    <div class="app-page-header" style="margin-bottom:32px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Leave') }}</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">
            @can('decide-leave')
                Review leave applications. Decisions are final and logged.
            @else
                Apply for leave with supporting documents. The Owner reviews every application.
            @endcan
        </p>
    </div>

    {{-- ── Apply form (staff only) ─────────────────────────── --}}
    @can('submit-leave')
    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:28px; margin-bottom:32px; max-width:640px;">
        <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:4px;">{{ __('Apply for Leave') }}</h2>
        <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:20px;">{{ __('Supporting documents are required before you can submit.') }}</p>

        <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data" onsubmit="return submitLeave()">
            @csrf

            <div style="display:flex; gap:12px; margin-bottom:18px;">
                <div style="flex:1;">
                    <label for="start_date" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                        {{ __('From') }} <span style="color:hsl(0,70%,50%);">*</span>
                    </label>
                    <input type="date" id="start_date" name="start_date" required value="{{ old('start_date') }}"
                           onchange="syncEndDate()"
                           style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:hsl(40,33%,99%); box-sizing:border-box;">
                    @error('start_date')<p style="margin-top:5px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                </div>
                <div style="flex:1;">
                    <label for="end_date" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                        {{ __('To') }} <span style="color:hsl(0,70%,50%);">*</span>
                    </label>
                    <input type="date" id="end_date" name="end_date" required value="{{ old('end_date') }}"
                           style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:hsl(40,33%,99%); box-sizing:border-box;">
                    @error('end_date')<p style="margin-top:5px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label for="reason" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                    {{ __('Reason') }} <span style="color:hsl(0,70%,50%);">*</span>
                </label>
                <textarea id="reason" name="reason" rows="3" required maxlength="2000"
                          placeholder="{{ __('State your reason for leave…') }}"
                          style="width:100%; padding:10px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; font-family:'DM Sans',sans-serif; resize:vertical; background:hsl(40,33%,99%); box-sizing:border-box;"
                          onfocus="this.style.borderColor='hsl(20,60%,45%)'"
                          onblur="this.style.borderColor='hsl(30,15%,85%)'">{{ old('reason') }}</textarea>
                @error('reason')<p style="margin-top:5px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                    {{ __('Supporting documents') }} <span style="color:hsl(0,70%,50%);">*</span>
                </label>
                <div style="position:relative;">
                    <input type="file" id="attachments" name="attachments[]" multiple required
                           accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.pdf"
                           onchange="updateFileList(this)"
                           style="position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;">
                    <div id="file-label" style="display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px dashed hsl(30,15%,80%); border-radius:6px; font-size:13px; color:hsl(24,5%,50%); background:hsl(40,33%,99%); pointer-events:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Click to choose files — you can pick several…</span>
                    </div>
                </div>
                <div id="file-list" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;"></div>
                <p style="margin-top:6px; font-size:12px; color:hsl(24,5%,55%);">
                    Images, videos (MP4/MOV) or PDF &middot; up to 2 GB per file
                </p>
                @error('attachments')<p style="margin-top:4px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                @error('attachments.*')<p style="margin-top:4px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button id="leave-submit" type="submit"
                        style="background-color:hsl(20,60%,45%); color:white; padding:10px 24px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer; font-family:'DM Sans',sans-serif;">
                    {{ __('Submit application') }}
                </button>
            </div>
        </form>
    </div>
    @endcan

    {{-- ── Applications list ───────────────────────────────── --}}
    <div style="margin-bottom:12px;">
        <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500;">
            @can('decide-leave') {{ __('Applications') }} @else {{ __('My Applications') }} @endcan
        </h2>
    </div>

    @if($applications->isEmpty())
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">
            {{ __('No leave applications yet.') }}
        </div>
    @else
        @foreach($applications as $application)
            @php
                $s = $statusStyles[$application->status] ?? $statusStyles['pending'];
                $rc = $roleColors[$application->user->role ?? 'viewer'] ?? $roleColors['viewer'];
                $days = $application->start_date->diffInDays($application->end_date) + 1;
            @endphp
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px 24px; margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap;">
                            @can('decide-leave')
                                <span style="font-weight:600;">{{ $application->user->name }}</span>
                                <span style="font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; background:{{ $rc['bg'] }}; color:{{ $rc['text'] }}; text-transform:capitalize;">
                                    {{ str_replace('_', ' ', $application->user->role) }}
                                </span>
                            @endcan
                            <span style="font-size:12px; font-weight:700; padding:3px 10px; border-radius:10px; background:{{ $s['bg'] }}; color:{{ $s['text'] }};">
                                {{ $s['label'] }}
                            </span>
                        </div>
                        <div style="font-size:14px; margin-bottom:4px;">
                            <span style="font-family:'JetBrains Mono',monospace; font-size:13px;">
                                {{ $application->start_date->format('d M Y') }}
                                @if(! $application->start_date->isSameDay($application->end_date))
                                    → {{ $application->end_date->format('d M Y') }}
                                @endif
                            </span>
                            <span style="color:hsl(24,5%,55%); font-size:12px;">&middot; {{ $days }} {{ __(Str::plural('day', $days)) }}</span>
                        </div>
                        <p style="font-size:13px; color:hsl(24,10%,25%); margin:0 0 8px; white-space:pre-line;">{{ $application->reason }}</p>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            @foreach($application->attachments as $attachment)
                                <a href="{{ route('leave.attachment', $attachment) }}"
                                   style="display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:4px; background:hsl(30,15%,95%); color:hsl(24,10%,30%); border:1px solid hsl(30,15%,88%); text-decoration:none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                    {{ Str::limit($attachment->filename, 34) }}
                                    <span style="color:hsl(24,5%,55%); font-weight:400;">{{ number_format($attachment->size / 1048576, 1) }} MB</span>
                                </a>
                            @endforeach
                        </div>
                        @if($application->decided_at)
                            <p style="font-size:12px; color:hsl(24,5%,55%); margin:8px 0 0;">
                                {{ $s['label'] }} by {{ $application->decider?->name ?? '—' }} · {{ $application->decided_at->format('d M Y H:i') }}
                            </p>
                        @endif
                    </div>

                    @can('decide-leave')
                        @if($application->isPending())
                            <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0; justify-content:center;">
                                <form method="POST" action="{{ route('leave.approve', $application) }}"
                                      onsubmit="return confirm('Approve {{ addslashes($application->user->name) }}\'s leave?')">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            style="width:100%; background:hsl(142,60%,32%); color:white; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; border:none; cursor:pointer;">
                                        {{ __('Approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('leave.reject', $application) }}"
                                      onsubmit="return confirm('Reject {{ addslashes($application->user->name) }}\'s leave?')">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            style="width:100%; background:white; color:hsl(0,70%,42%); padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; border:1px solid hsl(0,70%,75%); cursor:pointer;">
                                        {{ __('Reject') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>
        @endforeach

        {{ $applications->links() }}
    @endif

    <script>
        function syncEndDate() {
            var start = document.getElementById('start_date');
            var end = document.getElementById('end_date');
            if (start.value && (!end.value || end.value < start.value)) {
                end.value = start.value;
            }
            end.min = start.value;
        }

        function updateFileList(input) {
            var list = document.getElementById('file-list');
            var label = document.getElementById('file-label');
            list.innerHTML = '';
            if (input.files && input.files.length > 0) {
                label.querySelector('span').textContent = input.files.length + ' file(s) selected — click to change';
                label.style.borderColor = 'hsl(20,60%,45%)';
                label.style.borderStyle = 'solid';
                for (var i = 0; i < input.files.length; i++) {
                    var f = input.files[i];
                    var chip = document.createElement('span');
                    chip.style.cssText = 'font-size:12px; padding:3px 10px; border-radius:4px; background:hsl(30,15%,95%); border:1px solid hsl(30,15%,88%); color:hsl(24,10%,30%);';
                    chip.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)';
                    list.appendChild(chip);
                }
            } else {
                label.querySelector('span').textContent = 'Click to choose files — you can pick several…';
                label.style.borderColor = 'hsl(30,15%,80%)';
                label.style.borderStyle = 'dashed';
            }
        }

        function submitLeave() {
            var input = document.getElementById('attachments');
            if (!input.files || input.files.length === 0) {
                alert('Please attach at least one supporting document.');
                return false;
            }
            var btn = document.getElementById('leave-submit');
            btn.disabled = true;
            btn.textContent = 'Uploading…';
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
            return true;
        }
    </script>
</x-app-shell>
