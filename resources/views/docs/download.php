<?php
    $BibleSuperSearchAPIURL          = '';
    $BibleSuperSearchDownloadVerbose = TRUE;
    $BibleSuperSearchBibles          = $bibles;
    $BibleSuperSearchDownloadFormats = $formats;
    $BibleSuperSearchDownloadLimit   = config('download.bible_limit');
    $BibleSuperSearchIsAdmin         = $admin;

    include public_path() . '/widgets/download/download.php';
