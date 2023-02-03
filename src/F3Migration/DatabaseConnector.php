<?php

namespace F3Migration;

use DB\SQL;

class DatabaseConnector
{


    private $f3;

    public function __construct()
    {
        $this->f3 =  \Base::instance();
    }

    public function getDatabaseConnection($driver, $server = null, $port = null, $user = null, $password = null)
    {
        switch ($driver) {
            case 'sqlite':
                $db = new SQL('sqlite:database/database.sqlite');
                break;
            default :

                $db = 'no driver specified';
                break;
        }

        return $db;
    }
}