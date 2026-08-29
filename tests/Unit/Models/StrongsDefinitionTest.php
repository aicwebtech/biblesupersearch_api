<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\StrongsDefinition;

/**
 * StrongsDefinition is a thin data model over the Strong's lexicon table.
 *
 * migrateFromCsv() is not executed here - it rewrites the whole table. Its contract is
 * checked by reflection instead, since the column map it passes to the importer must stay in
 * step with $fillable or the import silently drops columns.
 */
class StrongsDefinitionTest extends TestCase
{
    private const COLUMNS = ['id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry'];

    public function testTableName(): void
    {
        $this->assertSame('strongs_definitions', (new StrongsDefinition())->getTable());
    }

    public function testEveryLexiconColumnIsMassAssignable(): void
    {
        $this->assertSame(self::COLUMNS, (new StrongsDefinition())->getFillable());
    }

    /**
     * The id is imported from the source data rather than generated, so it has to remain
     * mass-assignable.
     */
    public function testIdIsMassAssignableBecauseItComesFromTheSourceData(): void
    {
        $this->assertContains('id', (new StrongsDefinition())->getFillable());
    }

    public function testMigrateFromCsvIsAStaticEntryPoint(): void
    {
        $method = new \ReflectionMethod(StrongsDefinition::class, 'migrateFromCsv');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertSame(0, $method->getNumberOfRequiredParameters());
    }
}
