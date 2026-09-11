<x-app-shell>

    <div class="app-page-header" style="margin-bottom:32px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('About the system') }}</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">
            What Inventory, Sales and Management System is, and everything that has changed since it was first built — in plain language.
        </p>
    </div>

    {{-- ═══════════ OVERVIEW ═══════════ --}}
    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:10px; padding:28px; margin-bottom:24px;">
        <div style="display:flex; align-items:baseline; gap:14px; flex-wrap:wrap; margin-bottom:14px;">
            <h2 style="font-family:'Caveat',cursive; font-size:34px; font-weight:600; line-height:1; color:hsl(20,60%,40%);">isms</h2>
            <span style="display:inline-block; background:hsl(20,60%,45%); color:white; font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:600; padding:4px 12px; border-radius:999px;">
                Version {{ $currentVersion }}
            </span>
        </div>
        <p style="color:hsl(24,10%,25%); font-size:15px; line-height:1.6; max-width:640px; margin-bottom:22px;">
            Inventory, Sales and Management System is the kitchen’s management system — it keeps track of money, stock, recipes, daily
            checklists and the team, so nothing slips through the cracks. This page lists every release, newest first,
            and says clearly what was added, improved or removed each time.
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:16px; border-top:1px solid hsl(30,15%,92%); padding-top:20px;">
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:6px;">{{ __('Current version') }}</div>
                <div style="font-size:22px; font-weight:500; font-family:'DM Sans',sans-serif;">{{ $currentVersion }}</div>
                <div style="font-size:11px; color:hsl(24,5%,55%); margin-top:4px;">counts up — 1.9 is followed by 1.10, not 2.0</div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:6px;">{{ __('First released') }}</div>
                <div style="font-size:22px; font-weight:500; font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($firstReleaseDate)->format('j M Y') }}</div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:6px;">{{ __('Total updates') }}</div>
                <div style="font-size:22px; font-weight:500; font-family:'JetBrains Mono',monospace;">{{ $totalCommits }}</div>
            </div>
        </div>

        <p style="margin-top:18px; font-size:12px; color:hsl(24,5%,55%);">
            These notes match the project’s full change history on GitHub — {{ $totalCommits }} recorded changes from the
            very first one (<code style="font-family:'JetBrains Mono',monospace;">{{ $firstCommit }}</code>,
            {{ \Carbon\Carbon::parse($firstReleaseDate)->format('j M Y') }}) to today.
        </p>
    </div>

    {{-- ═══════════ RELEASE TIMELINE ═══════════ --}}
    <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:16px;">{{ __('Release history') }}</h2>

    <div style="position:relative;">
        @foreach($releases as $release)
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:10px; padding:24px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:6px;">
                    <span style="display:inline-block; background:hsl(24,10%,16%); color:white; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; padding:3px 10px; border-radius:6px;">
                        v{{ $release['version'] }}
                    </span>
                    @if($release['version'] === $currentVersion)
                        <span style="display:inline-block; background:hsl(20,60%,45%); color:white; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; padding:3px 9px; border-radius:999px;">{{ __('Current') }}</span>
                    @endif
                    <span style="font-size:13px; color:hsl(24,5%,45%);">{{ \Carbon\Carbon::parse($release['date'])->format('j F Y') }}</span>
                </div>

                <p style="font-family:'DM Sans',sans-serif; font-size:16px; color:hsl(24,10%,20%); margin-bottom:16px;">
                    {{ $release['summary'] }}
                </p>

                @foreach(['added' => ['New', 'hsl(145,45%,38%)', 'hsl(145,45%,96%)'], 'improved' => ['Improved', 'hsl(20,60%,42%)', 'hsl(20,60%,96%)'], 'removed' => ['Removed', 'hsl(0,55%,45%)', 'hsl(0,55%,97%)']] as $key => $meta)
                    @if(!empty($release[$key]))
                        <div style="margin-bottom:12px;">
                            <div style="display:inline-block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:{{ $meta[1] }}; background:{{ $meta[2] }}; padding:2px 8px; border-radius:4px; margin-bottom:8px;">
                                {{ $meta[0] }}
                            </div>
                            <ul style="list-style:none; padding:0; margin:0;">
                                @foreach($release[$key] as $item)
                                    <li style="display:flex; gap:10px; align-items:flex-start; font-size:14px; line-height:1.55; color:hsl(24,10%,28%); margin-bottom:7px;">
                                        <span style="flex-shrink:0; width:6px; height:6px; border-radius:50%; background:{{ $meta[1] }}; margin-top:7px;"></span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                <div style="margin-top:14px; padding-top:12px; border-top:1px solid hsl(30,15%,94%); font-size:11px; color:hsl(24,5%,60%);">
                    Change history: <code style="font-family:'JetBrains Mono',monospace;">{{ $release['commits'] }}</code>
                </div>
            </div>
        @endforeach
    </div>

</x-app-shell>
