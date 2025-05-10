<?php

namespace App\Renderers;

use App\Models\Bible;
use App\Models\Language;
use \TCPDF_FONTS;

abstract class PdfAbstract extends RenderAbstract 
{
    protected $file_extension = 'pdf';
    protected $include_book_name = TRUE;
    protected $tcpdf_class              = TCPDFBible::class;

    /* PDF-specific settings */
    protected static $pdf_page_format   = 'LETTER';  // For options, see TCPDF_STATIC::$page_formats
    protected static $pdf_red_word_tag  = 'red';     // HTML tag for words of Christ.  'red' colors them with traditional red

    protected static $name_include_format = FALSE;  // Temporary? Until I find time to rebuild renderers with user options

    protected static $render_est_time = 4500;
    
    protected $pdf_orientation          = 'P';
    protected $pdf_unit                 = 'mm';
    protected $pdf_width                = 8.5;      // Width, in $this->pdf_unit units.  Ignored if static::$pdf_format is specified
    protected $pdf_height               = 11;       // Height, in $this->pdf_unit units.  Ignored if static::$pdf_format is specified
    protected $pdf_columns              = 4;        // Number of columns of verses
    protected $pdf_column_width         = 47;       // Width of column, in $this->pdf_unit units.
    protected $pdf_margin               = 8;        // General margin size, in $this->pdf_unit units
    protected $pdf_margin_inside        = 14;        // Inside (binding edge) side margin.  Defaults to $this->pdf_margin
    protected $pdf_margin_outside       = 8;        // Inside (binding edge) side margin.  Defaults to $this->pdf_margin
    protected $pdf_top_margin           = 10;        // Top margin size, in $this->pdf_unit units
    protected $pdf_font_family          = 'freeserif'; // Unicode-friendly serif font
    protected $pdf_text_size            = 9; // Compact: 9? or less, Regular: 12, Large: 14?
    protected $pdf_testement_size       = 20;
    protected $pdf_title_size           = 36;
    protected $pdf_bible_version_size   = 20;
    protected $pdf_toc_title_size       = 16;
    protected $pdf_toc_size             = 12;
    protected $pdf_header_size          = 8;
    protected $pdf_header_style         = 'B';    // Warning, some languages do not support Bold
    protected $pdf_book_size            = 12;
    protected $pdf_book_style           = 'B';    
    protected $pdf_book_align           = 'C';
    protected $pdf_chapter_size         = 10;
    protected $pdf_chapter_style        = 'B';
    protected $pdf_chapter_align        = 'C';
    protected $pdf_text_align           = 'J';
    protected $pdf_brackets_to_italics  = TRUE;
    protected $pdf_verses_paragraph     = FALSE;     // TRUE, FALSE or 'auto'  'auto' will set it to true if Bible has Paragraph markings in it's text
    protected $pdf_break_new_testament  = NULL; // none, column, page
    protected $pdf_break_new_book       = NULL; // none, column, page
    protected $pdf_suppress_write_err   = FALSE;
    protected $pdf_avoid_html           = false; // If TRUE, avoid using HTML tages in the text.
    protected $pdf_allow_bold           = false; // If FALSE, do not use bold text in the PDF.  This is useful for some languages that do not support bold text.

    protected $pdf_no_styles            = false; // If TRUE, do not use any styles (Bold, UL, red letter) in the PDF

    protected $pdf_no_styles_defaults = [
        'pdf_header_style'  => '',
        'pdf_book_style'    => '',
        'pdf_chapter_style' => '',
        'pdf_allow_bold'    => false,
        'pdf_brackets_to_italics' => false,
        'pdf_avoid_html' => true,
    ];

