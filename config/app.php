<?php

// Only what differs from the framework's config/app.php, which Laravel merges
// underneath this file.
return [

    // The framework hard-codes 'UTC' here, so APP_TIMEZONE alone was ignored.
    // It must read the same variable config/database.php derives the MariaDB
    // session offset from, or the app and the database would disagree by
    // eight hours on every timestamp written. Set to Asia/Kuala_Lumpur in
    // docker-compose.yml.
    'timezone' => env('APP_TIMEZONE', 'UTC'),

];
