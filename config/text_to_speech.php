<?php 
// Needs voice 
// narakeet
// openai
// murfai

// These are global default voices
// For best results, set voices per language in resources/lang/{lang_short}/text_to_speech.php

return [
    'narakeet' => [
        'voices' => [
            'default'   => 'raymond',
            'male'      => null, // ???
            'female'    => null,
        ]
    ],
    'openai' => [
        'voices' => [
            'default'   => null,
            'male'      => null,
            'female'    => null,
        ],
    ],
    'murfai' => [
        'voices' => [
            'default'   => null,
            'male'      => null,
            'female'    => null,
        ],
    ],
];
