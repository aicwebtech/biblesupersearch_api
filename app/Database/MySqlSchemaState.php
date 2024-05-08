<?php

namespace App\Database;

use Illuminate\Database\Schema\MysqlSchemaState as Base;
use Illuminate\Database\Connection;

class MySqlSchemaState extends Base
{
    public function dump(Connection $connection, $path)
    {
        die('no dump for you');
    }
}