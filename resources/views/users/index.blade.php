<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Users</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">
                @can('manage-users')
                    Manage team accounts, roles, and passwords.
                @else
                    Reset team member passwords.
                @endcan
            </p>
        </div>
        @can('manage-users')
        <button onclick="document.getElementById('invite-modal').style.display='flex'"
                style="background:hsl(20,60%,45%); color:white; padding:10px 18px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            + Add User
        </button>
        @endcan
    </div>

    {{-- Active users table --}}
    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:32px;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">User</th>
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">Role</th>
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">Last Login</th>
                    <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    @php
                        $roleColors = [
                            'owner'       => ['bg' => '#fef9c3', 'text' => '#854d0e'],
                            'head_chef'   => ['bg' => '#dbeafe', 'text' => '#1e40af'],
                            'junior_chef' => ['bg' => '#f3e8ff', 'text' => '#6b21a8'],
                            'part_timer'  => ['bg' => '#dcfce7', 'text' => '#166534'],
                        ];
                        // Admin has no colour of its own and falls through here.
                        $c = $roleColors[$user->role] ?? ['bg' => 'hsl(30,15%,92%)', 'text' => 'hsl(24,10%,30%)'];
                    @endphp
                    <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                        <td style="padding:14px 16px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:36px; height:36px; border-radius:50%; background:hsl(20,60%,45%); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:500;">{{ $user->name }}</div>
                                    <div style="font-size:12px; color:hsl(24,5%,50%);">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:14px 16px;">
                            <span style="font-size:12px; font-weight:600; padding:3px 10px; border-radius:4px; background:{{ $c['bg'] }}; color:{{ $c['text'] }}; text-transform:capitalize;">
                                {{ str_replace('_', ' ', $user->role) }}
                            </span>
                            @if($user->is_demo)
                                <span title="Hidden demo/testing account — writes are blocked and sales are hidden."
                                      style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; background:hsl(20,60%,45%); color:white; letter-spacing:0.03em; margin-left:6px;">
                                    DEMO
                                </span>
                            @endif
                        </td>
                        <td style="padding:14px 16px; color:hsl(24,5%,50%); font-size:13px;">
                            {{ $user->last_login_at ? $user->last_login_at->format('M d, Y H:i') : 'Never' }}
                        </td>
                        <td style="padding:14px 16px;">
                            @php
                                $canManage   = auth()->user()->can('manage-users');
                                $canChangePw = auth()->user()->can('manage-user-passwords')
                                    && (auth()->user()->isOwner() || ! in_array($user->role, ['owner', 'admin'], true));
                                // Same escalation limit as passwords, and nobody deletes themselves here.
                                $canDelete   = auth()->user()->can('delete-users')
                                    && (auth()->user()->isOwner() || ! in_array($user->role, ['owner', 'admin'], true))
                                    && $user->id !== auth()->id();
                                $canSetLang  = auth()->user()->can('manage-user-language')
                                    && (auth()->user()->isOwner() || ! in_array($user->role, ['owner', 'admin'], true));
                                // "More" is a container, so it appears when any of its items does.
                                $hasMore     = $canDelete || $canSetLang;
                            @endphp
                            <div style="display:flex; gap:8px; align-items:center;">
                                @if($canManage)
                                    <button onclick="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->role }}')"
                                            style="font-size:13px; color:hsl(20,60%,45%); background:none; border:none; cursor:pointer; font-weight:500;">
                                        Edit
                                    </button>
                                @endif
                                @if($canChangePw)
                                    @if($canManage)<span style="color:hsl(30,15%,80%);">|</span>@endif
                                    <button onclick="openPassword({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                            style="font-size:13px; color:hsl(24,10%,30%); background:none; border:none; cursor:pointer; font-weight:500;">
                                        Password
                                    </button>
                                @endif
                                @if($hasMore)
                                    @if($canManage || $canChangePw)<span style="color:hsl(30,15%,80%);">|</span>@endif
                                    <button type="button"
                                            onclick="openMore(event, this, {{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->preferred_language === 'id' ? "'id'" : "''" }}, {{ $canSetLang ? 'true' : 'false' }}, {{ $canDelete ? 'true' : 'false' }})"
                                            style="font-size:13px; color:hsl(24,10%,30%); background:none; border:none; cursor:pointer; font-weight:500;">
                                        More
                                    </button>
                                @endif
                                @if(! $canManage && ! $canChangePw && ! $hasMore)
                                    <span style="color:hsl(30,15%,75%);">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @can('manage-users')

    {{-- Invite Modal --}}
    <div id="invite-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:400px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Add Team Member</h2>
                <button onclick="document.getElementById('invite-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('invitations.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Sam Chen"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Email Address</label>
                    <input type="email" name="email" required placeholder="chef@example.com"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Role</label>
                    <select name="role"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="head_chef">Head Chef</option>
                        <option value="junior_chef">Junior Chef</option>
                        <option value="part_timer">Part Timer</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <p style="font-size:12px; color:hsl(24,5%,50%); margin-bottom:16px;">
                    The account will be created immediately and a welcome email with login credentials will be sent.
                </p>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="document.getElementById('invite-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="edit-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:440px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Edit User</h2>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form id="edit-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                    <input type="text" name="name" id="edit-name" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Role</label>
                    <select name="role" id="edit-role"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="owner">Owner</option>
                        <option value="head_chef">Head Chef</option>
                        <option value="junior_chef">Junior Chef</option>
                        <option value="part_timer">Part Timer</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="document.getElementById('edit-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    @endcan {{-- end manage-users (invite + edit modals) --}}

    @if(auth()->user()->can('delete-users') || auth()->user()->can('manage-user-language'))
    {{-- Row "More" menu. One shared, fixed-position element rather than one per
         row: the table wrapper is overflow:hidden, which would clip an absolutely
         positioned menu on the lower rows. Because it is shared, which items
         apply cannot be baked into the markup: an Admin may set a chef's
         language but not an Owner's, so openMore() shows and hides per row. --}}
    <div id="more-menu"
         style="display:none; position:fixed; z-index:60; background:white; border:1px solid hsl(30,15%,88%); border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.12); padding:4px; min-width:180px;">
        @can('manage-user-language')
        <button type="button" id="more-language" onclick="openLanguage()"
                style="display:block; width:100%; text-align:left; padding:8px 12px; font-size:13px; font-weight:500; color:hsl(24,10%,30%); background:none; border:none; border-radius:4px; cursor:pointer;">
            Display Language
        </button>
        @endcan
        @can('delete-users')
        <button type="button" id="more-delete" onclick="openDelete()"
                style="display:block; width:100%; text-align:left; padding:8px 12px; font-size:13px; font-weight:500; color:hsl(0,70%,45%); background:none; border:none; border-radius:4px; cursor:pointer;">
            Delete Account
        </button>
        @endcan
    </div>
    @endif

    @can('manage-user-language')
    {{-- Switching someone else's language is reversible and low-stakes, so it is
         one step — unlike deletion below. The radios mirror the Profile page so
         the two places do not look like different features. --}}
    <div id="language-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:70; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:420px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Display Language</h2>
                <button type="button" onclick="closeLanguage()"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <p id="language-label" style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:4px;"></p>
            <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:16px;">
                Applies to the prep checklist page. Reports and PDFs stay in English.
            </p>

            <form id="language-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="display:flex; gap:12px; margin-bottom:20px;">
                    <label id="lang-opt-en" style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <input type="radio" name="preferred_language" value="" onchange="updateUserLangStyle()">
                        English
                    </label>
                    <label id="lang-opt-id" style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <input type="radio" name="preferred_language" value="id" onchange="updateUserLangStyle()">
                        Bahasa Indonesia
                    </label>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeLanguage()"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    @can('delete-users')
    {{-- Deletion is irreversible and there is no undo, so it is deliberately two
         steps: the first asks, the second makes you commit. --}}
    <div id="delete-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:70; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:400px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Delete Account</h2>
                <button type="button" onclick="closeDelete()"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <div id="delete-step-1">
                <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:12px; line-height:1.6;">
                    Once the account is deleted, all of its resources and data will be
                    permanently deleted. Before deleting the account, please download any
                    data or information that you wish to retain.
                </p>
                <p style="font-size:14px; margin-bottom:8px;">Are you sure to delete this account?</p>
                <p id="delete-name" style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:20px;"></p>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeDelete()"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="button" onclick="deleteStep(2)"
                            style="background-color:hsl(0,70%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Yes, delete
                    </button>
                </div>
            </div>

            <div id="delete-step-2" style="display:none;">
                <p style="font-size:14px; margin-bottom:8px;">Confirm?</p>
                <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:20px;">
                    This permanently removes <strong id="delete-name-2"></strong>. This cannot be undone.
                </p>
                <form id="delete-form" method="POST">
                    @csrf
                    @method('DELETE')
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" onclick="deleteStep(1)"
                                style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Go back</button>
                        <button type="submit"
                                style="background-color:hsl(0,70%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Password Modal — available to Owner and Admin --}}
    <div id="password-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:400px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Change Password</h2>
                <button onclick="document.getElementById('password-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <p id="password-label" style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:16px;"></p>
            <form id="password-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">New Password</label>
                    <input type="password" name="password" required minlength="8"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Confirm Password</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="document.getElementById('password-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEdit(id, name, role) {
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-form').action = '/users/' + id;
            document.getElementById('edit-modal').style.display = 'flex';
        }

        function openPassword(id, name) {
            document.getElementById('password-label').textContent = 'Setting new password for ' + name + '.';
            document.getElementById('password-form').action = '/users/' + id + '/password';
            document.getElementById('password-modal').style.display = 'flex';
        }

        // Which row's "More" menu is currently open. The menu itself is shared,
        // so the row identity has to live here rather than in the markup.
        let moreUserId = null;
        let moreUserName = '';
        let moreUserLang = '';

        function closeMore() {
            const menu = document.getElementById('more-menu');
            if (menu) menu.style.display = 'none';
        }

        function openMore(event, btn, id, name, lang, canSetLang, canDelete) {
            event.stopPropagation();
            const menu = document.getElementById('more-menu');
            const alreadyOpen = menu.style.display === 'block' && moreUserId === id;

            closeMore();
            if (alreadyOpen) return;

            moreUserId = id;
            moreUserName = name;
            moreUserLang = lang;

            // Per-row: the menu is shared, so which items apply is decided here
            // rather than in the markup. Guarded — an Admin has no delete item
            // rendered at all when the gate denied it.
            const langItem = document.getElementById('more-language');
            if (langItem) langItem.style.display = canSetLang ? 'block' : 'none';
            const deleteItem = document.getElementById('more-delete');
            if (deleteItem) deleteItem.style.display = canDelete ? 'block' : 'none';

            // Display first, then measure — offsetWidth is 0 while hidden.
            menu.style.display = 'block';
            const r = btn.getBoundingClientRect();

            // Drop below the button, but flip above it when there is not room —
            // otherwise tapping a row near the bottom of a phone opens the menu
            // off-screen, and the menu is position:fixed so the page cannot be
            // scrolled to reach it. Clamped to 8px as a last resort for a menu
            // taller than the viewport itself.
            const below = r.bottom + 4;
            const above = r.top - menu.offsetHeight - 4;
            const fitsBelow = below + menu.offsetHeight <= window.innerHeight - 8;

            menu.style.top  = Math.max(8, fitsBelow ? below : above) + 'px';
            menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        }

        // The menu is fixed-position, so it would drift away from its button.
        document.addEventListener('click', closeMore);
        window.addEventListener('resize', closeMore);
        window.addEventListener('scroll', closeMore, true);

        function updateUserLangStyle() {
            const base = 'flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;';
            const active = 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);';
            const chosen = document.querySelector('#language-form input[value="id"]').checked;
            document.getElementById('lang-opt-en').style.cssText = base + (chosen ? '' : active);
            document.getElementById('lang-opt-id').style.cssText = base + (chosen ? active : '');
        }

        function openLanguage() {
            closeMore();
            document.getElementById('language-form').action = '/users/' + moreUserId + '/language';
            document.getElementById('language-label').textContent = 'Setting the display language for ' + moreUserName + '.';
            document.querySelector('#language-form input[value="id"]').checked = moreUserLang === 'id';
            document.querySelector('#language-form input[value=""]').checked = moreUserLang !== 'id';
            updateUserLangStyle();
            document.getElementById('language-modal').style.display = 'flex';
        }

        function closeLanguage() {
            document.getElementById('language-modal').style.display = 'none';
        }

        function deleteStep(n) {
            document.getElementById('delete-step-1').style.display = n === 1 ? 'block' : 'none';
            document.getElementById('delete-step-2').style.display = n === 2 ? 'block' : 'none';
        }

        function openDelete() {
            closeMore();
            document.getElementById('delete-form').action = '/users/' + moreUserId;
            document.getElementById('delete-name').textContent = moreUserName;
            document.getElementById('delete-name-2').textContent = moreUserName;
            deleteStep(1);
            document.getElementById('delete-modal').style.display = 'flex';
        }

        function closeDelete() {
            document.getElementById('delete-modal').style.display = 'none';
            deleteStep(1);
        }
    </script>
</x-app-shell>
