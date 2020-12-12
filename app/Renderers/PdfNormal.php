<?php

namespace App\Renderers;

class PdfNormal extends PdfAbstract {
    static public $name = 'Normal Text';
    // static public $description = 'Ready to print PDF on 5.5 x 8 pages with two columns.';

    protected $pdf_columns              = 2;        // Number of columns of verses
    protected $pdf_column_width         = 94;       // Width of column, in $this->pdf_unit units.
    protected $pdf_margin               = 8;        // General margin size, in $this->pdf_unit units
    protected $pdf_top_margin           = 10;       // Top margin size, in $this->pdf_unit units
    protected $pdf_text_size            = 10;        // Compact: 9? or less
    protected $pdf_header_size          = 8;
    protected $pdf_header_style         = 'B';    
    protected $pdf_book_size            = 12;
    protected $pdf_book_style           = 'B';    
    protected $pdf_book_align           = 'C';
    protected $pdf_chapter_size         = 10;
    protected $pdf_chapter_style        = 'B';
    protected $pdf_chapter_align        = 'C';
    protected $pdf_text_align           = 'J';
    protected $pdf_brackets_to_italics  = TRUE;
    protected $pdf_verses_paragraph     = FALSE; 
    protected $pdf_break_new_testament  = 'page';
}
