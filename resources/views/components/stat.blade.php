@props(['label', 'ink' => 'inherit'])

{{-- One figure in a card. Anonymous component, like app-shell: there is no
     class behind it and nothing to configure beyond the label and its ink. --}}
<div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:14px 16px;">
    <div style="font-size:12px; color:hsl(24,5%,45%); text-transform:uppercase; letter-spacing:0.04em;">{{ $label }}</div>
    <div style="font-family:'JetBrains Mono',monospace; font-size:20px; margin-top:4px; color:{{ $ink }};">{{ $slot }}</div>
</div>
