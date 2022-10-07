<?php

namespace App\Models\Books;

use Illuminate\Database\Eloquent\Model;

// Simplified Chinese : zh_CN

class ZhCn extends BookAbstract
{
    protected $language = 'zh_CN';
    protected $table = 'books_zh_cn';
}
