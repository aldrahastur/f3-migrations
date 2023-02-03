<?php

namespace F3Migration;

use DB\SQL;

class DatabaseConnector
{


    private $f3;

    public function __construct()
    {
        $this->f3 =  Base::instance();
    }

    public function getDatabaseConnection($driver, $server = null, $port = null, $user = null, $password = null)
    {
        switch ($driver) {
            case 'sqlite':
               $sqlPath = $this->f3->get('BASE').'/database/database.sqlite';
                $db = new SQL('sqlite:'.$sqlPath);
                break;
            default :

                $db = 'no driver specified';
                break;
        }

        return $db;
    }
}