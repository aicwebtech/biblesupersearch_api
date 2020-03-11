<?php

namespace aicwebtech\BibleSuperSearch\Traits;

/**
 * Trait for common input handling
 *
 */
trait Input {
    
    function isTruthy($input_key, $input_array) {
        //var_dump($input_key);
        //var_dump($input_array);
        
        if(!array_key_exists($input_key, $input_array)) {
            //var_dump('no exist');
            return FALSE;
        }
        
        if($input_array[$input_key] === 'false') {
            //var_dump('false string');
            return FALSE;
        }
        if($input_array[$input_key] === FALSE) {
            //var_dump('FALSE');
            return FALSE;
        }
        
        return TRUE;
        
        return (array_key_exists($input_key, $input_array) && $input_array[$input_key] != 'false') ? TRUE : FALSE;
    }
    
    function defaultValue($input_key, $input_array, $default = NULL) {
        return (array_key_exists($input_key, $input_array)) ? $input_array[$input_key] : $default;
    }
}