    protected $pdf_language_overrides = [
        'ar' => [
            // 'pdf_font_family'   => 'aefurat', // do not use
            'pdf_column_width'  => 45,
        ],
        'bn' => [
            'pdf_chapter_size' => 9,
            'pdf_no_styles' => true,
        ],
        'te' => [
            'pdf_no_styles' => true,
            // 'pdf_font_family' => 'notoserifteluguvariablefont_wght',
            // Font path is relative to resources/fonts 
            // pdf_font_family is automatically set when the font is loaded
            'font_path' => 'Noto_Serif_Telugu/static/NotoSerifTelugu-Regular.ttf',
        ],
        'pa' => [
            'pdf_no_styles' => true,
        ],
        'gu' => ['pdf_no_styles' => true],
        'bo' => [
            'pdf_no_styles' => true,
            'font_path' => 'Noto_Serif_Tibetan/static/NotoSerifTibetan-Regular.ttf',
        ],
        'ug' => ['pdf_no_styles' => true],
        'ta' => ['pdf_no_styles' => true],
        'kn' => [
            'pdf_no_styles' => true,
            'font_path' => 'Noto_Serif_Kannada/static/NotoSerifKannada-Regular.ttf',
        ],
        'my' => [
            'pdf_no_styles' => true,
            // 'font_path' => 'Noto_Serif_Myanmar/NotoSerifMyanmar-Regular.ttf', // The Noto Myanmar font breaks TCPDF
            //'font_path' => 'myanmar/Pyidaungsu/Pyidaungsu-1.8.3_Regular.ttf', // mostly works?  (dashed circle characters?)
            'font_path' => 'myanmar/Pyidaungsu/Myanmar3-2018.ttf',  // works
        ],
        'am' => ['pdf_no_styles' => true],
        // 'gu' => ['pdf_no_styles' => true],
        // 'gu' => ['pdf_no_styles' => true],
        'zh' => [
            'pdf_font_family' => 'msungstdlight',
        ],        
        'ja' => [
            'pdf_font_family' => 'cid0jp',
        ],        
        'ko' => [
            'pdf_font_family' => 'cid0kr',
        ],
        'th' => [
            // 'pdf_columns' => 3,
            // 'pdf_column_width' => 65,            
            'pdf_columns' => 2,
            'pdf_column_width' => 95,
            'pdf_text_align' => 'L',
        ],
        'he' => [
            'pdf_suppress_write_err' => TRUE,
        ],
    ];

    /* END PDF-specific settings */

    protected $text_pending = ''; 
    protected $TCPDF;

    protected $last_render_book    = NULL;
    protected $last_render_chapter = NULL;

    protected $toc_page = 5;
    protected $in_psalms = FALSE;

    public function __construct($module) 
    {
        parent::__construct($module);

        $this->_applyPdfLanguageOverride();

        $this->pdf_margin_outside = $this->pdf_margin_outside ?: $this->pdf_margin;
        $this->pdf_margin_inside  = $this->pdf_margin_inside  ?: $this->pdf_margin;

        $format = static::$pdf_page_format ?: [$this->pdf_width, $this->pdf_height];
        $this->TCPDF  = new $this->tcpdf_class($this->pdf_orientation, $this->pdf_unit, $format);
        $this->TCPDF->setHeaderMargin(10);
        $this->TCPDF->setFooterMargin(0);
        $this->TCPDF->allow_bold = $this->pdf_allow_bold;

        //$res = TCPDF_FONTS::addTTFfont(__DIR__ . '/../../resources/fonts/noto_serif_telugu/NotoSerifTelugu-VariableFont_wght.ttf', 'TrueTypeUnicode', '', 96);

        //var_dump(is_file( __DIR__ . '/../../resources/fonts/noto_serif_telugu/static/NotoSerifTelugu-Regular.ttf'));

        // $res = $this->TCPDF->AddFont('bobski', '', __DIR__ . '/../../resources/fonts/noto_serif_telugu/NotoSerifTelugu-VariableFont_wght.ttf');
        // $res = $this->TCPDF->AddFont('bobski', '', __DIR__ . '/../../resources/fonts/noto_serif_telugu/static/NotoSerifTelugu-Regular.ttf');
        // // $res = $this->TCPDF->addTTFfont(__FILE__ . '/../../resources/fonts/noto_serif/telugu/NotoSerifTelugu-VariableFont_wght.ttf');

        // print_r($res);
        // var_dump($res);
        // die();
        
        if(static::$load_fonts) {
            $this->_initiateFonts();
        }
        
        $this->TCPDF->SetAutoPageBreak(TRUE, $this->pdf_margin);
        $this->TCPDF->SetMargins($this->pdf_margin_inside, $this->pdf_top_margin, $this->pdf_margin_outside);
    }

