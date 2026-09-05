{{-- Show/hide eye toggle, auto-applied to every password field on the page.
     Include once per page (before </body>). Idempotent and self-contained. --}}
<script>
(function () {
    var EYE = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

    function enhance(input) {
        if (input.dataset.eyeOn) return;
        input.dataset.eyeOn = '1';

        // Wrap the input so the button can be positioned over its right edge.
        var wrap = document.createElement('span');
        wrap.style.cssText = 'position:relative; display:block;';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.style.paddingRight = '40px';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.tabIndex = -1;
        btn.setAttribute('aria-label', 'Show password');
        btn.title = 'Show password';
        btn.style.cssText = 'position:absolute; top:50%; right:8px; transform:translateY(-50%); display:flex; align-items:center; justify-content:center; padding:4px; background:none; border:none; cursor:pointer; color:hsl(24,10%,55%);';
        btn.innerHTML = EYE;
        btn.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.innerHTML = reveal ? EYE_OFF : EYE;
            var label = reveal ? 'Hide password' : 'Show password';
            btn.setAttribute('aria-label', label);
            btn.title = label;
        });
        wrap.appendChild(btn);
    }

    function run() {
        var nodes = document.querySelectorAll('input[type="password"]');
        for (var i = 0; i < nodes.length; i++) enhance(nodes[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>
