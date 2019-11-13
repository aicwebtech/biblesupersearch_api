<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Copyright extends Model {

    public function bible() {
        return $this->belongsTo('App\Models\Bible');
    }

    public function getProcessedCopyrightStatement() {
        $cr = $this->default_copyright_statement;

        if($this->type == 'creative_commons') {
            $cr = 'This Bible is made available under the terms of the Creative Commons ';
            $cr .= $this->name;
            $cr .= "license. &nbsp; The terms of this license can be found <a href='{$this->url}'>here</a>";
            $cr .= "&nbsp; This work has been adapeted from it's original format to work with Bible SuperSearch.";
        }
        elseif($this->url) {
            $cr .= " &nbsp; The terms of this license can be found <a href='{$this->url}'>here</a>";
        }

        return $cr;
    }
}
