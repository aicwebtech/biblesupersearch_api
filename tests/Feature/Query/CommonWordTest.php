<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Depends;

use App\Engine;
use App\Models\Language;

class CommonWordTest extends TestCase 
{

    static protected $Languages = [];
    static protected $language_cache = [];

    protected $config_list = [
        'always' => ['name' => 'Allow Common Words:  Yes / Always'],
        'exact' => ['name' => 'Allow Common Words:  Yes, if search query includes other words'],
        'never' => ['name' => 'Allow Common Words:  No / Never'],
    ];

    protected $query_tests = [
        'QueryEn' => [
            [
                'params' => ['bible' => 'kjv', 'search' => 'and', 'page_limit' => 30],
                // No errors, because language not specified
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and', 'language' => 'bb', 'page_limit' => 30],
                'errors' => false, // No errors, because language not found
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => ''],
                // No erors, because no common words on language
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => 'and'],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => true,
                    'exact' => true,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => "a\nan\nand\nthe\nor"],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => true,
                    'exact' => true, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'or','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => true,
                    'exact' => true, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'hope or faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'or']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'the hope or faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, or']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
        ],
        'QueryEnCapital' => [
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => "a\nan\nAnd\nTHE"],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => true,
                    'exact' => true, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'THE hope And faith', 'language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => "a\nan\nand\nthe"],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'the hope ANd faith', 'language' => 'en', 'page_limit' => 30],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                // 'AND' is a boolean operator; it NEVER gets banned.
                'params' => ['bible' => 'kjv', 'search' => 'the hope AND faith', 'language' => 'en', 'page_limit' => 30],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                // 'AND' is a boolean operator; it NEVER gets banned.
                'params' => ['bible' => 'kjv', 'search' => 'hope AND faith', 'language' => 'en', 'page_limit' => 30],
                'errors'  => [
                    'never' => false,
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                // Capitalization in BOTH
                'params' => ['bible' => 'kjv', 'search' => 'THE hope And faith', 'language' => 'en', 'page_limit' => 30],
                'lang'   => ['en' => "a\nan\nAnd\nTHE"],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],

        ],
        'QueryEnSearchType' => [
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => null],
                'lang'   => ['en' => "a\nan\nand\nthe\nor"],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'and'],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'or'],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'xor'],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'two_or_more'],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'boolean'],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                // Phrase search type
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'phrase'],
                // No errors, because phrases are allowed
                'errors'  => [
                    'never' => false,
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => 'regexp'],
                // No errors, because regexp are allowed
                'errors'  => [
                    'never' => false,
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'language' => 'en', 'search' => '"created the heaven"', 'page_limit' => 30, 'search_type' => 'boolean'],
                // No errors, because Phrase within boolean - passes 
                'errors'  => [
                    'never' => false,
                    'exact' => false, 
                    'always' => false,
                ],
            ],
        ],
        'QueryEnHighlight' => [
            [
                'params' => ['bible' => 'kjv', 'search' => 'and', 'highlight' => true, 'page_limit' => 30],
                // No errors, because language not specified
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and', 'language' => 'bb', 'highlight' => true, 'page_limit' => 30],
                // No errors, because language not found
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true, 'page_limit' => 30],
                'lang'   => ['en' => ''],
                // No errors, because no common words
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true, 'page_limit' => 30],
                // Has errors, because and on word list
                'lang'   => ['en' => "a\nan\nand\nthe"],
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'and']]],
                    'exact' => [['errors.common_words', ['wordlist' => 'and']]],
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'highlight' => true, 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'highlight' => true, 'page_limit' => 30],
                // Has errors, because and on word list
                'errors'  => [
                    'never' => [['errors.common_words', ['wordlist' => 'the, and']]],
                    'exact' => false, 
                    'always' => false,
                ],
            ],
        ],
        'LanguageMismatch' => [
            [
                'params' => ['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'lv', 'page_limit' => 30],
                'lang'   => ['lv' => '', 'en' => ''],
                // No errors, because 'and' is not banned
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'lv', 'page_limit' => 30],
                'lang' => ['en' => "a\nan\nand\nthe\nor"],
                // No errors, because 'and' is not banned (only language at play is lv/Latvian)
                'errors'  => [
                    'never' => false,
                    'exact' => false,
                    'always' => false,
                ],
            ],
            // [
            //     'params' => ['bible' => ['lv_gluck_8','kjv'], 'search' => 'and', 'language' => 'lv', 'page_limit' => 30],
            //     // Errors, because 'and' is not allowed in English (via kjv) NOT WORKING, NEVER HAS!
            //     'skipped' => ['always'],
            //     'errors'  => [
            //         'never' => true,
            //         'exact' => true,
            //         'always' => false, // skipped, takes forever to run
            //     ],
            // ],
            [
                'params' => ['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'en', 'page_limit' => 30],
                // Errors, because 'and' is not allowed in English (via UI)
                'errors'  => [
                    'never' => true,
                    'exact' => true,
                    'always' => false,
                ],
            ],
            [
                'params' => ['bible' => ['lv_gluck_8','kjv'], 'search' => 'and', 'language' => 'en', 'page_limit' => 30],
                // Errors, because 'and' is not allowed in English (via kjv AND UI)
                'skipped' => ['always'],
                'errors'  => [
                    'never' => true,
                    'exact' => true,
                    'always' => false, // skippe, takes forever to run
                ],
            ],
        ],
    ];

    public $filter = null; //'LanguageMismatch';

    public function testSave() 
    {
        $Language = Language::findByCode('mh');
        $cache = $Language->common_words;

        $words = ['come', 'at', 'me', 'bro'];
        $newlines = ["\n", "\r", "\r\n"];

        foreach($newlines as $nl) {
            $Language->common_words = implode($nl, $words);
            $Language->save();
            $arr = $Language->getCommonWordsAsArray();

            $this->assertIsArray($arr);
            $this->assertNotEmpty($arr);
            $this->assertEquals($words, $Language->getCommonWordsAsArray());
        }

        $Language->common_words = $cache;
        $Language->save();
    }

    public function testQueryGlobal()
    {
        $config_cache = config('bss.search_common_words');
        
        foreach($this->config_list as $config => $data) {
            config(['bss.search_common_words' => $config]);

            foreach($this->query_tests as $method => $tests) {
                if($this->filter && $method != $this->filter) {
                    continue; // Skip tests that do not match the filter
                }
                
                foreach($tests as $idx => $test) {
                    $desc = "Config: {$config} - Method: {$method} - Test #{$idx}";
                    $this->helpTestQuery($test, $config, $desc);
                }
            }
        }

        // Reset config
        config(['bss.search_common_words' => $config_cache]);

        foreach(self::$language_cache as $code => $words) {
            $Language = self::getLanguage($code);
            $Language->common_words = $words; // Restore original common words
            $Language->save();
        }
    }

    protected function helpTestQuery($test, $config, $desc)
    {
        $Engine = new Engine(); // Need new instance because this test is colliding with others
        $lang_cache = [];

        if(isset($test['skipped']) && in_array($config, $test['skipped'])) {
            return;  // Skip this config/test
        }

        if(isset($test['lang'])) {
            foreach($test['lang'] as $lang => $words) {
                $Language = self::getLanguage($lang);

                $this->assertNotEmpty($Language, "Language '{$lang}' not found: {$desc}");

                $lang_cache[$lang] = $Language->common_words;
                $Language->common_words = $words;
                $Language->save();

                $this->assertEquals($words, $Language->common_words, "Language '{$lang}' common words not set correctly: {$desc}");
            }
        }

        $this->assertEquals($config, config('bss.search_common_words'), "Config '{$config}' not set correctly: {$desc}");
        
        $results = $Engine->actionQuery($test['params']);

        $this->assertIsArray($test['errors'], 'Test \'errors\' is not an array ' .  $desc);
        $this->assertArrayHasKey($config, $test['errors'], "Test has no errors definde for config '{$config}': {$desc}");

        $error_tests = $test['errors'][$config];

        if($error_tests) {
            $this->assertTrue($Engine->hasErrors(), "Query should result in errors: {$desc}");
            $errors = $Engine->getErrors();
            $this->assertNotEmpty($errors, "Engine has no errors: {$desc}");

            if(is_array($error_tests)) {
                foreach($error_tests as $et) {
                    $tr = trans($et[0], $et[1] ?? []);
                    $this->assertContains($tr, $errors, "Error '{$tr}' not found in query errors: {$desc}");
                }
            } 

        } else {
            if($Engine->hasErrors()) {
                $errors = $Engine->getErrors();
                $this->fail("Query should not result in errors, but got: " . implode(', ', $errors) . " - {$desc}");
            }
            
            $this->assertFalse($Engine->hasErrors(), "Query should not result in errors: {$desc}");
        }
    }

    static protected function getLanguage($code)
    {
        if(!isset(self::$Languages[$code])) {
            self::$Languages[$code] = Language::findByCode($code);
            self::$language_cache[$code] = self::$Languages[$code]->common_words ?? '';
        }
        
        return self::$Languages[$code];
    }
}
