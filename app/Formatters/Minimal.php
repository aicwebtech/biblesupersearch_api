<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace aicwebtech\BibleSuperSearch\Formatters;

/**
 * Minimal formatter - returns minimal data in a simple structure
 * 
 */
class Minimal extends FormatterAbstract {
    public function format() {
        return $this->_preFormatVerses($this->results);
    }
}

