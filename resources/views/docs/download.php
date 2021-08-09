<?php
    $BibleSuperSearchAPIURL          = '';
    $BibleSuperSearchDownloadVerbose = TRUE;
    $BibleSuperSearchBibles          = $bibles;
    $BibleSuperSearchDownloadFormats = $formats;
    $BibleSuperSearchDownloadLimit   = config('download.bible_limit');
    $BibleSuperSearchIsAdmin         = $admin;

    include $_SERVER['DOCUMENT_ROOT'] . '/widgets/download/download.php';
