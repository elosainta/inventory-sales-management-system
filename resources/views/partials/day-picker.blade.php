{{-- Multi-select day-of-week pills. Expects $prefix, a unique id namespace so
     the Add and Edit forms can each hold their own set on the same page. --}}
@php
    $pickerDays = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
@endphp
<div style="display:flex; flex-wrap:wrap; gap:8px;">
    @foreach($pickerDays as $value => $label)
        <label style="padding:6px 12px; border:1px solid hsl(30,15%,85%); border-radius:20px; font-size:13px; cursor:pointer; user-select:none; background:white; color:hsl(24,10%,15%);">
            <input type="checkbox" name="active_days[]" value="{{ $value }}" id="{{ $prefix }}-day-{{ $value }}"
                   style="display:none;" onchange="toggleDayPill(this)">
            {{ $label }}
        </label>
    @endforeach
</div>
