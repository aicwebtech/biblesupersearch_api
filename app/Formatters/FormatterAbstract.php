<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

use App\Passage;
use App\Search;
/**
 * The formatters format the retured data structure before it is sent to the client
 *
 * @author Computer
 */
abstract class FormatterAbstract {

    protected $results;
    protected $Passages;
    protected $Search;
    protected $is_search;
    protected $languages;

    public function __construct($results, $Passages, $Search, $languages) {
        $this->results      = $results;
        $this->Passages     = $Passages;
        $this->Search       = $Search;
        $this->is_search    = ($Search) ? TRUE : FALSE;
        $this->languages    = $languages;
    }

    abstract public function format();

    protected function _mapResultsToPassages($results) {
        if(!is_array($this->Passages) || !count($this->Passages) || $this->is_search) {
            if(!$this->is_search) {
                return FALSE;
            }

            $Passages = array();

            // We loop through every verse returned for every Bible requested,
            // so none are omitted
            foreach($results as $bible => $verses) {
                foreach($verses as $verse) {
                    $bcv = $verse->book * 1000000 + $verse->chapter * 1000 + $verse->verse;

                    if(empty($Passages[$bcv])) {
                        $Passages[$bcv] = Passage::createFromVerse($verse, $this->languages);
                    }
                }
            }

            ksort($Passages, SORT_NUMERIC);
            $this->Passages = array_values($Passages);
        }

        // We explode chapters only if not a search
        $this->Passages = Passage::explodePassages($this->Passages, TRUE, !$this->is_search);

        foreach($this->Passages as $key => $Passage) {
            if(!$Passage->claimVerses($results)) {
                unset($this->Passages[$key]);
            }
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
