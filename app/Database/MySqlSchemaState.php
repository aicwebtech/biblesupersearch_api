<?php

namespace App\Database;

use Illuminate\Database\Schema\MysqlSchemaState as Base;
use Illuminate\Database\Connection;

class MySqlSchemaState extends Base
{
    public function dump(Connection $connection, $path)
    {
        parent::dump($connection, $path);

        foreach(config('database.dump_tables') as $table) {
            $this->appendTableData($path, $connection->getTablePrefix() . $table);
        }
    }

    /**
     * Append table data to the schema dump.
     *
     * @param  string  $path
     * @param  string  $table
     * @return void
     */
    protected function appendTableData(string $path, string $table)
    {
        $process = $this->executeDumpProcess($this->makeProcess(
            $this->baseDumpCommand().' '.$table.' --no-create-info --skip-extended-insert --skip-routines --compact'
        ), null, array_merge($this->baseVariables($this->connection->getConfig()), [
            //
        ]));

        $this->files->append($path, $process->getOutput());
    }
}