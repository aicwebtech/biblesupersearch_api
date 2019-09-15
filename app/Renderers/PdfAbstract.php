<?php

namespace App\Renderers;

use App\Models\Bible;
use App\Models\Language;

abstract class PdfAbstract extends RenderAbstract {
    protected $file_extension = 'pdf';
    protected $include_book_name = TRUE;

    /* PDF-specific settings */
    // protected $tcpdf_class          = \TCPDF::class;
    protected $tcpdf_class          = TCPDFBible::class;
    protected $pdf_orientation      = 'P';
    protected $pdf_format           = 'LETTER';  // For options, see TCPDF_STATIC::$page_formats
    protected $pdf_unit             = 'mm';
    protected $pdf_width            = 8.5;      // Width, in $this->pdf_unit units.  Ignored if $this->pdf_format is specified
    protected $pdf_height           = 11;       // Height, in $this->pdf_unit units.  Ignored if $this->pdf_format is specified
    protected $pdf_columns          = 4;        // Number of columns of verses
    protected $pdf_column_width     = 47;       // Width of column, in $this->pdf_unit units.
    protected $pdf_margin           = 8;        // General margin size, in $this->pdf_unit units
    protected $pdf_top_margin       = 10;        // Top margin size, in $this->pdf_unit units
    protected $pdf_font_family      = 'freeserif'; // Unicode-friendly serif font
    // protected $pdf_font_family      = 'times';
    protected $pdf_text_size        = 10;
    protected $pdf_title_size       = 16;
    protected $pdf_header_size      = 8;
    protected $pdf_header_style     = 'B';    
    protected $pdf_book_size        = 12;
    protected $pdf_book_style       = 'B';    
    protected $pdf_book_align       = 'C';
    protected $pdf_chapter_size     = 10;
    protected $pdf_chapter_style    = 'B';
    protected $pdf_chapter_align    = 'C';
    protected $pdf_text_align       = 'J';
    protected $pdf_verses_paragraph = 'auto';     // Needs to auto-detect

    protected $pdf_language_overrides = [
        'ar' => [
            // 'pdf_font_family'   => 'aefurat', // do not use
            'pdf_column_width'  => 45,
        ],
        'zh' => [
            'pdf_font_family' => 'msungstdlight',
        ]
    ];

    /* END PDF-specific settings */

    protected $text_pending = ''; 
    protected $TCPDF;

    protected $last_render_book    = NULL;
    protected $last_render_chapter = NULL;

