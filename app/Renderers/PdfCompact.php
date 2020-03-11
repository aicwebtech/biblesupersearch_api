<?php

namespace aicwebtech\BibleSuperSearch\Renderers;

class PdfCompact extends PdfAbstract {
    static public $name = 'Compact';
    // static public $description = 'Ready to print PDF on 5.5 x 8 pages with two columns.';

    protected $pdf_columns              = 4;        // Number of columns of verses
    protected $pdf_column_width         = 47;       // Width of column, in $this->pdf_unit units.
    protected $pdf_margin               = 8;        // General margin size, in $this->pdf_unit units
    protected $pdf_top_margin           = 10;       // Top margin size, in $this->pdf_unit units
    protected $pdf_text_size            = 8;        // Compact: 9? or less
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
    protected $pdf_verses_paragraph     = 'auto';     // Needs to auto-detect
}