    protected function _initiateFonts() 
    {
        $this->TCPDF->setFont($this->pdf_font_family, '', $this->pdf_text_size);
        $this->TCPDF->setHeaderFont([$this->pdf_font_family, $this->pdf_header_style, $this->pdf_header_size]);
        $this->TCPDF->setFooterFont([$this->pdf_font_family, $this->pdf_header_style, $this->pdf_header_size]);
    }

    protected function _applyDefaultFont() 
    {
        $this->TCPDF->setFont($this->pdf_font_family, '', $this->pdf_text_size);
    }

    protected function _renderStart() 
    {
        // todo: Set RTL based on Bible language

        $this->TCPDF->setTitle($this->Bible->name);
        $this->TCPDF->addPage();

        $h       = $this->TCPDF->getPageHeight();
        $margins = $this->TCPDF->getMargins();

        $h -= $margins['top'] + $margins['bottom'] + $margins['header'] + $margins['footer'];

        $title_height = $h / 5;
        $title_pos = $h / 2 - $title_height;

        $this->TCPDF->setFontSize($this->pdf_title_size);
        $this->TCPDF->setY($title_pos);
        $this->TCPDF->Cell(0, $title_height, $this->strtoupper(__('basic.holy_bible')),   0, 1, 'C');
        $this->TCPDF->setFontSize($this->pdf_bible_version_size);
        $this->TCPDF->MultiCell(0, $title_height, $this->strtoupper($this->Bible->name), 0, 'C');
        $this->TCPDF->ln();
        $this->TCPDF->addPage();
        $this->TCPDF->addPage();
        $this->TCPDF->setY(0);
        $this->TCPDF->setFontSize($this->pdf_text_size);
        $this->TCPDF->Cell(0, 20, $this->Bible->name, 0, 1, 'L');
        $this->TCPDF->writeHTMLCell(0, 40, $this->pdf_margin_inside, $this->TCPDF->getY(), $this->_getCopyrightStatement() );

        $page = $this->TCPDF->getBiblePageCount();

        if($page >= $this->toc_page) {
            $inc = ($page % 2 == 1) ? 2 : 1;
            $this->toc_page = $page + $inc;

            if($page % 2 == 1) {
                // If the copyright info ends on an odd numbered page, add an extra page to insure TOC is on odd-numbered page as well
                $this->TCPDF->addPage();
            } 
        }

        $this->TCPDF->addPage();
        $this->TCPDF->addPage(); // TOC
        $this->TCPDF->addPage(); 

        $this->_setRtlByLanguage(TRUE);

        $this->_enableColumns();

        $this->TCPDF->setFontSize($this->pdf_text_size);

        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {

        if($this->pdf_verses_paragraph === 'auto') {
            $this->pdf_verses_paragraph = (strpos($verse->text, '¶') !== FALSE);
        }

        $this->TCPDF->setCurrentVerse($verse);

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
            $this->_writeText($text);     
        }
        else {
            // Paragraph format (preferred - takes up less paper)
            if (strpos($verse->text, '¶') !== FALSE) {
                $this->_writeText();
            }

            $this->text_pending .= $text . '  ';
        }

        $this->last_render_book         = $verse->book;
        $this->last_render_chapter      = $verse->chapter;
    }

