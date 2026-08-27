<?php

namespace App\Renderers;

use \DB;
use \Schema;
use App\Helpers;
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

    // Rows per SELECT page from the source DB, and per INSERT batch into the rendered file.
    // Recomputed in _renderStart() from the bound-variable ceiling the SQLite build writing the
    // file actually enforces, which varies by build - see Helpers::getMaxBoundVariables().
    protected $chunk_size = 1000;

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

        // A chunk binds every verse column in one INSERT, so the batch has to fit the variable
        // ceiling of the SQLite build writing this file, which is compile-time configurable.
        $this->chunk_size = Helpers::getInsertChunkSize($this->include_book_name ? 5 : 4, $cn);

        $info = $this->Bible->getMeta();
        $info['copyright_statement'] = $this->_getCopyrightStatement(TRUE);
        $meta = [];

        foreach($info as $field => $value) {
            $meta[] = ['field' => $field, 'value' => $value];
        }

        DB::connection($cn)->table('meta')->insert($meta);
        
        return TRUE;
    }

    /**
     * Render every verse inside one transaction. Each chunk insert would otherwise be its own
     * implicit transaction, so SQLite would flush the file to disk once per chunk.
     */
    protected function _beforeVerseRender() 
    {
        DB::connection( $this->getDbConnectionName('render') )->beginTransaction();
    }

    protected function _afterVerseRender() 
    {
        DB::connection( $this->getDbConnectionName('render') )->commit();
    }

    /**
     * Roll the render transaction back when a verse chunk throws. RenderManager keeps going
     * with the next Bible after an exception, so an abandoned transaction would otherwise hold
     * a write lock and a -journal file open for the rest of the process.
     */
    protected function _onVerseRenderError(\Throwable $e) 
    {
        DB::connection( $this->getDbConnectionName('render') )->rollBack();
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
