<?php

namespace Tests\Unit\ContentImporters;

use App\ContentImporters\ContentImportAbstract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContentImportAbstractTest extends TestCase
{
    /**
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
     * deprecated in 8.5, which CI runs.
     */
    #[DataProvider('timeLimitDataProvider')]
    public function testExtendTimeLimitOnlyEverRaisesTheLimit(int $before, int $expected): void
    {
        $original = (int) ini_get('max_execution_time');

        try {
            set_time_limit($before);
            (new \ReflectionMethod(ContentImportAbstract::class, 'extendTimeLimit'))->invoke(NULL);

            $this->assertSame($expected, (int) ini_get('max_execution_time'));
        }
        finally {
            set_time_limit($original);
        }
    }

    public static function timeLimitDataProvider(): array
    {
        return [
            'web default is raised'  => [30, ContentImportAbstract::TIME_LIMIT],
            'unlimited is left'      => [0, 0],
            'longer limit is left'   => [ContentImportAbstract::TIME_LIMIT + 300, ContentImportAbstract::TIME_LIMIT + 300],
        ];
    }
}
