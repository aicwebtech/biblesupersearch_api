<?php

namespace aicwebtech\BibleSuperSearch\Models;

use Illuminate\Database\Eloquent\Model;
use aicwebtech\BibleSuperSearch\ConfigManager;

// Please use ConfigManager to create and save config values

class ConfigValue extends Model {

    public $timestamps = FALSE;

    public function config() {
        return $this->belongsTo('aicwebtech\BibleSuperSearch\Models\Config');
    }

    public function getValueAttribute($value) {
        return ConfigManager::getValueAttribute($value, $this->config->type);
    }

    public function setValueAttribute($value) {
        $this->attributes['value'] = ConfigManager::setValueAttribute($value, $this->config->type);
    }
}
