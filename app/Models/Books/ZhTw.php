<?php

namespace App\Models\Books;

use Illuminate\Database\Eloquent\Model;

// Traditional Chinese : zh_TW

class ZhTw extends BookAbstract
{
    protected $language = 'zh_TW';
    protected $table = 'books_zh_tw';
}
