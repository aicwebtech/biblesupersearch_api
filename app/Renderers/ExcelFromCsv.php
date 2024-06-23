<?php

namespace App\Renderers;

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelFromCsv extends RenderAbstract 
{
    
    static public $name = 'Excel';
    static public $description = '2007-365 (.xlsx)';

    protected $file_extension = 'xlsx';
    protected $include_book_name = TRUE;
    protected $Spreadsheet;
    protected $rowidx = 6;

    protected $Csv = null;
    
    protected $styleTitle = [
        'font' => [
            'bold' => true,
            'size' => 16,
        ],
    ];

    protected $styleHeader = [
        'font' => [
            'bold' => true,
        ],
    ];

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() 
    {
        $filepath = $this->getRenderFilePath(TRUE);
        // $Cache = \Cache::store('database');

        // // var_dump($Cache instanceof \Psr\SimpleCache\CacheInterface); // true
        // \PhpOffice\PhpSpreadsheet\Settings::setCache($Cache);
        
        if(is_file($filepath)) {
            unlink($filepath);
        }

        $this->Csv = new Csv($this->Bible);
        $this->Csv->renderIfNeeded();

        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        // do nothing
    }

    protected function _renderFinish() 
    {
        $Reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Csv");
        $Reader->setReadDataOnly(true);
        $Spreadsheet = $Reader->load($this->Csv->getRenderFilePath());

        // $this->Spreadsheet = new Spreadsheet();
        // $Sheet = $this->Spreadsheet->getActiveSheet();
        // // $Sheet->setCellValue('A1', $this->Bible->name);
        // // $Sheet->setCellValue('A3', $this->_getCopyrightStatement(TRUE, '  ') );
        // // $Sheet->fromArray(['Verse ID','Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'], null, 'A5');
        // // $Sheet->fromArray(['Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'], null, 'A5');
        // // $Sheet->fromArray(['Verse ID','Book Number', 'Chapter', 'Verse', 'Text'], null, 'A5');
        // // $Sheet->fromArray(['Book Number', 'Chapter', 'Verse', 'Text'], null, 'A5');
        // $Sheet->getStyle('A1')->applyFromArray($this->styleTitle);
        // $Sheet->getStyle('A5:F5')->applyFromArray($this->styleHeader);
        // $Sheet->getRowDimension('1')->setRowHeight(20);


        // $Sheet = $this->Spreadsheet->getActiveSheet();
        // $Sheet->getColumnDimension('B')->setAutoSize(true);
        // $Sheet->getColumnDimension('F')->setAutoSize(true);
        $Writer = new Xlsx($Spreadsheet);
        $Writer->save( $this->getRenderFilePath() );
        return TRUE;
    }
}
