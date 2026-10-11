{{-- One photo, a statement or a payment slip, completes this supplier's
     purchases (the Owner, 2026-10-02), instead of pressing each Pending
     button in turn. It covers every purchase still pending or still without
     a receipt — the second so a purchase marked Completed by hand, or by an
     invoice scan before 2026-10-11, can still be given one. It posts the ids
     on screen, so it completes exactly what the reader saw. --}}
@can('manage-purchases')
    @if($open->isNotEmpty())
        <form action="{{ route('purchases.complete') }}" method="POST" enctype="multipart/form-data"
              style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-top:1px solid hsl(30,15%,90%);">
            @csrf
            @foreach($open->pluck('id') as $id)
                <input type="hidden" name="purchase_ids[]" value="{{ $id }}">
            @endforeach
            <label style="cursor:pointer; padding:6px 14px; background:hsl(20,60%,45%); color:white; border-radius:6px; font-size:13px; font-weight:600; white-space:nowrap;">
                Upload receipt
                <input type="file" name="photo" accept="image/*" onchange="this.form.submit()"
                       style="position:absolute; opacity:0; width:0; height:0;">
            </label>
            <span style="font-size:13px; color:hsl(24,5%,45%);">{{ $open->count() }} {{ \Illuminate\Support\Str::plural('purchase', $open->count()) }} not paid yet or without a receipt — uploading marks them paid</span>
        </form>
    @endif
@endcan