    protected function _writeText($text = NULL, $text_pending_addl = '') 
    {
        if(!$text) {
            if(!$this->text_pending) {
                return;
            }

            // Add paragraph indent - currently $this->text_pending is only used for paragraph rendering
            if($this->pdf_avoid_html) {
                $text = $this->text_pending . $text_pending_addl;
            } else {
                $text = '<div style="text-indent:20px">' . $this->text_pending . $text_pending_addl . '</div>';
            }

            $this->text_pending = '';
        }

        if(!$text) {
            return;
        }

        // Todo: quick exit if Bible doesn't support italics, red letter, or strongs
        // if(!$this->pdf_brackets_to_italics || (!$this->Bible->italics || !$this->Bible->)) {
        // if(!$this->pdf_brackets_to_italics) {
        //     $this->TCPDF->Write(0, $text, '', FALSE, $this->pdf_text_align, TRUE);
        //     return;
        // }

        $text_test = trim($text);

        switch(static::$pdf_red_word_tag) {
            case 'red' :
                $rl_st = '<span style="color:rgb(255, 0, 0);">';
                $rl_en = "</span>";
                break;
            case 'u':
            case 'b':
                $rl_st = '<' . static::$pdf_red_word_tag . '>';
                $rl_en = '</' . static::$pdf_red_word_tag . '>';
                break;
            default: 
                $rl_st = $rl_en = '';
        }

        // Note:  WriteHTML apparently solves the 'extra linebreak problem' and probably should be used for everything, even if the text doesn't require HTML rendering
        // This takes ~ 6.0 min
        // Write as HTML - works but is SLOW!  Takes 3x as long.  And it alters the margins some

        $find = $repl = [];

        $find[] = '  ';
        $repl[] = '&nbsp;&nbsp;';

        if($this->pdf_avoid_html) {
            $html = str_replace(array('‹', '›', '[', ']'), '', $text);
        } else {
            $html = str_replace(array('‹', '›', '[', ']', '  '), array($rl_st, $rl_en, '<i>', '</i>', '&nbsp;&nbsp;'), $text);
        }

        // $html = str_replace('  ', '&nbsp;&nbsp;', $text); // for some reason THIS takes 16 min for the KJV!
        // $html = $text;
        $this->TCPDF->setFont($this->pdf_font_family, '', $this->pdf_text_size);
        
        if($this->pdf_suppress_write_err) {
            $er_cache = error_reporting();
            error_reporting(0);
        }

        $this->TCPDF->WriteHTML($html, TRUE, FALSE, TRUE, FALSE, $this->pdf_text_align);

        if($this->pdf_suppress_write_err) {
            error_reporting($er_cache);
        }
        
        return;

        // TEST CODE - RETAINING FOR NOW

        // Retaining the below test code for reference
        $mod = ($text_test[0] == '[') ? 0 : 1;
        $pieces = preg_split("/\[|]/", $text); // split text into pieces by [ and ]
        $first_line = TRUE;

        foreach($pieces as $key => $t) {
            $style = ($key % 2 == $mod) ? 'I' : '';

            $this->TCPDF->SetFont($this->pdf_font_family, $style);

            // This takes ~ 4.25 min
            // while($t) {
            //     $t = $this->TCPDF->Write(0, $t, '', FALSE, $this->pdf_text_align, FALSE, 4, TRUE, $first_line); // 'works' but text not justified
            //     $first_line = FALSE;
            // }
            
            // $this->TCPDF->Write(0, $t, '', FALSE, $this->pdf_text_align, TRUE); // doesn't work, 1.85 min
            // $this->TCPDF->Write(0, $t, '', FALSE, $this->pdf_text_align, FALSE, 0, FALSE, $first_line); // doesn't work

            $this->TCPDF->Cell(0, 0, $t, 0, 0, $this->pdf_text_align);     // doesn't work
            // $this->TCPDF->MultiCell(0, 0, $t, 0, $this->pdf_text_align);  // doesn't work
        }

        $this->TCPDF->Ln(); 
    }

