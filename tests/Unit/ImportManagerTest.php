<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\ImportManager;

class ImportManagerTest extends TestCase
{
    public function testGetImportersListReturnsNonEmptyArray(): void
    {
        $list = ImportManager::getImportersList();
        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
    }

    public function testGetImportersListItemsHaveRequiredKeys(): void
    {
        $list = ImportManager::getImportersList();

        foreach ($list as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('desc', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('ext', $item);
            $this->assertArrayNotHasKey('class', $item, 'class must not be exposed in importer list');
        }
    }

    public function testGetImportersListJoinsArrayDescriptions(): void
    {
        $list = ImportManager::getImportersList();

        foreach ($list as $item) {
            $this->assertIsString($item['desc'], 'desc must be a string after processing');
        }
    }

    public function testGetImportersListIncludesKnownTypes(): void
    {
        $list  = ImportManager::getImportersList();
        $types = array_column($list, 'type');

        $this->assertContains('csv', $types);
        $this->assertContains('biblesupersearch', $types);
        $this->assertContains('usfm', $types);
    }

    public function testSetTypeWithValidType(): void
    {
        $mgr = new ImportManager();
        $result = $mgr->setType('csv');

        $this->assertTrue($result);
        $this->assertSame('csv', $mgr->type);
        $this->assertNotNull($mgr->import_class);
    }

    public function testSetTypeWithInvalidTypeReturnsFalse(): void
    {
        $mgr = new ImportManager();
        $result = $mgr->setType('nonexistent_format');

        $this->assertFalse($result);
        $this->assertTrue($mgr->hasErrors());
    }

    public function testSetTypeWithEmptyStringClearsType(): void
    {
        $mgr = new ImportManager();
        $mgr->setType('csv');
        $result = $mgr->setType('');

        $this->assertTrue($result);
        $this->assertNull($mgr->type);
        $this->assertNull($mgr->import_class);
    }
}
