<?php 
// Needs voice 
// narakeet
// openai
// murfai

// These are global default voices
// For best results, set voices per language in ./lang/{lang_short}/text_to_speech.php

return [
    'narakeet' => [
        'voice' => null,
    ],
    'openai' => [
        'voice' => 'alloy',
    ],
    'murfai' => [
        'voice' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation limits and timeouts
    |--------------------------------------------------------------------------
    |
    | These apply to every TTS provider above. They previously lived only as
    | inline defaults at their call sites under an 'audio.' prefix that nothing
    | defined -- there is no config/audio.php, and that namespace is populated
    | exclusively by the soft-config rows in the database -- so they read as
    | tunable while being pinned to the literals in the code.
    |
    */

    // Cap on how many verses one request may generate audio for. Each verse
    // that has no audio file yet costs one external TTS call, so an uncapped
    // range lets a single request drive an unbounded amount of provider work,
    // spend and storage. Verses already on disk are not counted against this.
    // Set to 0 to disable the cap.
    'max_verses_per_request' => 200,

    // Seconds to wait for the provider's API to accept the connection.
    'connect_timeout' => 10,

    // Seconds to wait for the whole request, including audio generation. Must
    // be at least connect_timeout. A provider synthesising a long verse can
    // legitimately take a while, so this is generous.
    'timeout' => 120,
];
