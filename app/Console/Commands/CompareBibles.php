<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;
use \DB;

class CompareBibles extends BibleAbstract
{
    protected $append_signature = FALSE;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:compare {module1} {module2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compares two Bibles.  To see options, use bible:list';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $module1 = $this->argument('module1');
        $module2 = $this->argument('module2');

        $Bible1 = Bible::findByModule($module1);
        $Bible2 = Bible::findByModule($module2);

        if(!$Bible1 || !$Bible2) {
            if(!$Bible1) {
                echo('Bible module does not exist:' . $module1 . PHP_EOL);
            }          

            if(!$Bible2) {
                echo('Bible module does not exist:' . $module2 . PHP_EOL);
            }

            return;

            echo PHP_EOL;

            return $this->_listBibles();
        }

        $tb1 = $Bible1->verses()->getTable();
        $tb2 = $Bible2->verses()->getTable();

        $results = true;
        $results = !$this->_queryResults($tb1, $tb2, 'minus') ? false : $results;
        $results = !$this->_queryResults($tb2, $tb1, 'plus')  ? false : $results;

        if($results) {
            echo PHP_EOL . 'No verse numbering differences found between Bibles.' . PHP_EOL;
        } else {
            echo PHP_EOL . 'Verse numbering differences found!' . PHP_EOL;
        }
    }

    private function _queryResults($tb1, $tb2, $status)
    {
        $Query = DB::table($tb1 . ' AS tb');
        $Query->select('tb.book', 'books_en.name AS book_name', 'tb.chapter','tb.verse');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');
        $Query->join('books_en', 'tb.book', '=', 'books_en.id');
        $Query->leftJoin($tb2 . ' AS tb2', function($join) {
            $join->on('tb.book', '=', 'tb2.book')
                 ->on('tb.chapter', '=', 'tb2.chapter')
                 ->on('tb.verse', '=', 'tb2.verse');
        });
        $Query->whereNull('tb2.id');

        $results = $Query->get();

        if($results && count($results)) {
            echo PHP_EOL;

            foreach($results as $row) {
                echo str_pad($row->book, 3);
                echo str_pad($row->book_name, 20);
                echo str_pad($row->chapter, 4);
                echo str_pad($row->verse, 4);
                echo str_pad($status, 30);
                echo PHP_EOL;
            }

            echo PHP_EOL;
            echo $status . ' count: ' . count($results) . PHP_EOL;

            echo PHP_EOL;
            return false;
        } else {
            return true;
        }
    }
}
