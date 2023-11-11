<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

/**
 * Description of Passage
 *
 * @author Computer
 */
class Passage extends FormatterAbstract {

    public function format() {
        $results  = $this->_preFormatVerses($this->results);
        $passages = [];

        if(!$this->_mapResultsToPassages($results)) {
            $this->Passages = [];
        }

        foreach($this->Passages as $Passage) {
            $passages[] = $Passage->toArray(TRUE);
        }

        return $passages;
    }
}
