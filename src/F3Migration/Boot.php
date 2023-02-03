<?php

namespace F3Migration;

class Boot
{
    /**
     * Start the migration with default settings
     *
     * @return void
     */
    public static function now() {
        //boot!
        $internal = MigrationController::instance();
        $path = \F3::instance()->get('ILGAR.path');
        \F3::instance()->route(\F3::instance()->get('ILGAR.access_path'), function($f3) use (&$internal){
            $internal->do_migrate();
        });
    }

    /**
     * Triggers the migration to start.
     *
     * @return array of quick stats.
     */
    public static function trigger_on() {
        return MigrationController::instance()->do_migrate();
    }

}