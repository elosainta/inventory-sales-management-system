<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inventory, Sales and Management System</title>
    @vite(['resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
</head>
<body class="antialiased" style="background:hsl(40,33%,96%);">
    @php($errorMessage = $errors->first('email') ?: session('error'))

    <div class="min-h-screen flex flex-col items-center justify-center">
        {{-- Logo --}}
        <div class="mb-8 flex items-center gap-3">
            <div style="width:44px; height:44px; border-radius:10px; background:hsl(20,60%,45%); display:flex; align-items:center; justify-content:center; color:white; font-family:'Caveat',cursive; font-weight:600; font-size:26px;">f</div>
            <span style="font-family:'Caveat',cursive; font-weight:600; font-size:40px; line-height:1; color:hsl(24,10%,12%);">ISMS</span>
        </div>

        {{-- Card --}}
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-md px-8 py-8">
            <h1 class="text-xl font-medium text-center mb-6" style="color:hsl(24,10%,12%);">
                {{ __('Sign in to your kitchen') }}
            </h1>

            @if($errorMessage)
                <div style="display:flex; align-items:flex-start; gap:10px; background:hsl(0,60%,97%); border:1px solid hsl(0,60%,85%); border-radius:8px; padding:11px 14px; margin-bottom:20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="hsl(0,65%,45%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         style="flex-shrink:0; margin-top:1px;">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <p style="font-size:13px; color:hsl(0,65%,40%); margin:0; line-height:1.5;">{{ $errorMessage }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium mb-1" style="color:hsl(24,10%,30%);">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" required autofocus value="{{ old('email') }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2"
                           style="border-color:hsl(24,10%,80%);">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium mb-1" style="color:hsl(24,10%,30%);">{{ __('Password') }}</label>
                    <input type="password" name="password" id="password" required
                           class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2"
                           style="border-color:{{ $errorMessage ? 'hsl(0,65%,65%)' : 'hsl(24,10%,80%)' }};">
                </div>

                <button type="submit" id="submit-btn"
                        class="w-full py-2 rounded-lg text-white text-sm font-medium transition"
                        style="background:hsl(20,60%,45%); cursor:pointer; border:none;">
                    {{ __('Sign in') }}
                </button>
            </form>
        </div>
    </div>

    @include('partials.password-eye')

    <script>
        // The lockout message from LoginRequest carries the remaining seconds.
        // Pull the number out and keep the button disabled until it runs down,
        // so nobody burns an attempt that the throttle would only reject anyway.
        //
        // Matched language-independently. This used to read /in (\d+) second/i,
        // which stopped working the moment auth.throttle was translated — the
        // Indonesian line ends "dalam 60 detik" and the countdown silently did
        // nothing for the one person reading it. The throttle message is the
        // only one on this page that contains a digit at all, so the first
        // number in it is the seconds; if that ever stops being true, pass the
        // value through explicitly rather than making this regex cleverer.
        (function () {
            var message = @json($errorMessage);
            var match = message && message.match(/(\d+)/);
            if (! match) return;

            var btn = document.getElementById('submit-btn');
            var left = parseInt(match[1], 10);

            function tick() {
                if (left <= 0) {
                    btn.disabled = false;
                    btn.textContent = @json(__('Sign in'));
                    btn.style.background = 'hsl(20,60%,45%)';
                    btn.style.cursor = 'pointer';
                    return;
                }
                btn.disabled = true;
                btn.textContent = @json(__('Try again in')) + ' ' + left + 's';
                btn.style.background = 'hsl(20,40%,60%)';
                btn.style.cursor = 'not-allowed';
                left--;
                setTimeout(tick, 1000);
            }

            tick();
        })();
    </script>
</body>
</html>
