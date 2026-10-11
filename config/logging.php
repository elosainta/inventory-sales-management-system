<?php

use App\Logging\GithubIssues;

// Only what differs from the framework's config/logging.php, which Laravel
// merges underneath this file (channel by channel).
return [

    'channels' => [

        // GitHub joins the stack only once its token is set, so a missing
        // token is "no issues opened", not a broken logger.
        'stack' => [
            'driver' => 'stack',
            'channels' => array_filter([
                ...explode(',', (string) env('LOG_STACK', 'single')),
                env('GITHUB_ISSUES_TOKEN') ? 'github' : null,
            ]),
            'ignore_exceptions' => false,
        ],

        // Errors only, whatever LOG_LEVEL the file log runs at. Token, repo
        // and assignee are services.github. See GithubIssues.
        'github' => [
            'driver' => 'custom',
            'via' => GithubIssues::class,
        ],

    ],

];