    protected function _renderFinish() 
    {
        $this->_writeText();

        $this->TCPDF->setEqualColumns(0);
        $this->TCPDF->endPage();
        $this->TCPDF->setCurrentVerse(NULL);

        // $this->_setRtlByLanguage(FALSE); // TOC page should follow language, but since we don't have book lists for Hebrew or Arabic, we force it to LTR for now.

        // Fix huge margins by re-iniitalizing them.
        $this->TCPDF->SetMargins($this->pdf_margin_inside, $this->pdf_top_margin, $this->pdf_margin_outside);

        // add a new page for TOC
        
        $this->TCPDF->addTOCPage();

        // write the TOC title
        $page_width = $this->TCPDF->getPageWidth();
        $margins    = $this->TCPDF->getMargins();
        $page_width -= $margins['left'] + $margins['right'];

        $table_of_contents = __('basic.table_of_contents');

        $this->TCPDF->SetFont($this->pdf_font_family, '', $this->pdf_toc_title_size);
        // $this->TCPDF->Write(0, $table_of_contents, '', TRUE, 'C', TRUE);
        $this->TCPDF->MultiCell(0, 0, $table_of_contents, 0, 'C', 0, 1, '', '', true, 0);
        // $this->TCPDF->Cell($page_width, 0, $table_of_contents,0,0,'C');
        $this->TCPDF->Ln();

        $this->TCPDF->setEqualColumns(2);
        $this->TCPDF->SetFont($this->pdf_font_family, '', $this->pdf_toc_size);

        $this->TCPDF->addTOC($this->toc_page, 'courier', '.', $table_of_contents, '');

        $this->TCPDF->setEqualColumns(0);
        // end of TOC page
        $this->TCPDF->endTOCPage();
        

        $filepath = $this->getRenderFilePath(TRUE);
        $this->TCPDF->Output($filepath, 'F');

        return TRUE;
    }

    protected function _renderNewBook($book, $book_name, $chapter = 1) 
    {
        if($this->pdf_verses_paragraph && $this->text_pending) {
            // $this->text_pending .= '<br />';
        }
 
        $this->_writeText(NULL, '<br /><br />');

        if($book == 1) {
            $this->_renderTestamentHeader(__('basic.old_testament'));
        }

        if($book == 40) {
            $this->_renderTestamentHeader(__('basic.new_testament'));
        }

        $this->in_psalms = ($book == 19);
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_book_style, $this->pdf_book_size);
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
        
        // Add page or switch column, if needed
        // $height_pixel = $this->pdf_book_size * 3;
        $height_pixel = $this->pdf_book_size * 2 + $this->pdf_chapter_size * 3 + $this->pdf_text_size;
        $height_units = $this->TCPDF->pixelsToUnits($height_pixel);
        $this->TCPDF->checkPageBreak($height_units);

