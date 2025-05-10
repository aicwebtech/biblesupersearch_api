<?php

namespace App\Renderers;

use \TCPDF;
use \TCPDF_STATIC;
use \TCPDF_FONTS;

if(!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
}

require_once( dirname(__FILE__) . '/tcpdf_config.php');


class TCPDFBible extends TCPDF 
{
    
    public $current_book;
    public $current_chapter;
    public $current_verse;
    public $toc_bookmark_level = 1;
    public $allow_bold = true;

    protected $start_book;
    protected $start_chapter;    
    protected $prev_book;
    protected $prev_chapter;
    protected $outlines_cache = [];
    protected $bible_page_count = 0;

    public function Header() 
    {
        // This doesn't actually generate a header!
        $this->bible_page_count ++;
        $this->start_book    = NULL;
        $this->start_chapter = NULL;
        $this->swapMargins();
    }

    public function Footer() 
    {
        // Position 'footer' at TOP of page 
        $this->SetY(0);
        $this->SetFontSize(8);
        $rtl = $this->getRtl();

        $this->setRtl(FALSE);

        
        if($this->start_book && $this->prev_book) {
            
            
            $st = $this->start_book   . ' ' . $this->start_chapter;
            $en = $this->prev_book    . ' ' . $this->prev_chapter;

            $index = ($st == $en) ? $st : $st . ' - ' . $en;

            $page_width = $this->getPageWidth();
            $margins    = $this->getMargins();
            $page_width -= $margins['left'] + $margins['right'];
            $cell_width = floor($page_width / 3);

            // $pg = trim($this->getAliasNumPage());
            $pg = $this->bible_page_count;

            if($rtl) {
                $this->Cell($cell_width, 10, $en, 0, 0, 'L', FALSE, '', 0, FALSE, 'T', 'M');
                $this->Cell($cell_width, 10, $pg, 0, 0, 'C', FALSE, '', 0, FALSE, 'T', 'M');
                $this->Cell($cell_width, 10, $st, 0, 0, 'R', FALSE, '', 0, FALSE, 'T', 'M');
            }
            else {
                $this->Cell($cell_width, 10, $st, 0, 0, 'L', FALSE, '', 0, FALSE, 'T', 'M');
                $this->Cell($cell_width, 10, $pg, 0, 0, 'C', FALSE, '', 0, FALSE, 'T', 'M');
                $this->Cell($cell_width, 10, $en, 0, 0, 'R', FALSE, '', 0, FALSE, 'T', 'M');                
            }
        }

        $this->setRtl($rtl);

        $this->start_book = '';
        $this->start_chapter = '';
    }

    public function getBiblePageCount() 
    {
        return $this->bible_page_count;
    }

    public function getCurrentFont()
    {
        return $this->CurrentFont;
    }

    public function setCurrentVerse($verse) 
    {
        if($verse) {
            $this->prev_book        = $this->current_book;
            $this->prev_chapter     = $this->current_chapter;
            $this->current_book     = $verse->book_name;
            $this->current_chapter  = $verse->chapter;
            $this->current_verse    = $verse->verse;

            if(!$this->start_book) {
                $this->start_book       = $verse->book_name;
                $this->start_chapter    = $verse->chapter;
            }
        }
        else {
            $this->current_book         = NULL;
            $this->current_chapter      = NULL;
            $this->current_verse        = NULL;
            $this->start_book           = NULL;
            $this->start_chapter        = NULL;            
            $this->prev_book            = NULL;
            $this->prev_chapter         = NULL;
        }
    }

    public function formatTitle($title) 
    {
        $oid = $this->_newobj();
        $nltags = '/<br[\s]?\/>|<\/(blockquote|dd|dl|div|dt|h1|h2|h3|h4|h5|h6|hr|li|ol|p|pre|ul|tcpdf|table|tr|td)>/si';
        // covert HTML title to string
        $title = preg_replace($nltags, "\n", $title);
        $title = preg_replace("/[\r]+/si", '', $title);
        $title = preg_replace("/[\n]+/si", "\n", $title);
        $title = strip_tags($title);
        // $title = $this->stringTrim($title);
        // $title = $this->_textstring($title, $oid);

        return $title;
    }

    // Customized TCPDF methods

    // Making this public
    public function checkPageBreak($h=0, $y='', $addpage=true) 
    {
        return parent::checkPageBreak($h, $y, $addpage);
    }

