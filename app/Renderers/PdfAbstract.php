<?php

namespace App\Renderers;

use App\Models\Bible;

abstract class PdfAbstract extends RenderAbstract {
    protected $file_extension = 'pdf';
    protected $include_book_name = TRUE;

    /* PDF-specific settings */
    protected $pdf_orientation      = 'P';
    protected $pdf_width            = 8.5;
    protected $pdf_height           = 11;
    protected $pdf_columns          = 4; // Number of columns of verses
    protected $pdf_book_align       = 'C';
    protected $pdf_chapter_align    = 'C';
    protected $pdf_text_align       = 'J';
    protected $pdf_verses_paragraph = FALSE; // Not working with TCPDF::write - find another method?

    protected $TCPDF;

    protected $last_render_book    = NULL;
    protected $last_render_chapter = NULL;

    public function __construct($module) {
        parent::__construct($module);

        $format = [$this->pdf_width, $this->pdf_height];
        $this->TCPDF  = new \TCPDF($this->pdf_orientation, 'in', $format);
    }

    protected function _renderStart() {
        // todo: Set RTL based on Bible language
        // $this->TCPDF->setRTL();

        $this->TCPDF->setTitle($this->Bible->name);

        $this->TCPDF->addPage();
        // todo - translate this!
        $this->TCPDF->MultiCell(0, .5, 'The Holy Bible',   0, 'C', FALSE, 1, '', '4');
        $this->TCPDF->MultiCell(0, .5, $this->Bible->name, 0, 'C', FALSE, 1, '', '7');
        $this->TCPDF->addPage();
        $this->TCPDF->addPage();
        $this->TCPDF->writeHTML( $this->_getCopyrightStatement() );
        $this->TCPDF->addPage();
        $this->TCPDF->addPage();

        if($this->pdf_columns > 1) {
            $this->TCPDF->setEqualColumns($this->pdf_columns);
        }

        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        if($verse->book > 2) {
            // return;
        }

        if($verse->book != $this->last_render_book) {
            $this->_renderNewBook($verse->book_name, $verse->chapter);
        }
        else if($verse->chapter != $this->last_render_chapter) {
            $this->_renderNewChapter($verse->chapter);
        }

        $text = $verse->verse . ' ' . $verse->text;

        if(!$this->pdf_verses_paragraph) {
            $this->TCPDF->Write(0, $text, '', FALSE, $this->pdf_text_align);
            $this->TCPDF->Ln();            
        }
        else {
            $this->TCPDF->Write(0, $text . 'bacon', '', FALSE, $this->pdf_text_align, TRUE);

            if (strpos($verse->text, '¶') !== FALSE) {
                // $this->TCPDF->Write(0, '  ', '', FALSE, $this->pdf_text_align);
                // $this->TCPDF->Ln();
                // $this->TCPDF->Write(0, $text . 'bacon', '', FALSE, $this->pdf_text_align);
            }
            else {
                // die('here');
                // $this->TCPDF->Write(0, $text . 'ham', '', FALSE, $this->pdf_text_align);
            }
        }


        $this->last_render_book    = $verse->book;
        $this->last_render_chapter = $verse->chapter;
    }

    protected function _renderFinish() {
        $this->TCPDF->setEqualColumns(0);

        $filepath = $this->getRenderFilePath(TRUE);
        $this->TCPDF->Output($filepath, 'F');

        return TRUE;
    }

    protected function _renderNewBook($book_name, $chapter = 1) {
        $this->TCPDF->Ln();
        $this->TCPDF->Write(0, $book_name, '', FALSE, $this->pdf_book_align);
        $this->TCPDF->Ln();
        $this->_renderNewChapter($chapter);
    }

    protected function _renderNewChapter($chapter) {
        // todo - translate this!
        $chapter_name = 'Chapter ' . $chapter;
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
        $this->TCPDF->Write(0, $chapter_name, '', FALSE, $this->pdf_chapter_align);
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
    }
}
