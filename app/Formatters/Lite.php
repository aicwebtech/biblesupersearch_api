<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

/**
 * Lite passage format
 *
 * @author Computer
 */
class Lite extends FormatterAbstract {

    public function format() {
        $results  = $this->_preFormatVerses($this->results);
        $passages = [];

        if(!$this->_mapResultsToPassages($results)) {
            $this->Passages = [];
            // Do something??
        }

        foreach($this->Passages as $Passage) {
            $passages[] = $Passage->toArray(FALSE);
        }

        return $passages;
    }
}
