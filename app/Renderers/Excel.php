<?php

namespace App\Renderers;

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \App\Helpers;

class Excel extends RenderAbstract 
{
    
    static public $name = 'Excel';
    static public $description = '2007-365 (.xlsx)';

    protected $file_extension = 'xlsx';
    protected $include_book_name = TRUE;
    protected $Spreadsheet;
    protected $rowidx = 6;
    protected $columns = 6;
    
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

        $mem_lim = ini_get('memory_limit');

        // $Cache = \Cache::store('file');
        // // var_dump($Cache instanceof \Psr\SimpleCache\CacheInterface); // true
        // \PhpOffice\PhpSpreadsheet\Settings::setCache($Cache);


        if(Helpers::compareSize('512M', $mem_lim) == 1) {
            $this->columns = 4;
        } else {
            $this->columns = 6;
        }

        switch($this->columns) {
            case 6:
                $header = ['Verse ID', 'Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'];
                $hcells = 'A5:F5';                
                break; 
            case 5:
                $header = ['Verse ID', 'Book Number', 'Chapter', 'Verse', 'Text'];
                $hcells = 'A5:E5';                
                break;
            case 4:
                $header = ['Book Number', 'Chapter', 'Verse', 'Text'];
                $hcells = 'A5:D5';
            default:  
        }

        if(is_file($filepath)) {
            unlink($filepath);
        }

        $this->Spreadsheet = new Spreadsheet();
        $Sheet = $this->Spreadsheet->getActiveSheet();
        $Sheet->setCellValue('A1', $this->Bible->name);
        $Sheet->setCellValue('A3', $this->_getCopyrightStatement(TRUE, '  ') );
        $Sheet->fromArray($header, null, 'A5');
        $Sheet->getStyle('A1')->applyFromArray($this->styleTitle);
        $Sheet->getStyle($hcells)->applyFromArray($this->styleHeader);
        $Sheet->getRowDimension('1')->setRowHeight(20);

        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        switch($this->columns) {
            case 6:
                $row = [$verse->id, $verse->book_name, $verse->book, $verse->chapter, $verse->verse, $verse->text];
                break;
            case 5:
                $row = [$verse->id, $verse->book, $verse->chapter, $verse->verse, $verse->text];
                break;
            case 4:
            default:  
                $row = [$verse->book, $verse->chapter, $verse->verse, $verse->text];
        }

        $this->Spreadsheet->getActiveSheet()->fromArray($row, null, 'A' . $this->rowidx);
        $this->rowidx ++;
    }

    protected function _renderFinish() 
    {
        $Sheet = $this->Spreadsheet->getActiveSheet();

        switch($this->columns) {
            case 6:
                $Sheet->getColumnDimension('B')->setAutoSize(true); // Book name
                $Sheet->getColumnDimension('F')->setAutoSize(true); // Text
                break;
            case 5:
                $Sheet->getColumnDimension('E')->setAutoSize(true); // Text
                break;
            case 4:
            default:  
                $Sheet->getColumnDimension('D')->setAutoSize(true); // Text
        }

        $Writer = new Xlsx($this->Spreadsheet);
        $Writer->save( $this->getRenderFilePath() );
        return TRUE;
    }
}
