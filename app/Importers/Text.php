<?php

namespace App\Importers;
use App\Models\Bible;

class Text extends ImporterAbstract 
{
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected function _importHelper(Bible &$Bible): bool  
    {
        ini_set("memory_limit", "500M");

        // Script settings
        $dir  = dirname(__FILE__) . '/../../bibles/misc/'; // directory of Bible files
        $file   = 'TEXT-PCE.txt';
        $path   = $dir . $file;
        $module = $this->module;
        $Bible    = $this->_getBible($module);

        // Open the file
        $handle = fopen($path, "r");

        if(!$handle) {
            return $this->addError('Could not open file: ' . $path, 4);
        }

        $this->saveBible();

        $line_number = 0;
        $book = 0;
        $book_name = null;

        // Example parsing logic (to be replaced with actual logic)
        while(($line = fgets($handle)) !== false) {
            $line_number++;

            // This parsing logic is specific to the KJV PCE format
            // Need to support other formats in the future
            $parts = explode(' ', $line, 3);

            if($parts[0] != $book_name) {
                $book_name = $parts[0];
                $book ++;
            }

            list($chapter, $verse) = explode(':', $parts[1], 2);
            $text = $parts[2] ?? '';
            
            $this->_addVerse($book, (int)$chapter, (int)$verse, $text);
        }

        fclose($handle);

        $this->_insertVerses();
        return true;
    }

    public function checkUploadedFile(\Illuminate\Http\UploadedFile $File): bool  
    {
        return $this->addError('Not implemented for RVG importer', 4);
    }
}
