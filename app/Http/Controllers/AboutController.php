<?php

namespace App\Http\Controllers;

use App\Support\ReleaseNotes;
use Illuminate\Support\Facades\Gate;

class AboutController extends Controller
{
    public function index()
    {
        Gate::authorize('view-about');

        return view('about.index', [
            'currentVersion'   => ReleaseNotes::CURRENT_VERSION,
            'firstReleaseDate' => ReleaseNotes::FIRST_RELEASE_DATE,
            'firstCommit'      => ReleaseNotes::FIRST_COMMIT,
            'totalCommits'     => ReleaseNotes::TOTAL_COMMITS,
            'releases'         => ReleaseNotes::all(),
        ]);
    }
}
