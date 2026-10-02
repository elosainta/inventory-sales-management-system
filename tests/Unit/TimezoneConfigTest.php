<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The app's time zone and the MariaDB session offset must move together:
 * every time column is TIMESTAMP, and an app on Malaysia time writing through a
 * UTC session stores each new time 8 hours off. The first attempt set only
 * APP_TIMEZONE - which the framework's own config/app.php ignored.
 */
class TimezoneConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_TIMEZONE');
        unset($_ENV['APP_TIMEZONE'], $_SERVER['APP_TIMEZONE']);

        parent::tearDown();
    }

    public function test_the_app_and_database_follow_one_setting(): void
    {
        putenv('APP_TIMEZONE=Asia/Kuala_Lumpur');
        $_ENV['APP_TIMEZONE'] = $_SERVER['APP_TIMEZONE'] = 'Asia/Kuala_Lumpur';

        $app = require __DIR__ . '/../../config/app.php';
        $db  = require __DIR__ . '/../../config/database.php';

        $this->assertSame('Asia/Kuala_Lumpur', $app['timezone']);
        $this->assertSame('+08:00', $db['connections']['mariadb']['timezone']);
        $this->assertSame('+08:00', $db['connections']['mariadb_demo']['timezone']);
    }
}
