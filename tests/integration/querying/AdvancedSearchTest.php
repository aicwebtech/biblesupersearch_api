<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;

class AdvancedSearchTest extends TestCase {
    public function testAllWords() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search_all' => 'faith hope', 'format_structure' => 'raw'];

        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(9, $results['kjv']);

        $input = ['bible' => 'kjv', 'search_all' => 'faith hope', 'reference' => '1 Thess', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(2, $results['kjv']);
    }

    public function testAnyWords() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search_any' => 'faith hope', 'format_structure' => 'raw'];

        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(462, $results['kjv']);

        $input['whole_words'] = 'on';
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(344, $results['kjv']);

        $input = ['bible' => 'kjv', 'search_any' => 'faith hope', 'reference' => 'Acts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(24, $results['kjv']);
    }

    public function testOneWord() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search_one' => 'faith hope', 'format_structure' => 'raw'];

        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(453, $results['kjv']);

        $input['whole_words'] = 'on';

        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(336, $results['kjv']);

        $input = ['bible' => 'kjv', 'search_one' => 'faith hope', 'reference' => 'Acts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(24, $results['kjv']);
    }

    public function testExactPhrase() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search_phrase' => 'free spirit', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);

        $input = ['bible' => 'kjv', 'search_phrase' => 'Lord of Hosts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(235, $results['kjv']);

        $input['whole_words'] = 'yes';
        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(235, $results['kjv']);
    }

    public function testNoneWords() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search_none' => 'faith hope', 'reference' => 'Rom', 'format_structure' => 'raw'];

        $results = $Engine->actionQuery($input);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(432, $results['kjv']);
    }

    public function testLongPassageQuery() {
        $reference = 'Gen 3:36; Exodus 3:36; Leviticus 3:36; Numbers 3:36; Deuteronomy 3:36; Joshua 3:36; Judges 3:36; Ruth 3:36; 1 Samuel 3:36; '
                . '2 Samuel 3:36; '
                . '1 Kings 3:36; 2 Kings 3:36; 1 Chronicles 3:36; 2 Chronicles 3:36; Ezra 3:36; Nehemiah 3:36; Esther 3:36; Job 3:36; Psalms 3:36; '
                . 'Proverbs 3:36; Ecclesiastes 3:36; Song of Solomon 3:36; Isaiah 3:36; Jeremiah 3:36; Lamentations 3:36; Ezekiel 3:36; Daniel 3:36; '
                . 'Hosea 3:36; Joel 3:36; Amos 3:36; Obadiah 3:36; Jonah 3:36; Micah 3:36; Nahum 3:36; Habakkuk 3:36; Zephaniah 3:36; Haggai 3:36; '
                . 'Zechariah 3:36; Malachi 3:36; Matthew 3:36; Mark 3:36; Luke 3:36; John 3:36; Acts 3:36; Romans 3:36; 1 Corinthians 3:36; '
                . '2 Corinthians 3:36; '
                . 'Galatians 3:36; Ephesians 3:36; Philippians 3:36; Colossians 3:36; 1 Thessalonians 3:36; 2 Thessalonians 3:36; 1 Timothy 3:36; '
                . '2 Timothy 3:36;'
                . ' Titus 3:36; Philemon 3:36; Hebrews 3:36; James 3:36; 1 Peter 3:36; 2 Peter 3:36; 1 John 3:36; 2 John 3:36; 3 John 3:36; Jude 3:36; '
                . 'Revelation 3:36';

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $input = ['bible' => 'kjv', 'reference' => $reference, 'format_structure' => 'raw', 'page_all' => TRUE];

        $results = $Engine->actionQuery($input);
        $this->assertTrue($Engine->hasErrors());
        $this->assertCount(61, $Engine->getErrors());
        $this->assertCount(5, $results['kjv']);
    }

    public function testProxRange() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $input = ['bible' => 'kjv', 'search' => 'joy peace', 'search_type' => 'proximity', 'proximity_limit' => 10, 'whole_words' => TRUE];

        $results = $Engine->actionQuery($input);

        $query = "
            SELECT bible_1.id AS id_1, bible_2.id AS id_2

            FROM bss_verses_kjv AS bible_1
            INNER JOIN bss_verses_kjv AS bible_2 ON bible_2.book = bible_1.book
                AND bible_2.id BETWEEN bible_1.id - 10 AND bible_1.id + 10
                AND (bible_2.book != 19 OR bible_2.chapter = bible_1.chapter)
                AND (
                        `bible_2`.`text` REGEXP '[[:<:]]joy[[:>:]]'
                )
            WHERE
            (
                    `bible_1`.`text` REGEXP '[[:<:]]peace[[:>:]]'
            )
        ";

        $query = "SELECT bible_1.id AS id_1, bible_2.id AS id_2 FROM bss_verses_kjv AS bible_1
                    INNER JOIN bss_verses_kjv AS bible_2 ON bible_2.book = bible_1.book AND (bible_2.book != 19 OR bible_2.chapter = bible_1.chapter ) AND bible_2.id BETWEEN bible_1.id - 10 AND bible_1.id + 10 AND (`bible_2`.`text` LIKE '%joy%' AND `bible_2`.`text` REGEXP '[[:<:]]joy[[:>:]]')
                    WHERE (`bible_1`.`text` LIKE '%peace%' AND `bible_1`.`text` REGEXP '[[:<:]]peace[[:>:]]')";

        $this->assertFalse($Engine->hasErrors());
        // $this->assertCount(92, $results['kjv']); // Allows cross-chapter in Psalms
        $this->assertCount(88, $results['kjv']); 
    }
}
