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
    protected $PassagesOrig;
    protected $Search;
    protected $is_search;
    protected $has_passages;
    protected $languages;
    protected $input;

    public function __construct($results, $Passages, $Search, $languages, $input) {
        $this->results      = $results;
        $this->Passages     = $Passages;
        $this->PassagesOrig = $Passages;
        $this->Search       = $Search;
        $this->is_search    = (bool) $Search;
        $this->languages    = $languages;
        $this->input        = $input;
        $this->has_passages = is_array($this->Passages) && count($this->Passages);
    }

    abstract public function format();

    protected function _mapResultsToPassages($results) {
        $passage_group_search = false;

        if($this->input['group_passage_search_results'] && $this->is_search && $this->has_passages) {
            $passage_group_search = true;
        }

        if(!($passage_group_search) && (!$this->has_passages || $this->is_search)) {
            
            if(!$this->is_search) {
                return FALSE;
            }

            $Passages = [];

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
        $explode_chapters = !$this->is_search || $passage_group_search;

        $this->Passages = Passage::explodePassages($this->Passages, true, $explode_chapters);

        foreach($this->Passages as $key => $Passage) {
            if(!$Passage->claimVerses($results)) {
                unset($this->Passages[$key]);
            }
        }

        if($passage_group_search) {
            $this->Passages = Passage::explodePassagesByChapters($this->Passages);
        }

        return TRUE;
    }

    protected function _createPassageFromSingleVerse($verse) {

    }

    protected function _preFormatVerses($results) {
        return $results; //

        // foreach($results as $key => &$verse) {

        // }
        // unset($value);

        // return $results;
    }

    protected function _preFormatVersesHelper(&$verse) {

    }
}
