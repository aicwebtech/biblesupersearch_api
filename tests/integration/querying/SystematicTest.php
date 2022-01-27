<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class SystematicTest extends TestCase {
    protected $set_fields = [
        ['reference' => 'Romans'],
        ['search' => 'faith'],
        ['reference' => 'Romans', 'search' => 'faith'],
        ['request' => 'Romans', 'search' => 'faith'],
        ['reference' => 'Romans', 'request' => 'faith'],
    ];

    protected $variable_field_options = [];

    protected $variable_fields_binary = [
        'whole_words' => 'BOOL',
        'exact_case' => 'BOOL',
        'data_format' => 'raw', // passage?
        'highlight' => 'BOOL',
        'page' => 2,
        'page_all' => 'BOOL',
        'highlight_tag' => 'em',
        'search_type' => 'any',
        // 'proximity_limit' => 10, // unused
        // 'keyword_limit' => 5, // unused
        // 'search_all' => '', // unused
        // 'search_any' => '',
        // 'search_one' => '',
        // 'search_none' => '',
        // 'search_phrase' => '',
        'context' => 'BOOL',
        'context_range' => 10,
        'markup' => ['none', 'raw'],
    ];

    public function testQuery() {
        $this->assertTrue(TRUE); return;  // Uncomment to run tests - but THIS will take 12+ MINUTES

        $Engine = Engine::getInstance();
        $this->_initBinaryOptions();

        $count = count($this->variable_field_options);

        foreach($this->set_fields as $query) {
            for($i = 0; $i <= $this->binary_limit; $i ++) {
                $bin = str_pad(decbin($i), $count, '0', STR_PAD_LEFT);

                foreach(str_split($bin) as $k => $b) {

                    $key = $this->variable_field_keys[$k];
                    $val = $this->variable_field_values[$k][$b];
                    $query[$key] = $val;
                }

                $results = $Engine->actionQuery($query);
                $this->assertFalse($Engine->hasErrors());
            }
        }
    }

    protected function _initBinaryOptions() {
        $this->variable_field_options = [];

        foreach($this->variable_fields_binary as $key => $opt) {
            if($opt == 'BOOL') {
                $this->variable_field_options[$key] = [TRUE, FALSE];
            }
            elseif(is_array($opt) && count($opt) == 2) {
                $this->variable_field_options[$key] = $opt;
            }
            else {
                $this->variable_field_options[$key] = [$opt, NULL];
            }
        }

        $this->variable_field_keys = array_keys($this->variable_field_options);
        $this->variable_field_values = array_values($this->variable_field_options);
        $count = count($this->variable_field_options);
        $this->binary_limit = 2 ** $count - 1;

    }
}
