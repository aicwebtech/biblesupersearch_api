<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Engine;

class SystematicTest extends TestCase 
{
    protected static $set_fields = [
        ['reference' => 'Romans'],
        // ['search' => 'faith'],
        // ['reference' => 'Romans', 'search' => 'faith'],
        // ['request' => 'Romans', 'search' => 'faith'],
        // ['reference' => 'Romans', 'request' => 'faith'],
    ];

    protected static $variable_field_options = [];
    protected static $variable_field_keys = [];
    protected static $variable_field_values = [];
    protected static $binary_limit = 0;

    // 11 fields, 2^11 = 2048 combinations.  5 set fields above, for a total of 2048 * 5 = 10240 tests!
    // This is slow, and it's running out of memory ... 
    protected static $variable_fields_binary = [
        'whole_words' => 'BOOL',
        'exact_case' => 'BOOL',
        'data_format' => 'raw', // passage?
        'highlight' => 'BOOL',
        'page' => 2,
        'page_all' => 'BOOL',
        'highlight_tag' => 'em',
        'search_type' => 'any_word',
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

    public function testTest()
    {
        $this->assertTrue(TRUE);
    }

    #[DataProvider('queryDataProvider')]
    // Enable to run tests - but THIS will take 12+ MINUTES, and may run out of memory!
    public function _testQuery(array $query) 
    {
        $Engine = Engine::getInstance();

        $results = $Engine->actionQuery($query);

        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $this->fail('Query failed with errors: ' . implode(', ', $errors));
        }

        $this->assertFalse($Engine->hasErrors());
    }

    public static function queryDataProvider()
    {
        self::_initBinaryOptions();

        $data = [];
        $count = count(self::$variable_field_options);

        foreach(self::$set_fields as $query) {
            for($i = 0; $i <= self::$binary_limit; $i ++) {
                $bin = str_pad(decbin($i), $count, '0', STR_PAD_LEFT);

                foreach(str_split($bin) as $k => $b) {
                    $key = self::$variable_field_keys[$k];
                    $val = self::$variable_field_values[$k][$b];
                    $query[$key] = $val;
                }

                $data[] = [$query];
            }
        }

        return $data;
    }

    protected static function _initBinaryOptions() 
    {
        self::$variable_field_options = [];

        foreach(self::$variable_fields_binary as $key => $opt) {
            if($opt == 'BOOL') {
                self::$variable_field_options[$key] = [TRUE, FALSE];
            }
            elseif(is_array($opt) && count($opt) == 2) {
                self::$variable_field_options[$key] = $opt;
            }
            else {
                self::$variable_field_options[$key] = [$opt, NULL];
            }
        }

        self::$variable_field_keys = array_keys(self::$variable_field_options);
        self::$variable_field_values = array_values(self::$variable_field_options);
        $count = count(self::$variable_field_options);
        self::$binary_limit = 2 ** $count - 1;
    }
}