    public function __construct($module) {
        parent::__construct($module);

        $this->_applyPdfLanguageOverride();

        $format = $this->pdf_format ?: [$this->pdf_width, $this->pdf_height];
        $this->TCPDF  = new $this->tcpdf_class($this->pdf_orientation, $this->pdf_unit, $format);
        $this->TCPDF->setHeaderMargin(10);
        $this->TCPDF->setFooterMargin(0);
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_text_size);
        $this->TCPDF->setHeaderFont([$this->pdf_font_family, $this->pdf_header_style, $this->pdf_header_size]);
        $this->TCPDF->setFooterFont([$this->pdf_font_family, $this->pdf_header_style, $this->pdf_header_size]);
        $this->TCPDF->SetAutoPageBreak(TRUE, $this->pdf_margin);
        $this->TCPDF->SetMargins($this->pdf_margin, $this->pdf_top_margin);
    }

    protected function _renderStart() {
        // todo: Set RTL based on Bible language
        if(Language::isRtl($this->Bible->lang_short)) {
            $this->TCPDF->setRTL(TRUE);
        }

        $this->TCPDF->setTitle($this->Bible->name);

        $h       = $this->TCPDF->getPageHeight();
        $margins = $this->TCPDF->getMargins();

        $h -= $margins['top'] + $margins['bottom'] + $margins['header'] + $margins['footer'];

        $title_height = $h / 5;
        $title_pos = $h / 2 - $title_height;

        // var_dump($h);
        // var_dump($title_height);
        // var_dump($title_pos);
        // die();

        $this->TCPDF->addPage();
        $this->TCPDF->setFontSize($this->pdf_title_size);
        $this->TCPDF->setY($title_pos);
        // // todo - translate this!
        // // $this->TCPDF->MultiCell(0, $title_height, 'The Holy Bible',   0, 'C', FALSE, 1, '', '4');
        // // $this->TCPDF->MultiCell(0, $title_height, $this->Bible->name, 0, 'C', FALSE, 1, '', '7');        
        $this->TCPDF->Cell(0, $title_height, strtoupper('The Holy Bible'),   0, 1, 'C');
        $this->TCPDF->Cell(0, $title_height, strtoupper($this->Bible->name), 0, 1, 'C');
        $this->TCPDF->ln();
        $this->TCPDF->addPage();
        $this->TCPDF->addPage();
        $this->TCPDF->setY(0);
        $this->TCPDF->setFontSize($this->pdf_text_size);
        // $this->TCPDF->writeHTML( $this->_getCopyrightStatement() );
        $this->TCPDF->Cell(0, 20, $this->Bible->name, 0, 1, 'L');
        // $this->TCPDF->ln();
        $this->TCPDF->writeHTMLCell(0, 40, $this->pdf_margin, $this->TCPDF->getY(), $this->_getCopyrightStatement() );
        $this->TCPDF->addPage();
        $this->TCPDF->addPage(); // TOC
        $this->TCPDF->addPage(); 
        // $this->TCPDF->addPage();

        if($this->pdf_columns > 1) {
            $this->TCPDF->setEqualColumns($this->pdf_columns, $this->pdf_column_width);
        }

        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        if($verse->id > 200) {
            return;
        }

        if($this->pdf_verses_paragraph === 'auto') {
            $this->pdf_verses_paragraph = (strpos($verse->text, '¶') !== FALSE) ? TRUE : FALSE;
        }

        $this->TCPDF->current_book      = $verse->book_name;
        $this->TCPDF->current_chapter   = $verse->chapter;

        if($verse->book != $this->last_render_book) {
            $this->_renderNewBook($verse->book, $verse->book_name, $verse->chapter);
        }
        else if($verse->chapter != $this->last_render_chapter) {
            $this->_renderNewChapter($verse->chapter);
        }

        $text = trim($verse->text);
        $text = str_replace('¶', '', $text);
        $text = $verse->verse . ' ' . $text;

        if(!$this->pdf_verses_paragraph) {
            // Verse format
            $this->TCPDF->Write(0, $text, '', FALSE, $this->pdf_text_align);
            $this->TCPDF->Ln();            
        }
        else {
            // Paragraph format (preferred)

            if (strpos($verse->text, '¶') !== FALSE) {
                if($this->text_pending) {
                    $this->TCPDF->Write(0, $this->text_pending, '', FALSE, $this->pdf_text_align);
                    $this->TCPDF->Ln();   
                }

                $this->text_pending = '';
            }

            if(!$this->text_pending) {
                $this->text_pending .= '      ';
            }

            $this->text_pending .= $text . '  ';

        }

        $this->last_render_book         = $verse->book;
        $this->last_render_chapter      = $verse->chapter;
    }

    protected function _renderFinish() {
        if($this->text_pending) {
            $this->TCPDF->Write(0, $this->text_pending, '', FALSE, $this->pdf_text_align);
            $this->TCPDF->Ln();   
        }

        $this->TCPDF->endPage();
        $this->TCPDF->setEqualColumns(0);
        $this->TCPDF->current_book    = NULL;
        $this->TCPDF->current_chapter = NULL;

        // add a new page for TOC
        $this->TCPDF->addTOCPage();

        // write the TOC title
        $this->TCPDF->SetFont($this->pdf_font_family, '', $this->pdf_title_size);
        $this->TCPDF->MultiCell(0, 0, 'Table Of Contents', 0, 'C', 0, 1, '', '', true, 0);
        $this->TCPDF->Ln();

        $this->TCPDF->setEqualColumns(3);
        $this->TCPDF->SetFont($this->pdf_font_family, '', $this->pdf_title_size);

        $this->TCPDF->addTOC(5, 'courier', '.', 'Table of Contents', '');

        $this->TCPDF->setEqualColumns(0);
        // end of TOC page
        $this->TCPDF->endTOCPage();

        $filepath = $this->getRenderFilePath(TRUE);
        $this->TCPDF->Output($filepath, 'F');

        return TRUE;
    }

    protected function _renderNewBook($book, $book_name, $chapter = 1) {
        // if($book == 1) {
        //     $this->TCPDF->addPage();

        //     if($this->pdf_columns > 1) {
        //         $this->TCPDF->setEqualColumns($this->pdf_columns);
        //     }
        // }

        if($book == 1) {
            $this->TCPDF->Bookmark('Old Testament');
        }

        if($book == 40) {
            $this->TCPDF->Bookmark('New Testament');
        }

        $this->TCPDF->Bookmark($book_name, 1);
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_book_style, $this->pdf_book_size);
        $this->TCPDF->Ln();
        $this->TCPDF->Write(0, strtoupper($book_name), '', FALSE, $this->pdf_book_align);
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
        $this->_renderNewChapter($chapter);
    }

    protected function _renderNewChapter($chapter) {

        // todo - translate this!
        $chapter_name = 'Chapter ' . $chapter;
        $this->TCPDF->Bookmark($chapter_name, 2);
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_chapter_style, $this->pdf_chapter_size);
        $this->TCPDF->Ln();
        $this->TCPDF->Write(0, $chapter_name, '', FALSE, $this->pdf_chapter_align);
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_text_size);
    }

    protected function _applyPdfLanguageOverride() {
        if(array_key_exists($this->Bible->lang_short, $this->pdf_language_overrides) && is_array($this->pdf_language_overrides[ $this->Bible->lang_short ])) {
            foreach($this->pdf_language_overrides[ $this->Bible->lang_short ] as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}
