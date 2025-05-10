<?php

// namespace Tests\Feature;

// use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;


class ImportSpreadsheetTest extends TestCase {
    protected $quick_mode = TRUE;

    // Note, the full files are removed from the official release to save space
    protected $files_full = [
        'kjv_full.csv'  => 'csv',
        'kjv_full.xlsx' => 'excel',
        'kjv_full.ods'  => 'ods', // DEAD DOG SLOW
    ];    

    protected $files_lite = [
        'kjv_full.csv'  => 'csv',  // No speed difference for CSV
        'kjv_min.xlsx'  => 'excel',
        'kjv_min.ods'   => 'ods',
    ];

    protected $files = [];
    protected $UploadedFiles = [];

    public function __construct() 
    {
        parent::__construct(...func_get_args());

        $this->files = ($this->quick_mode) ? $this->files_lite : $this->files_full;
    }

    /* Start deep / slow tests */

    /**
     *  Test if having every posible field 
     * Use Case: Use has a file containing every possible field we can import
     * They will select all roles, even though several are duplicated
     */
    public function testAllTheFields() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => 'bn c:v',
            'col_C' => 'b c:v',
            'col_D' => 'c:v',
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            
            if($Manager->hasErrors()) {
                print_r($Manager->getErrors());
            }

            $this->assertFalse($Manager->hasErrors());
        }
    }    

    /**
     *  Test with book name, chapter, verse all in one column 
     *  Use Case: User has 2-column file,  full reference in one column, text in another
     */
    public function testCombinedBncv() {
        $data = [
            'first_row_data' => 9,
            'col_A' => NULL,
            'col_B' => 'bn c:v',
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => NULL,
            'col_F' => NULL,
            'col_G' => NULL,
            'col_H' => NULL,
            'col_I' => 't',
        ];

        $Manager = new ImportManager();
        $Manager->test_mode = TRUE;
        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager->checkImportFile($idata);
            
            if($Manager->hasErrors()) {
                print_r($Manager->getErrors());
            }

            $this->assertFalse($Manager->hasErrors());
        }
    }    

    /**
     *  Test with chapter and verse in one column 
     *  Use Case: User has 3 column file containing book name, chapter and verse, and text
     */
    public function testCombinedCv() {
        $data = [
            'first_row_data' => 9,
            'col_A' => NULL,
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => 'c:v',
            'col_E' => 'bn',
            'col_F' => NULL,
            'col_G' => NULL,
            'col_H' => NULL,
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            
            if($Manager->hasErrors()) {
                print_r($Manager->getErrors());
            }

            $this->assertFalse($Manager->hasErrors());

            if($this->quick_mode) {
                break;
            }
        }
    }       

    /**
     *  Test with everything separate
     *  Use Case: User has 4 column file with everything in it's own column: book name, chapter, verse, and text
     */
    public function testSeparateBookName() {
        $data = [
            'first_row_data' => 9,
            'col_A' => NULL,
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => 'bn',
            'col_F' => NULL,
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            
            if($Manager->hasErrors()) {
                print_r($Manager->getErrors());
            }

            $this->assertFalse($Manager->hasErrors());
        }
    }       

    /**
     *  Test with everything separate, but book number provided instead of name
     *  Use Case: User has 4 column file with everything in it's own column: book number, chapter, verse, and text
     */
    public function testSeparateBookNumber() {
        $data = [
            'first_row_data' => 9,
            'col_A' => NULL,
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => NULL,
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            
            if($Manager->hasErrors()) {
                print_r($Manager->getErrors());
            }

            $this->assertFalse($Manager->hasErrors());

            if($this->quick_mode) {
                break;
            }
        }
    }   

    public function testIncorrectFirstRow() {
        $data = [
            'first_row_data' => 2,
            'col_A' => 'id',
            'col_B' => 'bn c:v',
            'col_C' => 'b c:v',
            'col_D' => 'c:v',
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
        }
    }    

    public function testFormula() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => 'bn c:v', // contains formula
            'col_C' => '',
            'col_D' => '',
            'col_E' => '',
            'col_F' => '',
            'col_G' => 't',
        ];

        $idata   = $this->_makeFakeImportTest('kjv_w_formula_min.xlsx', $data);
        $idata['importer'] = 'excel';
        $Manager = new ImportManager();
        $Manager->test_mode = TRUE;
        $Manager->checkImportFile($idata);
        $this->assertTrue($Manager->hasErrors());
        $this->assertContains('Column B contains a formula and cannot be imported. Please deselect this column.', $Manager->getErrors());
    }
    /* End deep / slow tests */

    /* Tests of superficial configs - QUICK */
    public function testMissingFirstRow() {
        $data = [
            'first_row_data' => NULL,
            'col_A' => 'id',
            'col_B' => 'bn c:v',
            'col_C' => 'b c:v',
            'col_D' => 'c:v',
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(1, $Manager->getErrors());
            $this->assertContains('First Row of Verse Data is required', $Manager->getErrors());

            if($this->quick_mode) {
                break;
            }
        }
    }    

    public function testMissingBook() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => 'c:v',
            'col_E' => NULL,
            'col_F' => NULL,
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(1, $Manager->getErrors());
            $this->assertContains('Please specify a column for Book Name or Number', $Manager->getErrors());
        }
    }      

    public function testMissingChapter() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => NULL,
            'col_H' => 'v',
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(1, $Manager->getErrors());
            $this->assertContains('Please specify a column for Chapter', $Manager->getErrors());

            if($this->quick_mode) {
                break;
            }
        }
    }      

    public function testMissingVerse() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => NULL,
            'col_I' => 't',
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(1, $Manager->getErrors());
            $this->assertContains('Please specify a column for Verse', $Manager->getErrors());
        }
    }        

    public function testMissingText() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => 'bn c:v',
            'col_C' => 'b c:v',
            'col_D' => 'c:v',
            'col_E' => 'bn',
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => NULL,
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(1, $Manager->getErrors());
            $this->assertContains('Please specify a column for Text', $Manager->getErrors());
        }
    }       

    public function testMissingAllColumns() {
        $data = [
            'first_row_data' => 9,
            'col_A' => 'id',
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => NULL,
            'col_F' => NULL,
            'col_G' => NULL,
            'col_H' => NULL,
            'col_I' => NULL,
        ];

        foreach($this->files as $file_name => $importer) {
            $idata   = $this->_makeFakeImportTest($file_name, $data);
            $Manager = new ImportManager();
            $Manager->test_mode = TRUE;
            $Manager->checkImportFile($idata);
            $this->assertTrue($Manager->hasErrors());
            $this->assertCount(4, $Manager->getErrors());
            $this->assertContains('Please specify a column for Book Name or Number', $Manager->getErrors());
            $this->assertContains('Please specify a column for Chapter', $Manager->getErrors());
            $this->assertContains('Please specify a column for Verse', $Manager->getErrors());
            $this->assertContains('Please specify a column for Text', $Manager->getErrors());
        }
    }    

    /* End Quick Tests */

    protected function _makeFakeImportTest($file_name, $data) {
        $data['file'] = $this->_generateUploadedFile($file_name);
        $data['importer'] = array_key_exists($file_name, $this->files) ? $this->files[ $file_name ] : NULL;  
        return $data;
    }

    protected function _generateUploadedFile($file_name) {
        // if(!array_key_exists($file_name, $this->UploadedFiles)) {
            $file_path = dirname(__FILE__) . '/test_spreadsheets/' . $file_name;
            return new UploadedFile($file_path, $file_name, NULL, NULL, TRUE);
            $this->UploadedFiles[$file_name] = new UploadedFile($file_path, $file_name, NULL, NULL, TRUE);
        // }

        return $this->UploadedFiles[$file_name];
    }
}
