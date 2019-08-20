<?php

namespace App;

class RenderManager {
    static public $register = [
        'text'      => \App\Renderers\PlainText::class,
        'pdf'       => \App\Renderers\PdfPrintable::class,
    ];

    public function __construct() {

    }

    static public function getRendererList() {
        $list = [];

        foreach(static::$register as $format => $CLASS) {
            $list[$format] = [
                'format' => $format,
                'name'   => $CLASS::$name,
                'desc'   => $CLASS::$description,
            ];
        }

        return $list;
    }
}
