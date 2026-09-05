<x-app-shell>
    <div style="max-width:640px; margin:0 auto;">
        <div style="margin-bottom:32px;">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Support') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">{{ __('Describe the problem and the developer will look into it.') }}</p>
        </div>

        @if(session('success'))
            {{-- Success state — replaces the form --}}
            <div style="background:white; border:1px solid hsl(142,60%,80%); border-radius:8px; padding:40px 32px; text-align:center;">
                <div style="width:56px; height:56px; border-radius:50%; background:hsl(142,60%,93%); display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="hsl(142,60%,35%)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:500; color:hsl(24,10%,12%); margin-bottom:10px;">{{ __('Report sent') }}</h2>
                <p style="font-size:14px; color:hsl(24,5%,45%); margin-bottom:28px;">{{ __("Your report has been delivered to the developer's inbox. You'll hear back out-of-band.") }}</p>
                <a href="{{ route('support.index') }}"
                   style="display:inline-block; padding:9px 22px; background:hsl(20,60%,45%); color:white; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                    Send another report
                </a>
            </div>
        @else
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:32px;">
                <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" onsubmit="return confirmSubmit(event)">
                    @csrf

                    {{-- Description --}}
                    <div style="margin-bottom:24px;">
                        <label for="description" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:8px;">
                            {{ __('Problem Description') }} <span style="color:hsl(0,70%,50%);">*</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            required
                            placeholder="{{ __('Describe what happened, what you expected, and any steps to reproduce…') }}"
                            style="width:100%; padding:10px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; font-family:'DM Sans',sans-serif; resize:vertical; outline:none; background:hsl(40,33%,99%); color:hsl(24,10%,12%); box-sizing:border-box;"
                            onfocus="this.style.borderColor='hsl(20,60%,45%)'"
                            onblur="this.style.borderColor='hsl(30,15%,85%)'"
                            >{{ old('description') }}</textarea>
                        @error('description')
                            <p style="margin-top:6px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Media attachment --}}
                    <div style="margin-bottom:28px;">
                        <label for="media" style="display:block; font-size:13px; font-weight:500; color:hsl(24,10%,20%); margin-bottom:8px;">
                            {{ __('Attach Media') }} <span style="color:hsl(24,5%,55%); font-weight:400;">(optional)</span>
                        </label>
                        <div style="position:relative;">
                            <input
                                type="file"
                                id="media"
                                name="media"
                                accept=".jpg,.jpeg,.png,.webp,.mp4,.pdf"
                                onchange="updateFileLabel(this)"
                                style="position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;">
                            <div id="file-label" style="display:flex; align-items:center; gap:10px; padding:10px 14px; border:1px dashed hsl(30,15%,80%); border-radius:6px; font-size:13px; color:hsl(24,5%,50%); background:hsl(40,33%,99%); pointer-events:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span>Click to choose a file…</span>
                            </div>
                        </div>
                        <p style="margin-top:6px; font-size:12px; color:hsl(24,5%,55%);">
                            {{ __('Accepted: JPG, PNG, WEBP, MP4, PDF') }} &middot; {{ __('Max :size', ['size' => '128 MB']) }}
                        </p>
                        @error('media')
                            <p style="margin-top:4px; font-size:12px; color:hsl(0,70%,45%);">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Submit --}}
                    <div style="display:flex; justify-content:flex-end;">
                        <button
                            id="submit-btn"
                            type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:10px 24px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer; font-family:'DM Sans',sans-serif;">
                            {{ __('Send Report') }}
                        </button>
                    </div>
                </form>
            </div>

            <p style="margin-top:20px; font-size:12px; color:hsl(24,5%,55%); text-align:center;">
                Reports go directly to the developer's inbox. You'll hear back out-of-band.
            </p>
        @endif
    </div>

    <script>
        function updateFileLabel(input) {
            var label = document.getElementById('file-label');
            if (input.files && input.files.length > 0) {
                label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(20,60%,45%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
                var nameSpan = document.createElement('span');
                nameSpan.style.color = 'hsl(24,10%,20%)';
                nameSpan.textContent = input.files[0].name;
                label.appendChild(nameSpan);
                label.style.borderColor = 'hsl(20,60%,45%)';
                label.style.borderStyle = 'solid';
            } else {
                label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>'
                    + '<span>Click to choose a file…</span>';
                label.style.borderColor = 'hsl(30,15%,80%)';
                label.style.borderStyle = 'dashed';
            }
        }

        function confirmSubmit(e) {
            var mediaInput = document.getElementById('media');
            if (!mediaInput.files || mediaInput.files.length === 0) {
                if (!confirm('You haven\'t attached any media. Send the report without an attachment?')) {
                    return false;
                }
            }
            // Disable button to prevent double-submit
            var btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Sending…';
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
            return true;
        }
    </script>
</x-app-shell>
