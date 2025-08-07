<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;

use App\Models\IpAccess;
use PHPUnit\Framework\Attributes\DataProvider;

class IpAccessTest extends TestCase
{
    protected $default_limit;
    protected $config_cache;
    protected $config_value = 0;
    protected $config_changed = false;

    #[DataProvider('hostParsingDataProvider')]
    public function testHostParsing(string $url, string|null $expected) 
    {
        $domain = IpAccess::parseDomain($url);
        $this->assertEquals($expected, $domain);
    }

    public static function hostParsingDataProvider()
    {
        return [
            ['https://www.example.com/bible-search', 'example.com'],
            ['http://example.com/bible-search', 'example.com'],
            ['https://bible.example.com', 'bible.example.com'],
            ['https://bible.example.com/', 'bible.example.com'],
            ['http://search.bible.example.com/page/1', 'search.bible.example.com'],
            ['https://example.com/bible/?biblesupersearch_ingerface=Classic', 'example.com'],
            ['https://example.com/bible/search.html', 'example.com'],
            ['bib.example.com', 'bib.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['example.org/grace-and-truth-came-through-jesus-christ-john-117/?fbclid=IwAR2bxtJmK9JKbhBY-Pznbf4NCGOjpsQ0ju6g05lXX6_XWIPj7h95tF4', 'example.org'],
            ['bib.example.com:7070/bible-tool/?customize_changeset_uuid=660a376f-ab87-41b1-b77a-489cae0a41d0', 'bib.example.com'],
            ['localhost', NULL],
            ['localhost:3333', NULL],
            ['http://top.prod.example.com/#/c/60fzq9yftx', 'top.prod.example.com'],
            ['http://top.prod.example.com#stuff', 'top.prod.example.com'],
            ['m.example.com/from=1000539d/s?word=supersearch%E5%96%B5%E5%96%B5%E5%96%B5&sa=ts_1&ts=6361105&t_kt=0&ie=utf-8&rsv_t=a6a1yI%252FfKJzP3GLcp13GTpYd5YT2WEGUGGcPoDu6ZHXMAE2pj5MMuUTitCaHKns&rsv_pq=10848949574623648659&ss=100&tj=1&rq=supersearch&rqlang=zh&rsv_sug4=', 'm.example.com'],
        ];
    }

}

