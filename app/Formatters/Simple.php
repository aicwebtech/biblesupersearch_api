<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

use App\Models\Books\BookAbstract;

/**
 * Simple formatter - returns data, with book info, in a simple structure
 * 
 */
class Simple extends FormatterAbstract 
{
    public function format() 
    {
        $language = (is_array($this->languages) && count($this->languages)) ? $this->languages[0] : config('bss.defaults.language_short');
        
        foreach($this->results as $bible => &$verses) {
            foreach($verses as &$verse) {
                $Book = $BookAbstract::findByIdAndLanguage($verse->book, $language);
                $verse->book_name = $Book->name;
                $verse->book_shortname = $Book->shortname;
            }
            unset($verse);
        }
        unset($verses);

        return $this->results;
    }
}

