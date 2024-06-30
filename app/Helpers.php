<?php

namespace App;

class Helpers {

    /*
     * Sorts an array of strings by string length
     */
    public static function sortStringsByLength(&$array, $dir = 'DESC') 
    {
        return usort($array, function($a, $b) use ($dir) {
            $comp = strlen($a) <=> strlen($b);
            $comp = ($dir == 'DESC') ? $comp * -1 : $comp;
            return $comp;
        });
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function isPremium() 
    {
        if(config('app.premium_disabled')) {
            return FALSE;
        }

        return static::premiumCodePresent();
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function premiumCodePresent() 
    {

        // List of classes with known premium versions
        $classes = [
            'Engine',
        ];

        foreach($classes as $basename) {
            $class_name = 'App\Premium\\' . $basename;

            if(!class_exists($class_name)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function make($class_name) 
    {
        $new_class_name = static::find($class_name);
        return new $new_class_name();
    }

    public static function find($class_name) 
    {
        $new_class_name = static::transformClassName($class_name);

        if(class_exists($new_class_name)) {
            return $new_class_name;
        }
        else if(class_exists($class_name)) {
            return $class_name;
        }

        return FALSE;
    }

    public static function transformClassName($class_name) 
    {
        $imp = \App\InstallManager::getImportableDir()[2];
        $class_name_imp = str_replace("App\\", "App\\" . $imp . "\\", $class_name);
        
        if(class_exists($class_name_imp)) {
            return $class_name_imp;
        }

        return config('app.premium') ? str_replace("App\\", "App\Premium\\", $class_name) : $class_name;
    }

    public static function ordinal($number) 
    {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number. 'th';
        }
        else {
            return $number. $ends[$number % 10];
        }
    }

    public static function isCommonWord($word, $lang) 
    {
        $common_en = ['a', 'and', 'the', 'or', 'but'];

        if($lang == 'en' && in_array($word, $common_en)) {
            return TRUE;
        }

        return FALSE;
    }

    public static function maxUploadSize($format = TRUE) 
    {
        $max = \Illuminate\Http\UploadedFile::getMaxFilesize();
        $max_fmt = NULL;

        if(!$format) {
            return $max;
        }

        $map = [
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'k' => 1024
        ];

        foreach($map as $k => $v) {
            if($max >= $v) {
                $max_fmt = $max / $v;
                $max_fmt .=  $k;
                break;
            }
        }

        $max_fmt = $max_fmt ?: $max;
        return ($format === 'both') ? ['raw' => $max, 'fmt' => $max_fmt] : $max_fmt;
    }

    public static function sizeStringToInt($size_str)
    {
        $size_int = (int)$size_str;

        if(is_string($size_str)) {
            $char = substr($size_str, -1);

            $size_int = match($char) {
                'G' => $size_int * 1024 ** 3,
                'M' => $size_int * 1024 ** 2,
                'k' => $size_int * 1024,
                default => $size_int,
            };
        }

        return $size_int;
    }

    public static function compareSize($size1, $size2) 
    {
        return static::sizeStringToInt($size1) <=> static::sizeStringToInt($size2);
    }

    public static function isAuthorized($access_level) 
    {

    }

    public static function trimRequest($request)
    {
        $request = trim($request);
        $request = trim($request, ';,');
        $request = trim($request);
        return $request;
    }

    /**
     * Builds Laravel query from jqgrid search data
     * 
     * @param $data response data
     * @param $Query Laravel query builder
     */
    public static function buildGridSearchQuery(&$data, \Illuminate\Database\Eloquent\Builder &$Query, $field_map = []) 
    {
        $val = $data['searchString'];
        $op  = NULL;
        $data['_post_filters'] = [];

        if(array_key_exists('filters', $data) && $data['filters']) {
            return self::buildGridSearchMuiltiQuery($data, $Query, $field_map);
        }

        list($op, $val, $special) = self::_mapSearchOperator($data['searchOper'], $data['searchString']);

        if($op && $data['searchString'] != '_no_rest_') {
            $field = (array_key_exists($data['searchField'], $field_map) && $field_map[$data['searchField']]) ? $field_map[$data['searchField']] : $data['searchField']; 

            if($field == 'POSTFILTER') {
                $data['_post_filters'][ $data['searchField'] ] = $val;
            }
            else {
                $Query->where($field, $op, $val);
            }
        }
    }

    public static function buildGridSearchMuiltiQuery(&$data, \Illuminate\Database\Eloquent\Builder &$Query, $field_map = []) 
    {
        if(!array_key_exists('filters', $data) || !$data['filters']) {
            return;
        }

        $filters = json_decode($data['filters']);
        $mapped  = [];
        $data['_post_filters'] = [];

        foreach($filters->rules as $rule) {
            list($op, $val, $special) = self::_mapSearchOperator($rule->op, $rule->data);

            if($op && $rule->data != '_no_rest_') {            
                $field = (array_key_exists($rule->field, $field_map) && $field_map[$rule->field]) ? $field_map[$rule->field] : $rule->field; 

                if($field == 'POSTFILTER') {
                    $data['_post_filters'][ $rule->field ] = $val;
                }
                else {                
                    $mapped[] = [
                        'field' => $field,
                        'op'    => $op,
                        'val'   => $val,
                        'sp'    => $special,
                    ];
                }
            }
        }

        if($filters->groupOp == 'AND') {
            $Query->where(function($q) use ($mapped) {
                foreach($mapped as $m) {
                    $q->where($m['field'], $m['op'], $m['val']);
                }
            });
        }        

        // Known issue: When using a postfilter, the postfilter will always be treated as AND
        if($filters->groupOp == 'OR') {
            $Query->where(function($q) use ($mapped) {
                foreach($mapped as $key => $m) {
                    if($key == 0) {
                        $q->where($m['field'], $m['op'], $m['val']);
                    }
                    else {
                        $q->orWhere($m['field'], $m['op'], $m['val']);
                    }
                }
            });
        }
    }

    protected static function _mapSearchOperator($search_op, $val) 
    {
        $op = NULL;
        $special = NULL;

        switch($search_op) {
            case 'eq':
                $op = '=';
                break;             
            case 'ne':
                $op = '!=';
                break;           
            case 'lt':
                $op = '<';
                break;             
            case 'le':
                $op = '<=';
                break;
            case 'gt':
                $op = '>';
                break;             
            case 'ge':
                $op = '>=';
                break; 
            case 'bw':
                $op = 'LIKE';
                $val .= '%';
                break;             
            case 'bn':
                $op = 'NOT LIKE';
                $val .= '%';
                break;            
            case 'ew':
                $op = 'LIKE';
                $val = '%' . $val;
                break;             
            case 'en':
                $op = 'NOT LIKE';
                $val = '%' . $val;
                break;             
            case 'cn':
                $op = 'LIKE';
                $val = '%' . $val . '%';
                break;             
            case 'nc':
                $op = 'NOT LIKE';
                $val = '%' . $val . '%';
                break; 
        }

        return [$op, $val, $special];
    }

}
