{{-- A picker shaped like the stock-take sheet's search: the box is already on
     screen, you type, you tap a result. One tap, not three.

     A native <select> on a phone hands the list to the operating system — it
     opens full-screen in the OS's own colours and no CSS can touch it. A
     click-to-open panel is no better on a long list: it still costs a tap
     before you can type. This is the shape the kitchen already uses to put an
     item on a stock-take sheet, so it is the shape they already know.

     A real <input type="hidden"> carries the value, so the form posts exactly
     what the <select> posted and every server rule still applies.

     Expects: $name, $options (value => label). Optional: $selected, $id,
     $placeholder, $required. --}}
@php
    $id           = $id ?? $name;
    $selected     = (string) ($selected ?? '');
    $placeholder  = $placeholder ?? __('Choose…');
    $required     = $required ?? false;
    $hasSelection = array_key_exists($selected, $options);
@endphp
{{-- Every option shows until you type. It was capped at 10 when this listed
     Bukku's 14 contacts; with the Suppliers page behind it (21 and growing)
     the cap hid half the kitchen's suppliers and read as "not updated". --}}
<div data-select data-select-max="{{ count($options) }}">
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $selected }}" @if($required) data-select-required @endif>

    {{-- What was picked. Shown instead of the search box, with a way back. --}}
    <div data-select-chosen @unless($hasSelection) hidden @endunless
         style="display:flex; align-items:center; justify-content:space-between; gap:10px;
                padding:10px 12px; border:1px solid hsl(20,40%,75%); border-radius:6px; background:hsl(30,25%,97%);">
        <span data-select-label style="font-size:14px; font-weight:500; color:hsl(20,60%,38%); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $hasSelection ? $options[$selected] : '' }}</span>
        <button type="button" data-select-clear title="{{ __('Change') }}" aria-label="{{ __('Change') }}"
                style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); font-size:18px; line-height:1; padding:2px 4px;">&times;</button>
    </div>

    <div data-select-search-wrap @if($hasSelection) hidden @endif>
        <input type="search" data-select-search placeholder="{{ $placeholder }}" autocomplete="off"
               style="width:100%; padding:11px 14px; border:1px solid hsl(30,15%,80%); border-radius:6px;
                      font-size:14px; background:white; box-sizing:border-box;">

        {{-- Results are rendered once, server-side, and filtered by hiding —
             the list is short and this keeps names out of innerHTML. --}}
        <div data-select-list style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
            @foreach($options as $value => $label)
                <button type="button" data-select-option data-value="{{ $value }}"
                        style="padding:7px 13px; border:1px solid hsl(20,40%,75%); background:white; color:hsl(20,60%,38%);
                               border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; text-align:left;">{{ $label }}</button>
            @endforeach
        </div>

        <p data-select-note hidden style="font-size:12px; color:hsl(24,5%,50%); margin:9px 0 0;">{{ __('Nothing matches.') }}</p>
    </div>
</div>

@once
    {{-- `hidden` is a UA stylesheet rule, and the inline display: on the chosen
         row outranks it — without this the empty chip sits above the search box
         before anything is picked. --}}
    <style>[data-select] [hidden] { display: none !important; }</style>
    <script>
        // One delegated listener for every picker on the page — they are all
        // built from this same markup, so a copy per instance would only be a
        // second thing to keep in step.
        (function () {
            const filter = (wrap) => {
                const term    = (wrap.querySelector('[data-select-search]').value || '').trim().toLowerCase();
                const max     = parseInt(wrap.dataset.selectMax || '10', 10);
                let shown     = 0;

                wrap.querySelectorAll('[data-select-option]').forEach((option) => {
                    const hit = option.textContent.toLowerCase().includes(term) && shown < max;
                    option.hidden = ! hit;
                    if (hit) shown++;
                });

                wrap.querySelector('[data-select-note]').hidden = shown > 0;
            };

            const choose = (wrap, option) => {
                const input = wrap.querySelector('input[type=hidden]');
                input.value = option.dataset.value;
                wrap.querySelector('[data-select-label]').textContent = option.textContent.trim();
                wrap.querySelector('[data-select-chosen]').hidden = false;
                wrap.querySelector('[data-select-search-wrap]').hidden = true;
                // Anything that listened to the old <select> keeps working.
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };

            document.addEventListener('click', (e) => {
                const wrap = e.target.closest('[data-select]');
                if (! wrap) return;

                const option = e.target.closest('[data-select-option]');
                if (option) choose(wrap, option);

                if (e.target.closest('[data-select-clear]')) {
                    wrap.querySelector('input[type=hidden]').value = '';
                    wrap.querySelector('[data-select-chosen]').hidden = true;
                    const searchWrap = wrap.querySelector('[data-select-search-wrap]');
                    searchWrap.hidden = false;
                    const search = wrap.querySelector('[data-select-search]');
                    search.value = '';
                    filter(wrap);
                    search.focus();
                }
            });

            document.addEventListener('input', (e) => {
                const search = e.target.closest('[data-select-search]');
                if (search) filter(search.closest('[data-select]'));
            });

            document.addEventListener('keydown', (e) => {
                const search = e.target.closest('[data-select-search]');
                if (! search) return;
                // Enter in a search box would submit the form around it.
                if (e.key === 'Enter') e.preventDefault();
                if (e.key === 'Escape') { search.value = ''; filter(search.closest('[data-select]')); }
            });

            // A hidden input cannot carry `required`, so the check the native
            // select did for free happens here. The server still validates —
            // this only saves a round trip.
            document.addEventListener('submit', (e) => {
                const empty = [...e.target.querySelectorAll('[data-select-required]')].find((i) => ! i.value);
                if (! empty) return;
                e.preventDefault();
                const search = empty.closest('[data-select]').querySelector('[data-select-search]');
                search.style.borderColor = 'hsl(0,70%,50%)';
                search.focus();
            });

            // Cap the list before anyone types, so a long one does not fill
            // the screen on first sight.
            document.querySelectorAll('[data-select]').forEach(filter);
        })();
    </script>
@endonce
