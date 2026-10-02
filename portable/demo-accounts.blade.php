{{-- Offline demo only: portable/build.sh adds this under the sign-in form.
     One button per role fills in that role's sample account, so whoever is
     trying the demo can see how each role's menu differs without a crib sheet.
     The accounts come from SampleDataSeeder; every password is "password". --}}
@php($demoAccounts = [
    ['Owner',       'owner@example.test',     'Everything, including the financial dashboard'],
    ['Head Chef',   'sam@example.test',       'Runs the kitchen: prep, stock, purchases, sales'],
    ['Junior Chef', 'chef1@example.test',     'Prep checklist, production, stock-take'],
    ['Part timer',  'parttimer@example.test', 'Prep, inventory key-in, wastage'],
    ['Admin',       'admin@example.test',     'Support tickets, and every page'],
])

<div style="margin-top:24px; padding-top:20px; border-top:1px solid hsl(24,10%,90%);">
    <p style="font-size:12px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; color:hsl(24,5%,45%); margin:0 0 10px;">
        Try a demo account
    </p>
    <div style="display:flex; flex-direction:column; gap:6px;">
        @foreach($demoAccounts as [$role, $email, $what])
            <button type="button" class="demo-account" data-email="{{ $email }}"
                    style="display:flex; justify-content:space-between; align-items:baseline; gap:12px; width:100%; text-align:left; padding:8px 12px; border:1px solid hsl(24,10%,85%); border-radius:8px; background:hsl(40,33%,98%); cursor:pointer;">
                <span style="font-size:13px; font-weight:600; color:hsl(20,60%,40%); white-space:nowrap;">{{ $role }}</span>
                <span style="font-size:12px; color:hsl(24,5%,45%); text-align:right;">{{ $what }}</span>
            </button>
        @endforeach
    </div>
    <p style="font-size:12px; color:hsl(24,5%,50%); margin:10px 0 0;">Every demo password is <strong>password</strong>.</p>
</div>

<script>
    document.querySelectorAll('.demo-account').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('email').value = button.dataset.email;
            document.getElementById('password').value = 'password';
            document.getElementById('submit-btn').click();
        });
    });
</script>
