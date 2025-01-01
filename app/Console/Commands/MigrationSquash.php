<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Events\SchemaDumped;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Database\Console\DumpCommand;

class MigrationSquash extends DumpCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'migration:squash
                {--database= : The database connection to use}
                {--path= : The path where the schema dump file should be stored}
                {--prune : Delete all existing migration files}';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'migration:squash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the given database schema, including data from specific tables (extends schema:dump)';

    /**
     * Create a schema state instance for the given connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return mixed
     */
    protected function schemaState(Connection $connection)
    {
        $schemaState = $connection->getSchemaState();

        // Yank class name from default SchemaState class
        // See if custom extension exists
        // if so, create instance of extension and use that instead!
        $cls_parts  = explode('\\', get_class($schemaState));
        $base_class = array_pop($cls_parts);

        $ext_class = '\App\Database\\' . $base_class;

        if(class_exists($ext_class)) {
            $schemaState = new $ext_class($connection);
        }

        return $schemaState
                ->withMigrationTable($connection->getTablePrefix().Config::get('database.migrations', 'migrations'))
                ->handleOutputUsing(function ($type, $buffer) {
                    $this->output->write($buffer);
                });
    }
}
