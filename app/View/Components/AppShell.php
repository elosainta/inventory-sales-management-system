<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppShell extends Component
{
    public function render(): View
    {
        return view('layouts.app-shell');
    }
}