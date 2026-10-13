<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs (AI Voice Generation)
    |--------------------------------------------------------------------------
    |
    | Credentials for the ElevenLabs text-to-speech REST API. The application
    | uses lazy generation: the first request renders the poem to an .mp3
    | file inside the "public" disk, later requests reuse the cached file.
    |
    */

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
        'output_format' => env('ELEVENLABS_OUTPUT_FORMAT', 'mp3_44100_128'),
        // Optional. Leave EMPTY for eleven_multilingual_v2 (it auto-detects the
        // language and rejects language_code). Set "hy" for turbo/flash models.
        'language' => env('ELEVENLABS_LANGUAGE'),
        'with_timestamps' => filter_var(env('ELEVENLABS_WITH_TIMESTAMPS', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('ELEVENLABS_TIMEOUT', 180),
        // Cloudflare in front of ElevenLabs rejects requests without a
        // browser-like User-Agent (403 "Just a moment..."), so we send one.
        'user_agent' => env('ELEVENLABS_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'),
        // Some shared-hosting IPs are challenged by Cloudflare in front of
        // api.elevenlabs.io. Point this to an HTTP proxy (e.g. http://user:pass@host:port)
        // to route the calls through a "clean" IP.
        'proxy' => env('ELEVENLABS_PROXY'),
        'stability' => (float) env('ELEVENLABS_STABILITY', 0.5),
        'similarity_boost' => (float) env('ELEVENLABS_SIMILARITY_BOOST', 0.75),
        'style' => (float) env('ELEVENLABS_STYLE', 0.0),
        'use_speaker_boost' => filter_var(env('ELEVENLABS_SPEAKER_BOOST', true), FILTER_VALIDATE_BOOL),
        // Optional speech rate (0.7 — 1.2). Not sent unless configured.
        'speed' => env('ELEVENLABS_SPEED'),
        'disk' => env('ELEVENLABS_DISK', 'public'),
        'directory' => env('ELEVENLABS_DIRECTORY', 'ai_audio'),
    ],

];
