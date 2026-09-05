<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inventory, Sales and Management System</title>
    {{-- app.js exists only to import app.css — that is how Tailwind gets built.
         Remove this and every page loses its styling. --}}
    @vite(['resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
</head>
<body class="antialiased" style="background:hsl(40,33%,98%);">

    {{-- Mobile top bar --}}
    <div id="app-topbar" style="display:none; position:fixed; top:0; left:0; right:0; height:56px; background:hsl(24,10%,12%); color:hsl(40,33%,98%); align-items:center; padding:0 16px; gap:12px; z-index:60; border-bottom:1px solid hsl(24,10%,16%);">
        <button onclick="toggleSidebar()" style="background:none; border:none; color:hsl(40,33%,98%); cursor:pointer; padding:4px; display:flex; align-items:center;">
            <svg id="icon-menu" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        </button>
        <a href="{{ route('dashboard') }}" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit;">
            <div style="width:26px; height:26px; border-radius:5px; background:hsl(20,60%,45%); display:flex; align-items:center; justify-content:center; color:white; font-family:'Caveat',cursive; font-weight:600; font-size:18px; line-height:1;">f</div>
            <span style="font-family:'Caveat',cursive; font-weight:600; font-size:22px; line-height:1;">ISMS</span>
        </a>
        @can('search-global')
        <button onclick="openSearch()" style="background:none; border:none; color:hsl(40,33%,70%); cursor:pointer; padding:4px; display:flex; align-items:center; margin-left:auto;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        @endcan
    </div>

    {{-- Sidebar overlay backdrop --}}
    <div id="app-overlay" onclick="toggleSidebar()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:45;"></div>

    <div class="flex h-screen">

        <aside id="app-sidebar" style="width:256px; background-color:hsl(24,10%,12%); color:hsl(40,33%,98%); border-right:1px solid hsl(24,10%,16%); display:flex; flex-direction:column; flex-shrink:0;">
            {{-- Logo links to the account's default landing page (route('dashboard')
                 self-forwards each role: juniors → prep, admins → support tickets). --}}
            <a href="{{ route('dashboard') }}" style="padding:24px; border-bottom:1px solid hsl(24,10%,16%); display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit;">
                <div style="width:32px; height:32px; border-radius:6px; background-color:hsl(20,60%,45%); display:flex; align-items:center; justify-content:center; color:white; font-family:'Caveat',cursive; font-weight:600; font-size:22px; line-height:1;">
                    f
                </div>
                <span style="font-family:'Caveat',cursive; font-weight:600; font-size:26px; line-height:1;">ISMS</span>
            </a>
            {{-- Search button --}}
            @can('search-global')
            <div style="padding:8px 12px; border-bottom:1px solid hsl(24,10%,16%);">
                <button onclick="openSearch()" style="display:flex; align-items:center; gap:8px; width:100%; padding:8px 10px; border-radius:6px; background:hsl(24,10%,8%); border:1px solid hsl(24,10%,20%); cursor:pointer; color:hsl(40,33%,55%); font-size:13px; font-family:'DM Sans',sans-serif;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Search…
                </button>
            </div>
            @endcan

            <nav style="flex:1; overflow-y:auto; padding:16px 12px;">
                @php
                    $nav = [
                        // Support queue — first because it is the Admin's whole
                        // job and they would otherwise scroll past twenty links
                        // to reach it. Gated, so no chef ever sees it. The Owner
                        // holds view-support-tickets too and had no link at all
                        // until now — the same drift that hid Inventory.
                        ['route' => 'support-tickets.index',  'label' => 'Support Tickets','gate' => 'view-support-tickets',  'icon' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
                        // Operations — daily use
                        ['route' => 'prep.index',             'label' => 'Prep Checklist'  , 'gate' => 'view-checklist',        'icon' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'],
                        ['route' => 'prep.overview',          'label' => 'Prep Overview',  'gate' => 'overview-checklist',     'icon' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><path d="M14 4h7"/><path d="M14 9h7"/><path d="M14 15h7"/><path d="M14 20h7"/>'],
                        ['route' => 'daily-report.index',     'label' => 'Daily Report',   'gate' => 'view-daily-report',      'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                        // Procurement
                        ['route' => 'purchases.index',        'label' => 'Purchases',      'gate' => 'view-purchases',         'icon' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'],
                        ['route' => 'market-purchases.index', 'label' => 'Market',         'gate' => 'view-market-purchases',  'icon' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'],
                        ['route' => 'invoice-scan.index',     'label' => 'Invoice Scan',   'gate' => 'use-invoice-scan',       'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15h6"/><path d="M12 12v6"/>'],
                        ['route' => 'production.index',       'label' => 'Production',     'gate' => 'view-production',        'icon' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/>'],
                        // Tracking
                        ['route' => 'inventory.index',        'label' => 'Inventory',      'gate' => 'view-inventory',         'icon' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>'],
                        ['route' => 'stock-take.index',       'label' => 'Stock-take',     'gate' => 'view-stock-take',        'icon' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>'],
                        ['route' => 'tally.index',            'label' => 'Tally Check',    'gate' => 'view-tally',             'icon' => '<path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>'],
                        ['route' => 'rnd.index',              'label' => 'R&D',            'gate' => 'view-rnd',               'icon' => '<path d="M10 2v7.31"/><path d="M14 9.3V1.99"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.52 16h12.96"/>'],
                        ['route' => 'wastage.index',          'label' => 'Wastage',        'gate' => 'view-wastage',           'icon' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>'],
                        ['route' => 'sales.index',            'label' => 'Sales',          'gate' => 'view-sales',             'icon' => '<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
                        // Financial overview
                        ['route' => 'dashboard',              'label' => 'Dashboard',      'gate' => 'view-dashboard',         'icon' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'],
                        ['route' => 'float.index',            'label' => 'Petty Cash',     'gate' => 'manage-float',           'icon' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>'],
                        ['route' => 'events.index',           'label' => 'Events',         'gate' => 'manage-events',          'icon' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>'],
                        // Reference & management
                        ['route' => 'recipes.index',          'label' => 'Recipes',        'gate' => 'view-recipes',           'icon' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
                        ['route' => 'suppliers.index',        'label' => 'Suppliers',      'gate' => 'view-suppliers',         'icon' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>'],
                        ['route' => 'sections.index',         'label' => 'Sections',       'gate' => 'manage-sections',        'icon' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'],
                        ['route' => 'users.index',            'label' => 'Users',          'gate' => 'view-users',              'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                        // Admin
                        ['route' => 'audits.index',           'label' => 'Audit Log',      'gate' => 'view-audit-log',         'icon' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                        ['route' => 'login-history.index',    'label' => 'Login History',  'gate' => 'view-audit-log',         'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
                    ];

                    // One list, gate-filtered, for every role including Admin.
                    // Two hand-written copies of this nav have now been deleted
                    // for the same reason. The Owner's had drifted nine links
                    // behind their gates (Inventory, Sales, Purchases, Market,
                    // Production, Wastage, Suppliers, Events, Login History);
                    // the Admin's was a deliberately narrow list that stopped
                    // being narrow the moment Admin was given every feature but
                    // the dashboard. A second copy of the nav cannot be kept in
                    // step by hand — reading the gates is what stops it.
                @endphp
                @foreach($nav as $item)
                    @if(isset($item['gate']) && !auth()->user()?->can($item['gate']))
                        @continue
                    @endif
                    @php
                        $exists = \Illuminate\Support\Facades\Route::has($item['route']);
                        $isActive = $exists && request()->routeIs($item['route'] . '*');
                        $bg = $isActive ? 'hsl(24,10%,16%)' : 'transparent';
                        $color = 'hsl(40,33%,' . ($isActive ? '98%' : '70%') . ')';
                    @endphp
                    <a href="{{ $exists ? route($item['route']) : '#' }}"
                       class="app-nav-item"
                       style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; margin-bottom:4px; background-color:{{ $bg }}; color:{{ $color }};">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">{!! $item['icon'] !!}</svg>
                        {{-- Deliberately NOT translated. The sidebar is the
                             team's shared vocabulary: the Owner and both Head
                             Chefs work in English, so "go to Stock-take" has to
                             mean the same thing on everyone's screen. Page
                             CONTENT is still translated — only the signposts
                             stay fixed. --}}
                        {{ $item['label'] }}
                    </a>
                @endforeach

                {{-- Leave + Feedback + Support — gate-driven, all roles --}}
                @php
                    $supportActive  = request()->routeIs('support.*');
                    $feedbackActive = request()->routeIs('feedback.*');
                    $leaveActive    = request()->routeIs('leave.*');
                @endphp
                {{-- Gate-driven, like $nav above. These three were hand-written
                     with no @can at all until 1.11.6, which is how a part timer
                     ended up looking at Leave and Feedback links that 403 —
                     both gates had denied the role since 1.11.5. --}}
                <div style="margin-top:8px; padding-top:8px; border-top:1px solid hsl(24,10%,16%);">
                    @can('view-leave')
                    <a href="{{ route('leave.index') }}"
                       class="app-nav-item"
                       style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; margin-bottom:4px; background-color:{{ $leaveActive ? 'hsl(24,10%,16%)' : 'transparent' }}; color:hsl(40,33%,{{ $leaveActive ? '98%' : '70%' }});">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
                        Leave
                    </a>
                    @endcan
                    @can('view-feedback')
                    <a href="{{ route('feedback.index') }}"
                       class="app-nav-item"
                       style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; margin-bottom:4px; background-color:{{ $feedbackActive ? 'hsl(24,10%,16%)' : 'transparent' }}; color:hsl(40,33%,{{ $feedbackActive ? '98%' : '70%' }});">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        Feedback
                    </a>
                    @endcan
                    @can('submit-support')
                    <a href="{{ route('support.index') }}"
                       class="app-nav-item"
                       style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; background-color:{{ $supportActive ? 'hsl(24,10%,16%)' : 'transparent' }}; color:hsl(40,33%,{{ $supportActive ? '98%' : '70%' }});">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                        Support
                    </a>
                    @endcan
                </div>
            </nav>

            {{-- User + Profile + Logout --}}
            <div style="border-top:1px solid hsl(24,10%,16%); padding:16px;">
                <div style="font-size:13px; font-weight:500; margin-bottom:2px;">{{ auth()->user()?->name }}</div>
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:hsl(40,33%,98%,0.5); margin-bottom:12px;">
                    {{ str_replace('_', ' ', auth()->user()?->role ?? '') }}
                </div>
                @can('toggle-maintenance')
                @php $maintenanceOn = \Illuminate\Support\Facades\Cache::get('maintenance_mode', false); @endphp
                <form method="POST" action="{{ route('maintenance.toggle') }}" style="margin-bottom:8px;">
                    @csrf
                    <button type="submit"
                            style="width:100%; padding:8px 12px; background:{{ $maintenanceOn ? 'hsl(20,60%,35%)' : 'hsl(24,10%,16%)' }}; color:{{ $maintenanceOn ? 'hsl(40,33%,98%)' : 'hsl(40,33%,70%)' }}; border:1px solid {{ $maintenanceOn ? 'hsl(20,60%,40%)' : 'hsl(24,10%,20%)' }}; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 8v4l3 3"/></svg>
                        {{ $maintenanceOn ? 'Maintenance: ON' : 'Maintenance: OFF' }}
                    </button>
                </form>
                @endcan
                @can('edit-profile')
                <a href="{{ route('profile.edit') }}"
                   style="display:block; text-align:center; padding:8px 12px; background:transparent; color:hsl(40,33%,98%,0.7); border:1px solid hsl(24,10%,20%); border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; margin-bottom:8px;">
                    Profile
                </a>
                @endcan
                <a href="{{ route('about.index') }}"
                   style="display:flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; background:transparent; color:hsl(40,33%,98%,{{ request()->routeIs('about.*') ? '1' : '0.55' }}); border:1px solid hsl(24,10%,20%); border-radius:6px; font-size:12px; font-weight:500; text-decoration:none; margin-bottom:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    About · v{{ \App\Support\ReleaseNotes::CURRENT_VERSION }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            style="width:100%; padding:8px 12px; background:hsl(24,10%,16%); color:hsl(40,33%,98%); border:1px solid hsl(24,10%,20%); border-radius:6px; font-size:13px; font-weight:500; cursor:pointer;">
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        <main class="app-main" style="flex:1; overflow-y:auto; background:hsl(40,33%,98%);">
            <div style="padding:40px 32px; max-width:1280px; margin:0 auto;">
                @auth
                    @php
                        $unread = auth()->user()->unreadNotifications->where('type', \App\Notifications\ChecklistReminder::class)->first();
                        $lowStockAlerts = auth()->user()->unreadNotifications->where('type', \App\Notifications\LowStockAlert::class);
                    @endphp
                    @if($unread)
                        <div style="margin-bottom:24px; padding:14px 18px; border-radius:6px; background:#fef9c3; color:#854d0e; font-size:14px; display:flex; justify-content:space-between; align-items:center;">
                            <span>{{ $unread->data['message'] }}</span>
                            <a href="{{ route('prep.overview') }}" style="font-weight:600; color:#854d0e; text-decoration:underline; margin-left:16px;">View Checklist</a>
                        </div>
                    @endif
                    @if($lowStockAlerts->isNotEmpty())
                        <div style="margin-bottom:24px; padding:14px 18px; border-radius:6px; background:#fee2e2; color:#991b1b; font-size:14px;">
                            <div style="font-weight:600; margin-bottom:6px;">
                                Low stock alert — {{ $lowStockAlerts->count() }} item{{ $lowStockAlerts->count() > 1 ? 's' : '' }} need reordering:
                            </div>
                            <ul style="margin:0; padding-left:18px;">
                                @foreach($lowStockAlerts as $alert)
                                    <li>{{ $alert->data['message'] }}</li>
                                @endforeach
                            </ul>
                            @can('dismiss-low-stock')
                                <form method="POST" action="{{ route('notifications.dismiss-low-stock') }}" style="margin-top:10px;">
                                    @csrf
                                    <button type="submit" style="background:none; border:none; cursor:pointer; color:#991b1b; font-size:13px; text-decoration:underline; padding:0;">Dismiss</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                @endauth
                {{ $slot }}
            </div>
        </main>

    </div>

    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('app-sidebar');
            var overlay = document.getElementById('app-overlay');
            var open = sidebar.classList.toggle('sidebar-open');
            overlay.style.display = open ? 'block' : 'none';
        }
    </script>

    {{-- Toast notification container --}}
    <div id="app-toast-container" style="position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none;"></div>

    <style>
        @keyframes app-toast-in {
            from { opacity:0; transform:translateX(24px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes app-toast-out {
            from { opacity:1; transform:translateX(0); }
            to   { opacity:0; transform:translateX(24px); }
        }
        .app-toast {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 18px; border-radius:8px; min-width:280px; max-width:380px;
            box-shadow:0 8px 24px rgba(0,0,0,0.14); pointer-events:all;
            animation: app-toast-in 0.25s ease forwards;
            font-family:'DM Sans',sans-serif; font-size:14px; line-height:1.4;
        }
        .app-toast.hiding { animation: app-toast-out 0.3s ease forwards; }
        .app-toast-close { background:none; border:none; cursor:pointer; padding:0; margin-left:auto; opacity:0.6; flex-shrink:0; line-height:1; font-size:18px; }
        .app-toast-close:hover { opacity:1; }
    </style>

    <script>
        function showToast(message, type) {
            var colors = {
                success: { bg:'#d1fae5', color:'#065f46', border:'#a7f3d0', icon:'<polyline points="20 6 9 17 4 12"/>' },
                error:   { bg:'#fee2e2', color:'#991b1b', border:'#fca5a5', icon:'<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>' },
                status:  { bg:'#fef9c3', color:'#854d0e', border:'#fde68a', icon:'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' },
            };
            var c = colors[type] || colors.status;
            var toast = document.createElement('div');
            toast.className = 'app-toast';
            toast.style.cssText = 'background:' + c.bg + '; color:' + c.color + '; border:1px solid ' + c.border + ';';
            toast.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">' + c.icon + '</svg>'
                + '<span style="flex:1;">' + message + '</span>'
                + '<button class="app-toast-close" onclick="dismissToast(this.parentNode)" style="color:' + c.color + ';">×</button>';
            document.getElementById('app-toast-container').appendChild(toast);
            var timer = setTimeout(function() { dismissToast(toast); }, 4000);
            toast._timer = timer;
        }
        function dismissToast(toast) {
            if (!toast || toast.classList.contains('hiding')) return;
            clearTimeout(toast._timer);
            toast.classList.add('hiding');
            setTimeout(function() { toast && toast.parentNode && toast.parentNode.removeChild(toast); }, 320);
        }
        @if(session('success')) showToast(@json(session('success')), 'success'); @endif
        @if(session('error'))   showToast(@json(session('error')),   'error');   @endif
        @if(session('status'))
            @php
                $statusMessages = [
                    'profile-updated'        => 'Profile updated.',
                    'password-updated'       => 'Password updated.',
                    'verification-link-sent' => 'A new verification link has been sent.',
                ];
            @endphp
            showToast(@json($statusMessages[session('status')] ?? session('status')), 'status');
        @endif
    </script>

    @can('search-global')
    <div id="search-modal" onclick="if(event.target===this)closeSearch()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:70; align-items:flex-start; justify-content:center; padding:80px 16px 16px;">
        <div style="background:white; border-radius:8px; width:100%; max-width:580px; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <form action="{{ route('search.index') }}" method="GET" style="display:flex; align-items:center; padding:14px 18px; gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(24,5%,55%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input id="search-input" type="text" name="q" placeholder="Search inventory, recipes, suppliers…"
                       style="flex:1; border:none; outline:none; font-size:15px; font-family:'DM Sans',sans-serif; background:transparent;">
                <button type="button" onclick="closeSearch()" style="background:none; border:none; cursor:pointer; font-size:22px; line-height:1; color:hsl(24,5%,45%); flex-shrink:0;">×</button>
            </form>
            <div style="border-top:1px solid hsl(30,15%,92%); padding:9px 18px; font-size:12px; color:hsl(24,5%,45%);">
                Press <kbd style="background:hsl(30,15%,94%); border:1px solid hsl(30,15%,85%); border-radius:3px; padding:1px 5px; font-size:11px; font-family:'JetBrains Mono',monospace;">Enter</kbd> to search &nbsp;·&nbsp; <kbd style="background:hsl(30,15%,94%); border:1px solid hsl(30,15%,85%); border-radius:3px; padding:1px 5px; font-size:11px; font-family:'JetBrains Mono',monospace;">Esc</kbd> to close
            </div>
        </div>
    </div>
    <script>
        function openSearch() {
            document.getElementById('search-modal').style.display = 'flex';
            setTimeout(function() { document.getElementById('search-input').focus(); }, 50);
        }
        function closeSearch() {
            document.getElementById('search-modal').style.display = 'none';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSearch();
        });
    </script>
    @endcan

    @include('partials.password-eye')
</body>
</html>