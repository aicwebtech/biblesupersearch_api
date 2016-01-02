<?php

namespace App\Models\Books;

use Illuminate\Database\Eloquent\Model;

class Abs extends Model
{
    protected $language;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) {
        if(empty($this->language)) {            
            $class = explode('\\', get_called_class());
            $this->language = strtolower(array_pop($class));
        }

        if(empty($this->table)) {
            $this->table = 'books_' . $this->language;
        }

        parent::__construct($attributes);
    }

    public static function getClassNameByLanguage($language) {
        $class_name = 'App\Models\Books\\' . ucfirst($language);
        return $class_name; 
    }
}
