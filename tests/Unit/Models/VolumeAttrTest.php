<?php

namespace Tests\Unit\Models;

use App\Models\VolumeAttr;
use App\Models\VolumeTypes\Strongs;
use PHPUnit\Framework\TestCase;

/**
 * volumes_attr: a volume setting attached to another record.  Plain-object checks only; the
 * helpers that query are exercised by BibleStrongsDictionaryTest and StrongsApiTest.
 */
class VolumeAttrTest extends TestCase
{
    public function testTableHasNoTimestamps(): void
    {
        $Attr = new VolumeAttr();

        $this->assertSame('volumes_attr', $Attr->getTable());
        $this->assertFalse($Attr->usesTimestamps());
    }

    public function testFillable(): void
    {
        $Attr = new VolumeAttr(['module' => 'kjv', 'type' => VolumeAttr::TYPE_BIBLE, 'attribute' => Strongs::BIBLE_ATTR, 'value' => 'en_orig']);

        $this->assertSame(['module', 'type', 'attribute', 'value'], $Attr->getFillable());
        $this->assertSame('kjv', $Attr->module);
        $this->assertSame('bible', $Attr->type);
        $this->assertSame('default_strongs_dictionary', $Attr->attribute);
        $this->assertSame('en_orig', $Attr->value);
    }
}
