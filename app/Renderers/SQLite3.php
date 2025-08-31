<?php

namespace App\Renderers;

use \DB;
use \Schema;
use Illuminate\Database\Schema\Blueprint;

class SQLite3 extends RenderAbstract 
{
    static public $name = 'SQLite';
    static public $description = 'SQLite 3 database';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = TRUE; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;     

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 1;  
       
    // Estimated size to render a Bible of the given format, in MB. 
    static protected $render_est_size = 5;      

    protected $file_extension = 'sqlite';
    protected $include_book_name = FALSE;

    protected $chuck_size = 3000;

    protected $TableVerses = null;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() 
    {
        $filepath = $this->getRenderFilePath(TRUE);
        
        if(file_exists($filepath)) {
            unlink($filepath);
        }
        
        touch($filepath);

        // Dynamically create 'render' as a DB connection
        $cn = $this->createDbConnection('render', [
            'driver'   => 'sqlite',
            'database' => $filepath,
            'prefix'   => '',
        ]);

        Schema::connection($cn)->dropIfExists('meta');
        Schema::connection($cn)->dropIfExists('verses');

        Schema::connection($cn)->create('meta', function(Blueprint $table) {
            $table->string('field', 255);
            $table->text('value')->nullable();
            $table->primary('field');
        });

        Schema::connection($cn)->create('verses', function(Blueprint $table) {
            $table->integer('id', TRUE);
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->text('text')->charset('utf8');
            $table->index('book', 'ixb');
            $table->index('chapter', 'ixc');
            $table->index('verse', 'ixv');
            $table->index(['book', 'chapter', 'verse'], 'ixbcv'); // Composite index on b, c, v
        });

        $info = $this->Bible->getMeta();
        $info['copyright_statement'] = $this->_getCopyrightStatement(TRUE);
        $meta = [];

        foreach($info as $field => $value) {
            $meta[] = ['field' => $field, 'value' => $value];
        }

        DB::connection($cn)->table('meta')->insert($meta);
        
        return TRUE;
    }

    protected function _renderVerseChunk() 
    {
        DB::connection( $this->getDbConnectionName('render') )->table('verses')->insert($this->chunk_data);
    }

    protected function _renderFinish() 
    {
        return TRUE;
    }
}
