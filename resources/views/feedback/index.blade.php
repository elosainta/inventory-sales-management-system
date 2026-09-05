<x-app-shell>

    <div class="app-page-header" style="margin-bottom:32px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Feedback') }}</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">
            @if($ownerData)
                {{ __('Team performance ratings. Only you can see who sent what.') }}
            @else
                {{ __('Rate your teammates on five questions. Your name is hidden from them — only the Owner can see who sent what.') }}
            @endif
        </p>
    </div>

    {{-- ═══════════ OWNER VIEW ═══════════ --}}
    @if($ownerData)
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
            <form method="GET" action="{{ route('feedback.index') }}" style="display:flex; gap:8px; align-items:center;">
                <input type="month" name="month" value="{{ $ownerData['month'] }}"
                       style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:white;">
                <button type="submit"
                        style="padding:8px 16px; background:hsl(24,10%,16%); color:white; border:none; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer;">
                    View
                </button>
            </form>
            @can('export-feedback-pdf')
            <form method="GET" action="{{ route('feedback.export-pdf') }}">
                <input type="hidden" name="month" value="{{ $ownerData['month'] }}">
                <button type="submit"
                        style="padding:8px 18px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">
                    {{ __('Export PDF') }}
                </button>
            </form>
            @endcan
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:16px;">
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">{{ __('Feedback sent') }}</div>
                <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">{{ $ownerData['entries']->count() }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">{{ $ownerData['monthLabel'] }}</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">{{ __('Kitchen average') }}</div>
                <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">
                    {{ $ownerData['kitchenAverage'] !== null ? $ownerData['kitchenAverage'] . ' ★' : '—' }}
                </div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">across all questions</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">{{ __('Weekly averages') }}</div>
                <div style="display:flex; gap:10px; align-items:baseline; flex-wrap:wrap;">
                    @forelse($ownerData['weekly'] as $week => $avg)
                        <div style="text-align:center;">
                            <div style="font-size:17px; font-weight:500; font-family:'JetBrains Mono',monospace;">{{ $avg }}</div>
                            <div style="font-size:10px; color:hsl(24,5%,50%);">Wk {{ $week }}</div>
                        </div>
                    @empty
                        <span style="font-size:14px; color:hsl(24,5%,50%);">{{ __('No data yet') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Question averages --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px; margin-bottom:16px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin-bottom:16px;">{{ __('Scores by question') }} — {{ $ownerData['monthLabel'] }}</h3>
            @foreach(\App\Models\FeedbackEntry::QUESTIONS as $key => $label)
                @php $avg = $ownerData['questionAverages'][$key]; @endphp
                <div style="display:flex; align-items:center; gap:14px; margin-bottom:10px;">
                    <div style="width:210px; font-size:13px; color:hsl(24,10%,25%); flex-shrink:0;">{{ $label }}</div>
                    <div style="flex:1; height:10px; background:hsl(30,15%,94%); border-radius:5px; overflow:hidden;">
                        <div style="height:100%; width:{{ $avg !== null ? ($avg / 5) * 100 : 0 }}%; background:hsl(20,60%,45%); border-radius:5px;"></div>
                    </div>
                    <div style="width:36px; text-align:right; font-family:'JetBrains Mono',monospace; font-size:13px;">{{ $avg ?? '—' }}</div>
                </div>
            @endforeach
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="app-chart-grid">
            {{-- Per person --}}
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
                <div style="padding:16px 20px 0;">
                    <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin-bottom:8px;">{{ __('By team member') }}</h3>
                </div>
                <table class="app-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:10px 20px; font-weight:600;">{{ __('Member') }}</th>
                        <th style="text-align:right; padding:10px 20px; font-weight:600;">{{ __('Reviews') }}</th>
                        <th style="text-align:right; padding:10px 20px; font-weight:600;">{{ __('Average') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($ownerData['perPerson'] as $person)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:10px 20px; font-weight:500;">{{ $person['name'] }}</td>
                            <td style="padding:10px 20px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $person['count'] }}</td>
                            <td style="padding:10px 20px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(20,60%,40%); font-weight:600;">{{ $person['average'] }} ★</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="padding:24px 20px; text-align:center; color:hsl(24,5%,50%);">{{ __('No feedback this month.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Who sent what --}}
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
                <div style="padding:16px 20px 0;">
                    <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin-bottom:8px;">{{ __('Who sent what') }}</h3>
                </div>
                <table class="app-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:10px 20px; font-weight:600;">{{ __('From → To') }}</th>
                        <th style="text-align:right; padding:10px 20px; font-weight:600;">{{ __('Avg') }}</th>
                        <th style="text-align:right; padding:10px 20px; font-weight:600;">{{ __('Sent') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($ownerData['entries'] as $entry)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:10px 20px;">
                                <span style="font-weight:600;">{{ $entry->fromUser?->name ?? '—' }}</span>
                                <span style="color:hsl(24,5%,55%);">→</span>
                                {{ $entry->toUser?->name ?? '—' }}
                                @if($entry->comment)
                                    <div style="font-size:12px; color:hsl(24,5%,50%); margin-top:2px;">“{{ Str::limit($entry->comment, 70) }}”</div>
                                @endif
                                @foreach($entry->attachments as $attachment)
                                    <a href="{{ route('feedback.attachment', $attachment) }}" style="font-size:11px; color:hsl(20,60%,40%); text-decoration:none;">📎 {{ Str::limit($attachment->filename, 24) }}</a>
                                @endforeach
                            </td>
                            <td style="padding:10px 20px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $entry->averageRating() }} ★</td>
                            <td style="padding:10px 20px; text-align:right; color:hsl(24,5%,50%); font-size:12px;">{{ $entry->created_at->format('d M') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="padding:24px 20px; text-align:center; color:hsl(24,5%,50%);">{{ __('No feedback this month.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ═══════════ STAFF VIEW ═══════════ --}}
    @else

        @can('submit-feedback')
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:28px; margin-bottom:32px; max-width:640px;">
            <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:4px;">{{ __('Give Feedback') }}</h2>
            <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:20px;">
                Your name is hidden from the recipient. The Owner can see who sent what.
            </p>

            <form method="POST" action="{{ route('feedback.store') }}" enctype="multipart/form-data" onsubmit="return submitFeedback()">
                @csrf

                <div style="margin-bottom:18px;">
                    <label for="to_user_id" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                        {{ __('About') }} <span style="color:hsl(0,70%,50%);">*</span>
                    </label>
                    <select id="to_user_id" name="to_user_id" required
                            style="width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:hsl(40,33%,99%);">
                        <option value="">— {{ __('Choose a teammate') }} —</option>
                        @foreach($teammates as $teammate)
                            <option value="{{ $teammate->id }}" @selected(old('to_user_id') == $teammate->id)>
                                {{ $teammate->name }} — {{ str_replace('_', ' ', ucwords($teammate->role, '_')) }}
                            </option>
                        @endforeach
                    </select>
                    @error('to_user_id')<p style="margin-top:5px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                </div>

                <div style="border:1px solid hsl(30,15%,92%); border-radius:6px; padding:16px; margin-bottom:18px; background:hsl(40,33%,99%);">
                    @foreach(\App\Models\FeedbackEntry::QUESTIONS as $key => $label)
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; {{ $loop->last ? '' : 'margin-bottom:12px;' }}">
                            <span style="font-size:14px; color:hsl(24,10%,22%);">{{ $label }} <span style="color:hsl(0,70%,50%);">*</span></span>
                            <span class="star-group" data-key="{{ $key }}" style="display:inline-flex; gap:2px; flex-shrink:0;">
                                @for($i = 1; $i <= 5; $i++)
                                    <label style="cursor:pointer; font-size:22px; color:hsl(30,15%,80%); line-height:1;" data-value="{{ $i }}">
                                        <input type="radio" name="rating_{{ $key }}" value="{{ $i }}" required
                                               @checked(old('rating_' . $key) == $i)
                                               style="position:absolute; opacity:0; width:0; height:0;">★</label>
                                @endfor
                            </span>
                        </div>
                        @error('rating_' . $key)<p style="margin:2px 0 8px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                    @endforeach
                </div>

                <div style="margin-bottom:18px;">
                    <label for="comment" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                        {{ __('Comment') }} <span style="color:hsl(24,5%,55%); font-weight:400;">(optional)</span>
                    </label>
                    <textarea id="comment" name="comment" rows="3" maxlength="2000"
                              placeholder="{{ __('Anything specific this week…') }}"
                              style="width:100%; padding:10px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; font-family:'DM Sans',sans-serif; resize:vertical; background:hsl(40,33%,99%); box-sizing:border-box;">{{ old('comment') }}</textarea>
                    @error('comment')<p style="margin-top:5px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                </div>

                <div style="margin-bottom:24px;">
                    <label style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:6px;">
                        {{ __('Attachments') }} <span style="color:hsl(24,5%,55%); font-weight:400;">(optional)</span>
                    </label>
                    <div style="position:relative;">
                        <input type="file" id="attachments" name="attachments[]" multiple
                               accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.pdf"
                               onchange="updateFileList(this)"
                               style="position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;">
                        <div id="file-label" style="display:flex; align-items:center; gap:10px; padding:10px 14px; border:1px dashed hsl(30,15%,80%); border-radius:6px; font-size:13px; color:hsl(24,5%,50%); background:hsl(40,33%,99%); pointer-events:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span>{{ __('Click to choose files…') }}</span>
                        </div>
                    </div>
                    <div id="file-list" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;"></div>
                    @error('attachments')<p style="margin-top:4px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                    @error('attachments.*')<p style="margin-top:4px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>@enderror
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button id="feedback-submit" type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:10px 24px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer; font-family:'DM Sans',sans-serif;">
                        {{ __('Send anonymously') }}
                    </button>
                </div>
            </form>
        </div>
        @endcan

        {{-- Received feedback --}}
        <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:12px;">
            <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500;">{{ __('Your Feedback') }}</h2>
            @if($monthAverage !== null)
                <span style="font-size:13px; color:hsl(24,5%,45%);">{{ now()->format('F') }} average: <b style="color:hsl(20,60%,40%);">{{ $monthAverage }} ★</b></span>
            @endif
        </div>

        @if($received->isEmpty())
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">
                {{ __('No feedback received yet.') }}
            </div>
        @else
            @foreach($received as $entry)
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px 24px; margin-bottom:12px; max-width:640px;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:10px;">
                        <span style="font-size:13px; font-style:italic; color:hsl(24,5%,45%);">{{ __('From a teammate') }}</span>
                        <span style="font-size:12px; color:hsl(24,5%,55%);">{{ $entry->created_at->format('d M Y') }}</span>
                    </div>
                    @foreach(\App\Models\FeedbackEntry::QUESTIONS as $key => $label)
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                            <span style="font-size:13px; color:hsl(24,10%,30%);">{{ $label }}</span>
                            <span style="font-size:14px; letter-spacing:2px; color:hsl(20,60%,45%); font-family:sans-serif;">
                                {!! str_repeat('★', $entry->rating($key)) !!}<span style="color:hsl(30,15%,85%);">{!! str_repeat('★', 5 - $entry->rating($key)) !!}</span>
                            </span>
                        </div>
                    @endforeach
                    @if($entry->comment)
                        <p style="font-size:13px; color:hsl(24,10%,25%); margin:10px 0 0; white-space:pre-line;">“{{ $entry->comment }}”</p>
                    @endif
                    @if($entry->attachments->isNotEmpty())
                        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:10px;">
                            @foreach($entry->attachments as $attachment)
                                <a href="{{ route('feedback.attachment', $attachment) }}"
                                   style="font-size:12px; font-weight:600; padding:3px 10px; border-radius:4px; background:hsl(30,15%,95%); color:hsl(24,10%,30%); border:1px solid hsl(30,15%,88%); text-decoration:none;">
                                    {{ Str::limit($attachment->filename, 30) }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
            {{ $received->links() }}
        @endif

        <script>
            // Star widgets: paint stars up to the chosen one.
            document.querySelectorAll('.star-group').forEach(function (group) {
                var labels = group.querySelectorAll('label');
                function paint(upTo) {
                    labels.forEach(function (l) {
                        l.style.color = (parseInt(l.dataset.value) <= upTo) ? 'hsl(20,60%,45%)' : 'hsl(30,15%,80%)';
                    });
                }
                labels.forEach(function (l) {
                    l.addEventListener('click', function () { paint(parseInt(l.dataset.value)); });
                });
                var checked = group.querySelector('input:checked');
                if (checked) paint(parseInt(checked.value));
            });

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
                    label.querySelector('span').textContent = 'Click to choose files…';
                    label.style.borderColor = 'hsl(30,15%,80%)';
                    label.style.borderStyle = 'dashed';
                }
            }

            function submitFeedback() {
                var btn = document.getElementById('feedback-submit');
                btn.disabled = true;
                btn.textContent = 'Sending…';
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
                return true;
            }
        </script>
    @endif
</x-app-shell>
