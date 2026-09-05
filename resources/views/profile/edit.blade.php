<x-app-shell>
    <div style="margin-bottom:28px;">
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Profile') }}</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">Manage your account details, password, and preferences.</p>
    </div>

    <div style="display:flex; flex-direction:column; gap:24px; max-width:640px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:28px;">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:28px;">
            @include('profile.partials.update-password-form')
        </div>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:28px;">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-shell>
