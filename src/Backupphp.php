<?php

namespace Danilocgsilva;

class Backupphp
{
    public static function backup($host, $user, $dbname, $pass)
    {
        $instance = new Backupphp();
        echo 'Host: ' . $host . ', user: ' . $user . ', ' . $dbname . ', pass: ' . $pass;
    }
}
