<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

/**
 * The formatters format the retured data structure before it is sent to the client
 *
 * @author Computer
 */
abstract class FormatterAbstract {
    
    protected $results;
    protected $Passages;
    
    public function __construct($results, $Passages) {
        $this->results  = $results;
        $this->Passages = $Passages;
        //var_dump(count($Passages));
    }
    
    abstract public function format();
    
    protected function _mapResultsToPassages($results) {
        if(!is_array($this->Passages) || !count($this->Passages)) {
            return FALSE;
        }
        
        $ct = count($results);
        
        foreach($this->Passages as $Passage) {
            $Passage->claimVerses($results);
        }
        
        $cta = count($results);
        
        if($ct == $cta) {
            echo('no verses claimed');
        }
        else if($cta) {
            echo('some verses not claimed');
        }
        
        return TRUE;
    }
    
    protected function _createPassageFromSingleVerse($verse) {
        
    }
    
    protected function _preFormatVerses($results) {
        foreach($results as $key => &$verse) {
            
        }
        unset($value);
        
        return $results;
    }
    
    protected function _preFormatVersesHelper(&$verse) {
        
    }
}
