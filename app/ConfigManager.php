<?php

namespace App;

use App\Models\Config;
use App\Models\ConfigValue;
use App\Engine;
use Illuminate\Contracts\Auth\Guard;

/**
 * ConfigManager
 *
 * Component class for managing soft configs
 * both global and user-specific
 */
class ConfigManager 
{

    static function getGlobalConfigs() 
    {
        return self::getConfigs(0);
    }

    static function getUserConfigs(Guard $Guard) 
    {
        // Todo - implement!
        var_dump($Guard->user->user_id);
    }

    static function getConfigs($user_id = 0, $return_models = FALSE) 
    {
        $ConfigValues = ConfigValue::where('user_id', $user_id)->with('config')->get();
        $config_values = [];

        foreach($ConfigValues as $Value) {
            if(!$Value->config) {
                continue;
            }

            $config_values[$Value->config->key] = ($return_models) ? $Value : $Value->value;
        }

        return $config_values;
    }

    static function getConfigItems($is_global = FALSE) 
    {
        $Configs = Config::where('global', ($is_global) ? 1 : 0)->get();
    }

    static function addConfigItem($attributes) 
    {
        $attrs = ['key', 'descr', 'default', 'global', 'type'];

        foreach($attrs as $attr) {
            $$attr = (array_key_exists($attr, $attributes)) ? $attributes[$attr] : NULL;
        }

        if(!$key || !$descr) {
            return; // No key
        }

        $ConfigExists = Config::where('key', $key)->first();

        if($ConfigExists) {
            return; // Config with key found, not adding
        }

        $Config = new Config();
        $Config->key        = $key;
        $Config->descr      = $descr;
        $Config->default    = $default;
        $Config->global     = ($global) ? 1 : 0;
        $Config->type       = $type;
        $Config->save();

        if($global) {
            $ConfigValue = new ConfigValue();
            $ConfigValue->user_id   = 0;
            $ConfigValue->config_id = $Config->id;
            $ConfigValue->value     = $Config->default;
            $ConfigValue->save();
        }
        else {
            // to do - apply default value to all users!
        }
    }

    static function addConfigItems($items) 
    {
        foreach($items as $item) {
            self::addConfigItem($item);
        }
    }

    static function removeConfigItems($items) 
    {
        foreach($items as $item) {
            if(is_array($item)) {
                $key = array_key_exists('key', $item) ? $item['key'] : NULL;
            }
            else {
                $key = $item;
            }

            if($key) {
                self::removeConfigItem($key);
            }
        }
    }

    static function removeConfigItem($key) 
    {
        // Todo
    }

    static function setGlobalConfigs($config_values) 
    {
        self::setConfigs($config_values, 0);
    }

    static function setUserConfigs($config_values) 
    {

    }

    static function setConfigs($config_values, $user_id = 0) 
    {
        $ConfigValues = self::getConfigs($user_id, TRUE);
        $config_values['app.configs_updated_at'] = time();

        foreach($config_values as $key => $value) {
            $key = str_replace('__', '.', $key);

            if(array_key_exists($key, $ConfigValues)) {
                $ConfigValues[$key]->value = $value;
                $ConfigValues[$key]->save();
            }
        }
    }

    static function setConfig($config_name, $config_value, $user_id = 0) 
    {
        static::setConfigs([$config_name => $config_value], $user_id);
    }

    static public function getValueAttribute($value, $type) 
    {
        switch($type) {
            case 'int':
                $val = (int) $value;
                break;

            case 'array':
                $val = json_decode($value, TRUE);
                break;

            case 'object':
                $val = json_decode($value);
                break;

            case 'bool':
                $val = (bool) $value;
                break;

            case 'string':
            default:
                $val = $value;
        }

        return $val;
    }

    static public function setValueAttribute($value, $type) 
    {
        switch($type) {
            case 'int':
                $val = (int) $value;
                break;

            case 'array':
            case 'object':
                $val = json_encode($value);
                break;

            case 'bool':
                $val = ($value) ? 1 : 0;
                break;

            default:
                $val = $value ? trim($value) : null;
        }

        return $val;
    }

    static public function getValueConfig($usr_id = 0, $config = NULL) 
    {
        $ConfigValues = ConfigValue::where('user_id', $user_id)->where('key', $config)->with('config')->first();

        if(!$ConfigValues && !$config) {
            return Engine::triggerInvalidConfigError();
        }

        return ($ConfigValues) ? $ConfigValues->value : NULL;
    }
}
