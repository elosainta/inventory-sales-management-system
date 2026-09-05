<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

        </div>

        @if(auth()->user()->isJuniorChef())
        <div>
            <x-input-label for="preferred_language" :value="__('Display Language')" />
            <p class="text-sm text-gray-500 mt-1 mb-2">Applies to the prep checklist page.</p>
            <div id="lang-options" style="display:flex; gap:12px; margin-top:4px;">
                <label id="lang-en" style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px;">
                    <input type="radio" name="preferred_language" value=""
                           {{ auth()->user()->preferred_language !== 'id' ? 'checked' : '' }}
                           onchange="updateLangStyle()">
                    English
                </label>
                <label id="lang-id" style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px;">
                    <input type="radio" name="preferred_language" value="id"
                           {{ auth()->user()->preferred_language === 'id' ? 'checked' : '' }}
                           onchange="updateLangStyle()">
                    Bahasa Indonesia
                </label>
            </div>
        </div>
        <script>
            function updateLangStyle() {
                const idChecked = document.querySelector('input[name="preferred_language"][value="id"]').checked;
                const active = 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);';
                const inactive = 'display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 16px; border:1px solid hsl(30,15%,85%); border-radius:6px;';
                document.getElementById('lang-en').style.cssText = inactive + (!idChecked ? 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);' : '');
                document.getElementById('lang-id').style.cssText = inactive + (idChecked ? 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);' : '');
            }
            document.addEventListener('DOMContentLoaded', updateLangStyle);
        </script>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p class="text-sm text-gray-600">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
