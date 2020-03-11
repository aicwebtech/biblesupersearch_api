<?php

namespace aicwebtech\BibleSuperSearch\Models;

use Illuminate\Database\Eloquent\Model;

// Please use ConfigManager to create config items

class Config extends Model {
    public $timestamps = FALSE;

    public function getDefaultAttribute($value) {
        return \aicwebtech\BibleSuperSearch\ConfigManager::getValueAttribute($value, $this->type);
    }

    public function setDefaultAttribute($value) {
        $this->attributes['default'] = \aicwebtech\BibleSuperSearch\ConfigManager::setValueAttribute($value, $this->type);
    }
}
