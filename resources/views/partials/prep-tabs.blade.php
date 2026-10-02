{{-- Today / History, on all three prep screens. "Today" means the manager's
     overview for whoever has it and the chef's own checklist for everyone
     else, so one partial serves both without a second copy. --}}
@php
    $prepTabs = [
        ['route' => Gate::allows('overview-checklist') ? 'prep.overview' : 'prep.index', 'label' => __('Today'), 'on' => $active === 'today'],
        ['route' => 'prep.history', 'label' => __('History'), 'on' => $active === 'history'],
    ];
@endphp
<div style="display:flex; gap:4px; border-bottom:1px solid hsl(30,15%,90%); margin-bottom:24px;">
    @foreach($prepTabs as $tab)
        <a href="{{ route($tab['route']) }}"
           style="padding:10px 16px; font-size:14px; text-decoration:none; margin-bottom:-1px;
                  font-weight:{{ $tab['on'] ? '600' : '500' }};
                  color:{{ $tab['on'] ? 'hsl(20,60%,45%)' : 'hsl(24,5%,45%)' }};
                  border-bottom:2px solid {{ $tab['on'] ? 'hsl(20,60%,45%)' : 'transparent' }};">{{ $tab['label'] }}</a>
    @endforeach
</div>