    /**
     *   CUSTOMIZED VERSION OF TCPDF::addTOC();

     * Output a Table of Content Index (TOC).
     * This method must be called after all Bookmarks were set.
     * Before calling this method you have to open the page using the addTOCPage() method.
     * After calling this method you have to call endTOCPage() to close the TOC page.
     * You can override this method to achieve different styles.
     * @param $page (int) page number where this TOC should be inserted (leave empty for current page).
     * @param $numbersfont (string) set the font for page numbers (please use monospaced font for better alignment).
     * @param $filler (string) string used to fill the space between text and page number.
     * @param $toc_name (string) name to use for TOC bookmark.
     * @param $style (string) Font style for title: B = Bold, I = Italic, BI = Bold + Italic.
     * @param $color (array) RGB color array for bookmark title (values from 0 to 255).
     * @public
     * @author Nicola Asuni
     * @since 4.5.000 (2009-01-02)
     * @see addTOCPage(), endTOCPage(), addHTMLTOC()
     */
    public function addTOC($page='', $numbersfont='', $filler='.', $toc_name='TOC', $style='', $color=array(0,0,0)) 
    {
        $bold = $this->allow_bold ? 'B' : ''; // custom

        $fontsize = $this->FontSizePt;
        $fontfamily = $this->FontFamily;
        $fontstyle = $this->FontStyle;
        $w = $this->w - $this->lMargin - $this->rMargin;
        $spacer = $this->GetStringWidth(chr(32)) * 4;
        $lmargin = $this->lMargin;
        $rmargin = $this->rMargin;
        $x_start = $this->GetX();
        $page_first = $this->page;
        $current_page = $this->page;
        $page_fill_start = false;
        $page_fill_end = false;
        $current_column = $this->current_column;
        if (TCPDF_STATIC::empty_string($numbersfont)) {
            $numbersfont = $this->default_monospaced_font;
        }
        if (TCPDF_STATIC::empty_string($filler)) {
            $filler = ' ';
        }
        if (TCPDF_STATIC::empty_string($page)) {
            $gap = ' ';
        } else {
            $gap = '';
            if ($page < 1) {
                $page = 1;
            }
        }
        $this->SetFont($numbersfont, $fontstyle, $fontsize);
        $numwidth = $this->GetStringWidth('00000');
        $maxpage = 0; //used for pages on attached documents
        foreach ($this->outlines as $key => $outline) {
            
            // Start custom code
            if($outline['l'] > $this->toc_bookmark_level) {
                continue;
            }
            // End custom code

            //$outline['l'] = 0;

            // check for extra pages (used for attachments)
            if (($this->page > $page_first) AND ($outline['p'] >= $this->numpages)) {
                $outline['p'] += ($this->page - $page_first);
            }
            if ($this->rtl) {
                $aligntext = 'R';
                $alignnum = 'L';
            } else {
                $aligntext = 'L';
                $alignnum = 'R';
            }
            if ($outline['l'] == 0) {
                $this->SetFont($fontfamily, $outline['s'].$bold, $fontsize); // custom
            } else {
                $this->SetFont($fontfamily, $outline['s'], $fontsize - $outline['l']);
            }
            $this->SetTextColorArray($outline['c']);
            // check for page break
            $this->checkPageBreak(2 * $this->getCellHeight($this->FontSize));
            // set margins and X position
            if (($this->page == $current_page) AND ($this->current_column == $current_column)) {
                $this->lMargin = $lmargin;
                $this->rMargin = $rmargin;
            } else {
                if ($this->current_column != $current_column) {
                    if ($this->rtl) {
                        $x_start = $this->w - $this->columns[$this->current_column]['x'];
                    } else {
                        $x_start = $this->columns[$this->current_column]['x'];
                    }
                }
                $lmargin = $this->lMargin;
                $rmargin = $this->rMargin;
                $current_page = $this->page;
                $current_column = $this->current_column;
            }
            $this->SetX($x_start);
            $indent = ($spacer * $outline['l']);
            if ($this->rtl) {
                $this->x -= $indent;
                $this->rMargin = $this->w - $this->x;
            } else {
                $this->x += $indent;
                $this->lMargin = $this->x;
            }
            $link = $this->AddLink();
            $this->SetLink($link, $outline['y'], $outline['p']);
            // write the text
            if ($this->rtl) {
                $txt = ' '.$outline['t'];
            } else {
                $txt = $outline['t'].' ';
            }
            $this->Write(0, $txt, $link, false, $aligntext, false, 0, false, false, 0, $numwidth, '');
            if ($this->rtl) {
                $tw = $this->x - $this->lMargin;
            } else {
                $tw = $this->w - $this->rMargin - $this->x;
            }
            $this->SetFont($numbersfont, $fontstyle, $fontsize);
            if (TCPDF_STATIC::empty_string($page)) {
                $pagenum = $outline['p'];
            } else {
                // placemark to be replaced with the correct number
                $pagenum = '{#'.($outline['p']).'}';
                if ($this->isUnicodeFont()) {
                    $pagenum = '{'.$pagenum.'}';
                }
                $maxpage = max($maxpage, $outline['p']);
            }
            $fw = ($tw - $this->GetStringWidth($pagenum.$filler));
            $wfiller = $this->GetStringWidth($filler);
            if ($wfiller > 0) {
                $numfills = floor($fw / $wfiller);
            } else {
                $numfills = 0;
            }
            if ($numfills > 0) {
                $rowfill = str_repeat($filler, $numfills);
            } else {
                $rowfill = '';
            }
            if ($this->rtl) {
                $pagenum = $pagenum.$gap.$rowfill;
            } else {
                $pagenum = $rowfill.$gap.$pagenum;
            }
            // write the number
            $this->Cell($tw, 0, $pagenum, 0, 1, $alignnum, 0, $link, 0);
        }
        $page_last = $this->getPage();
        $numpages = ($page_last - $page_first + 1);
        // account for booklet mode
        if ($this->booklet) {
            // check if a blank page is required before TOC
            $page_fill_start = ((($page_first % 2) == 0) XOR (($page % 2) == 0));
            $page_fill_end = (!((($numpages % 2) == 0) XOR ($page_fill_start)));
            if ($page_fill_start) {
                // add a page at the end (to be moved before TOC)
                $this->addPage();
                ++$page_last;
                ++$numpages;
            }
            if ($page_fill_end) {
                // add a page at the end
                $this->addPage();
                ++$page_last;
                ++$numpages;
            }
        }
        $maxpage = max($maxpage, $page_last);
        if (!TCPDF_STATIC::empty_string($page)) {
            for ($p = $page_first; $p <= $page_last; ++$p) {
                // get page data
                $temppage = $this->getPageBuffer($p);
                for ($n = 1; $n <= $maxpage; ++$n) {
                    // update page numbers
                    $a = '{#'.$n.'}';
                    // get page number aliases
                    $pnalias = $this->getInternalPageNumberAliases($a);
                    // calculate replacement number
                    if (($n >= $page) AND ($n <= $this->numpages)) {
                        $np = $n + $numpages;
                    } else {
                        $np = $n;
                    }
                    $na = TCPDF_STATIC::formatTOCPageNumber(($this->starting_page_number + $np - 1));
                    $nu = TCPDF_FONTS::UTF8ToUTF16BE($na, false, $this->isunicode, $this->CurrentFont);
                    // replace aliases with numbers
                    foreach ($pnalias['u'] as $u) {
                        $sfill = str_repeat($filler, max(0, (strlen($u) - strlen($nu.' '))));
                        if ($this->rtl) {
                            $nr = $nu.TCPDF_FONTS::UTF8ToUTF16BE(' '.$sfill, false, $this->isunicode, $this->CurrentFont);
                        } else {
                            $nr = TCPDF_FONTS::UTF8ToUTF16BE($sfill.' ', false, $this->isunicode, $this->CurrentFont).$nu;
                        }
                        $temppage = str_replace($u, $nr, $temppage);
                    }
                    foreach ($pnalias['a'] as $a) {
                        $sfill = str_repeat($filler, max(0, (strlen($a) - strlen($na.' '))));
                        if ($this->rtl) {
                            $nr = $na.' '.$sfill;
                        } else {
                            $nr = $sfill.' '.$na;
                        }
                        $temppage = str_replace($a, $nr, $temppage);
                    }
                }
                // save changes
                $this->setPageBuffer($p, $temppage);
            }
            // move pages
            $this->Bookmark($toc_name, 0, 0, $page_first, $style, $color);
            if ($page_fill_start) {
                $this->movePage($page_last, $page_first);
            }
            for ($i = 0; $i < $numpages; ++$i) {
                $this->movePage($page_last, $page);
            }
        }
    }
}