        $this->TCPDF->Bookmark($this->strtoupper($book_name), 1);
        $this->TCPDF->Write(0, $this->strPreFormat($book_name), '', FALSE, $this->pdf_book_align);
        $this->TCPDF->Ln();
        $this->_renderNewChapter($chapter);
    }

    protected function strtoupper($text) 
    {
        // $text = mb_strtoupper($text, 'UTF-8');
        return $text;
    }

    protected function strPreFormat($text)
    {
        // $text = TCPDF_FONTS::UTF8ToUTF16BE($text, false, true, $this->TCPDF->getCurrentFont());
        return $text;
    }

    protected function _renderTestamentHeader($testament) 
    {

        if($this->pdf_break_new_testament == 'page') {
            $this->_addOddNumberedPage();
            $this->_applyDefaultFont();
            $this->TCPDF->setFontSize($this->pdf_testement_size);
            $this->TCPDF->Bookmark($testament);
            $this->TCPDF->Cell(0, 20, $testament, 0, 1, 'L');
            $this->TCPDF->addPage();
            $this->TCPDF->addPage();
        } 
        else {
            // $testament = $this->TCPDF->formatTitle($testament);
            $this->TCPDF->Bookmark($testament);
        }
    }

    protected function _renderNewChapter($chapter) 
    {
        if($this->pdf_verses_paragraph && $this->text_pending) {
            // $this->text_pending .= '<br />';
        }

        $this->_writeText(NULL, '<br />');
        $ch_param = ($this->in_psalms) ? 'basic.psalm_n' : 'basic.chapter_n';

        $chapter_name = __($ch_param, ['n' => $chapter]);
        $this->TCPDF->setFont($this->pdf_font_family, $this->pdf_chapter_style, $this->pdf_chapter_size);
        $this->TCPDF->Ln();

        // Add page or switch column, if needed
        $height_pixel = $this->pdf_chapter_size * 3 + $this->pdf_text_size;
        $height_units = $this->TCPDF->pixelsToUnits($height_pixel);
        $this->TCPDF->checkPageBreak($height_units);

        $this->TCPDF->Bookmark($chapter_name, 2);
        $this->TCPDF->Write(0, $chapter_name, '', FALSE, $this->pdf_chapter_align);
        $this->TCPDF->Ln();
        $this->TCPDF->Ln();
        $this->_applyDefaultFont();
    }

    protected function _enableColumns() 
    {
        if($this->pdf_columns > 1) {
            $this->TCPDF->setEqualColumns($this->pdf_columns, $this->pdf_column_width);
        }
    }

    protected function _disableColumns() 
    {
        $this->TCPDF->setEqualColumns(0);
    }

    protected function _applyPdfLanguageOverride() 
    {
        if(array_key_exists($this->Bible->lang_short, $this->pdf_language_overrides) && is_array($this->pdf_language_overrides[ $this->Bible->lang_short ])) {
            $ov = $this->pdf_language_overrides[ $this->Bible->lang_short ];

            if(array_key_exists('font_path', $ov)) {
                $full_path = __DIR__ . '/../../resources/fonts/' . $ov['font_path'];
                $ov['pdf_font_family'] = TCPDF_FONTS::addTTFfont($full_path);

                if(!$ov['pdf_font_family'] ) {
                    die('Font not found: resources/fonts/' . $ov['font_path']);
                }
                
                unset($ov['font_path']);
            }
            
            foreach($ov as $key => $value) {
                $this->$key = $value;
            }
        }

        if($this->pdf_no_styles) {
            foreach($this->pdf_no_styles_defaults as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    protected function _setRtlByLanguage($enable = TRUE) 
    {
        if($enable && Language::isRtl($this->Bible->lang_short)) {
            $this->TCPDF->setRTL(TRUE);
        }
        else {
            $this->TCPDF->setRTL(FALSE);
        }
    }

    /**
     * Adds the next odd-numbered page
     */
    protected function _addOddNumberedPage() 
    {
        $page = $this->TCPDF->getBiblePageCount();
        $this->TCPDF->addPage();

        if($page % 2 == 0) {
            // If the current page was odd-numbered, add a second page
            $this->TCPDF->addPage();
        } 
    }

    public static function getName() 
    {
        if(!static::$name_include_format) {
            return static::$name;
        }

        $woc = '';

        switch(static::$pdf_red_word_tag) {
            case 'b':
                $woc = 'in <b>Bold</b>';
                break;
            case 'u':
                $woc = '<u>Underlined</u>';
                break;
            case 'red':
                $woc = 'in <span class="red">Red</span>';
                break;
        }

        $woc = ($woc) ? ', Words of Christ ' . $woc : '';

        $format = static::$pdf_page_format;

        if($format == 'LETTER') {
            $format = 'Letter';
        }

        return static::$name . ', ' . $format . $woc;
    }

    public static function getDescription() 
    {
        $desc = static::$description;

        if(static::$pdf_red_word_tag == 'u' || static::$pdf_red_word_tag == 'b') {
            $desc .= ' Monochrome friendly.';
        }

        return $desc;
    }
}
