{{-- The period filter every list page shares. The query half is
     App\Support\Period::filter(). Reads $range and $month from the page;
     pass the page's own route, and the running total with its label. --}}
@php
    $active = 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);';
    $normal = 'background:white; color:hsl(24,10%,20%); border-color:hsl(30,15%,85%);';
    $pill   = 'padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none;';
@endphp
<div class="app-filters" style="display:flex; gap:8px; align-items:center; margin-bottom:24px; flex-wrap:wrap;">
    @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
        <a href="{{ route($route, ['range' => $key]) }}" style="{{ $pill }} {{ $range === $key ? $active : $normal }}">{{ __($label) }}</a>
    @endforeach
    <form method="GET" action="{{ route($route) }}" style="display:flex; gap:6px; align-items:center;">
        <input type="month" name="month" value="{{ $month }}"
               style="padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
        <button type="submit" style="{{ $pill }} cursor:pointer; {{ ! $range ? $active : $normal }}">{{ __('Custom') }}</button>
    </form>
    <span style="margin-left:auto; font-size:14px; color:hsl(24,5%,45%);">
        {{ $totalLabel }}: <strong>@money($total)</strong>
    </span>
</div>
