<?php

use RiseTechApps\FormRequest\Tests\Support\NullLogger;

if (!function_exists('logglyError')) {
    /**
     * Test-only stub. In production this helper comes from the host
     * application logging package; here it only needs to accept the call chain.
     */
    function logglyError(): NullLogger
    {
        return new NullLogger();
    }
}
