<?php

namespace App\Importers;

use App\Models\Bible;

/**
 * Description of ImporterAbstract
 *
 * @author Computer
 */
abstract class ImporterAbstract {
    public function __construct() {
        
    }
    
    abstract public function import();
}
