<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — Inventory, Sales and Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #faf7f2;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }

        .wordmark {
            font-family: 'Caveat', cursive;
            font-size: 2rem;
            font-weight: 600;
            color: #b87333;
            margin-bottom: 2rem;
            display: block;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 1.375rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            color: #1a1a1a;
        }

        p {
            font-size: 0.9375rem;
            color: #6b7280;
            line-height: 1.6;
        }

        .divider {
            border: none;
            border-top: 1px solid #f0ebe3;
            margin: 2rem 0;
        }

        .note {
            font-size: 0.8125rem;
            color: #9ca3af;
        }

        .note a,
        .note button {
            color: #b87333;
            text-decoration: none;
        }

        /* The sign-out control has to be a form (POST /logout), so the button
           is dressed as the link it reads as. */
        .note button {
            background: none;
            border: 0;
            padding: 0;
            font: inherit;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="wordmark">ISMS</span>
        <div class="icon">🔧</div>
        <h1>We'll be right back</h1>
        <p>Inventory, Sales and Management System is currently undergoing a quick update. We'll be back shortly — thank you for your patience.</p>
        <hr class="divider">
        {{-- Someone already signed in cannot use the sign-in link: /login is
             behind `guest`, so it bounces them to their home page, which is
             blocked, which lands them back here. It reads as a dead link. What
             they actually need is a way out of the session they are in. --}}
        @auth
            <p class="note">
                Signed in as {{ auth()->user()->name }} — that account cannot be used while
                maintenance is on.
            </p>
            <form method="POST" action="{{ route('logout') }}" class="note" style="margin-top:0.5rem;">
                @csrf
                <button type="submit">Sign out</button> to use an Owner or Admin account.
            </form>
        @else
            <p class="note">Owner or Admin? Please <a href="{{ route('login') }}">sign in</a> to manage this.</p>
        @endauth
    </div>
</body>
</html>
