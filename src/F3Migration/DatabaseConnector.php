<?php

namespace F3Migration;

use DB\SQL;

class DatabaseConnector
{

    static function getDatabaseConnection($driver, $server = null, $port = null, $dbname = null, $user = null, $password = null): string|SQL
    {
        return match ($driver) {
            'mysql' => new SQL('mysql:host=' . $server . ';port=' . $port . ';dbname=' . $dbname, $user, $password),
            'sqlite' => new SQL('sqlite:database/database.sqlite'),
            default => 'no driver specified',
        };
    }
}