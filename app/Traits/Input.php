<?php

namespace App\Traits;

/**
 * Trait for common input handling
 *
 */
trait Input {
    
    function isTruthy($input_key, $input_array) {
        if(!array_key_exists($input_key, $input_array)) {
            return FALSE;
        }
        
        if($input_array[$input_key] === 'false') {
            return FALSE;
        }
        if($input_array[$input_key] === FALSE) {
            return FALSE;
        }
        
        return TRUE;
        
        return (array_key_exists($input_key, $input_array) && $input_array[$input_key] != 'false');
    }
    
    function defaultValue($input_key, $input_array, $default = NULL) {
        return (array_key_exists($input_key, $input_array)) ? $input_array[$input_key] : $default;
    }
}

