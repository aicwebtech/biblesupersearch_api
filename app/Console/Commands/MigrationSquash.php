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

class MigrationSquash extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'migration:squash
                {--database= : The database connection to use}
                {--path= : The path where the schema dump file should be stored}';

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
    protected $description = 'Dump the given database schema, with data';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $connections
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return int
     */
    public function handle(ConnectionResolverInterface $connections, Dispatcher $dispatcher)
    {
        $connection = $connections->connection($database = $this->input->getOption('database'));

        $this->schemaState($connection)->dump(
            $connection, $path = $this->path($connection)
        );

        $dispatcher->dispatch(new SchemaDumped($connection, $path));

        $info = 'Database schema dumped';

        $this->components->info($info.' successfully.');
    }

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

    /**
     * Get the path that the dump should be written to.
     *
     * @param  \Illuminate\Database\Connection  $connection
     */
    protected function path(Connection $connection)
    {
        return tap($this->option('path') ?: database_path('schema/'.$connection->getName().'-schema.sql'), function ($path) {
            (new Filesystem)->ensureDirectoryExists(dirname($path));
        });
    }

}
