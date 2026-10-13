<?php

namespace App\Services;

use App\Models\Poem;
use App\Support\PoemText;
use App\Support\WordTokenizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ElevenLabs text-to-speech integration with lazy audio generation.
 *
 * Lazy generation strategy:
 *  1. A deterministic file name is derived from the poem slug, its content
 *     hash and the configured voice id.
 *  2. If the .mp3 already exists on the "public" disk it is reused.
 *  3. Otherwise the API is called, audio (and word alignment) is stored.
 */
class ElevenLabsService
{
    protected ?string $apiKey;

    protected string $baseUrl;

    protected ?string $voiceId;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $voiceId = null,
    ) {
        $this->apiKey = $apiKey ?: config('services.elevenlabs.key');
        $this->baseUrl = rtrim($baseUrl ?: (string) config('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'), '/');
        $this->voiceId = $voiceId ?: config('services.elevenlabs.voice_id');
    }

    /**
     * Whether the service has the credentials required to call the API.
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->voiceId);
    }

    public function disk(): string
    {
        return (string) config('services.elevenlabs.disk', 'public');
    }

    public function directory(): string
    {
        return trim((string) config('services.elevenlabs.directory', 'ai_audio'), '/');
    }

    /**
     * Deterministic relative storage path of the AI recording for a poem.
     */
    public function pathForPoem(Poem $poem, ?string $text = null): string
    {
        $text ??= (string) $poem->content;
        $hash = substr(sha1(($poem->slug ?: 'poem').'|'.$text.'|'.$this->voiceId), 0, 16);

        return $this->directory().'/'.$poem->slug.'-'.$hash.'.mp3';
    }

    /**
     * Whether the AI audio for the poem is already cached on disk.
     */
    public function audioExists(Poem $poem): bool
    {
        return Storage::disk($this->disk())->exists($this->pathForPoem($poem));
    }

    /**
     * Lazy generation entry point used by controllers and commands.
     *
     * @return array{path: string, url: string, words: array<int, array{word: string, start: float, end: float}>, cached: bool}
     */
    public function generateAudioForPoem(Poem $poem, bool $force = false): array
    {
        $path = $this->pathForPoem($poem);
        $disk = Storage::disk($this->disk());

        if (! $force && $disk->exists($path)) {
            return [
                'path' => $path,
                'url' => $disk->url($path),
                'words' => $this->loadAlignment($poem),
                'cached' => true,
            ];
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('ElevenLabs is not configured. Set ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID.');
        }

        $result = $this->synthesize((string) $poem->content, $poem);
        $disk->put($path, $result['audio'], ['visibility' => 'public']);
        $this->storeAlignment($poem, $result['words']);

        return [
            'path' => $path,
            'url' => $disk->url($path),
            'words' => $result['words'],
            'cached' => false,
        ];
    }

    /**
     * Call the ElevenLabs REST API and return raw audio bytes plus word timings.
     *
     * @return array{audio: string, words: array<int, array{word: string, start: float, end: float}>, alignment: array<string, mixed>}
     */
    public function synthesize(string $text, ?Poem $poem = null, array $options = []): array
    {
        $voiceId = (string) ($options['voice_id'] ?? $this->voiceId);
        if ($voiceId === '') {
            throw new RuntimeException('ElevenLabs voice id is not configured.');
        }

        $withTimestamps = (bool) ($options['with_timestamps'] ?? config('services.elevenlabs.with_timestamps', true));
        $outputFormat = (string) ($options['output_format'] ?? config('services.elevenlabs.output_format', 'mp3_44100_128'));
        $model = (string) ($options['model'] ?? config('services.elevenlabs.model', 'eleven_multilingual_v2'));

        $voiceSettings = [
            'stability' => (float) ($options['stability'] ?? config('services.elevenlabs.stability', 0.5)),
            'similarity_boost' => (float) ($options['similarity_boost'] ?? config('services.elevenlabs.similarity_boost', 0.75)),
            'style' => (float) ($options['style'] ?? config('services.elevenlabs.style', 0.0)),
            'use_speaker_boost' => (bool) ($options['use_speaker_boost'] ?? config('services.elevenlabs.use_speaker_boost', true)),
        ];

        // Speech rate (0.7 — 1.2). Sent only when configured, so older models
        // that reject the field keep working by default.
        $speed = $options['speed'] ?? config('services.elevenlabs.speed');
        if (filled($speed)) {
            $voiceSettings['speed'] = (float) $speed;
        }

        $payload = [
            'text' => trim($text),
            'model_id' => $model,
            'voice_settings' => $voiceSettings,
        ];

        $language = $options['language'] ?? config('services.elevenlabs.language');

        // eleven_multilingual_v2 detects the language on its own and rejects
        // language_code ("does not support language_code"), so only send it for
        // models that actually accept it (turbo / flash).
        if (filled($language) && ! str_contains($model, 'multilingual')) {
            $payload['language_code'] = (string) $language;
        }

        $endpoint = $this->baseUrl.'/text-to-speech/'.rawurlencode($voiceId).($withTimestamps ? '/with-timestamps' : '');

        $response = $this->http()->post($endpoint.'?'.http_build_query(['output_format' => $outputFormat]), $payload);

        if ($response->failed()) {
            Log::error('ElevenLabs request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            // Cloudflare's bot protection answers with a challenge page instead
            // of a JSON error — surface a clear hint instead of raw HTML.
            if ($this->isCloudflareChallenge($response->body())) {
                throw new RuntimeException(
                    'ElevenLabs-ը մերժեց հարցումը (Cloudflare 403)։ Ստուգիր ELEVENLABS_API_KEY-ը և ELEVENLABS_USER_AGENT-ը։'
                );
            }

            $response->throw();
        }

        if ($withTimestamps) {
            $data = $response->json() ?? [];
            $audio = base64_decode((string) ($data['audio_base64'] ?? ''), true);
            if ($audio === false || $audio === '') {
                throw new RuntimeException('ElevenLabs returned an empty audio payload.');
            }

            $alignment = (array) ($data['normalized_alignment'] ?? $data['alignment'] ?? []);
            $words = $this->alignmentToWords($text, $alignment);

            if ($words === []) {
                $words = $this->estimateWordsFromAudio($text, $audio, $outputFormat);
            }

            return ['audio' => $audio, 'words' => $words, 'alignment' => $alignment];
        }

        return [
            'audio' => $response->body(),
            'words' => $this->estimateWordsFromAudio($text, $response->body(), $outputFormat),
            'alignment' => [],
        ];
    }

    /**
     * Convert a character level alignment into word level timings.
     *
     * @param  array<string, mixed>  $alignment
     * @return array<int, array{word: string, start: float, end: float}>
     */
    public function alignmentToWords(string $text, array $alignment): array
    {
        $characters = $alignment['characters'] ?? [];
        $starts = $alignment['character_start_times_seconds'] ?? [];
        $ends = $alignment['character_end_times_seconds'] ?? [];

        if (! is_array($characters) || $characters === []) {
            return [];
        }

        if (count($characters) !== count($starts) || count($characters) !== count($ends)) {
            return [];
        }

        $wordList = WordTokenizer::split($text);
        $words = [];
        $index = 0;
        $current = null;

        foreach (array_values($characters) as $position => $character) {
            if (! is_string($character)) {
                continue;
            }

            if (WordTokenizer::isWhitespace($character)) {
                if ($current !== null) {
                    $words[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($current === null) {
                $current = [
                    'word' => $wordList[$index] ?? $character,
                    'start' => (float) $starts[$position],
                    'end' => (float) $ends[$position],
                ];
                $index++;
            } else {
                $current['end'] = (float) $ends[$position];
            }
        }

        if ($current !== null) {
            $words[] = $current;
        }

        return $words;
    }

    /**
     * Fallback: distribute the audio duration across the words proportionally
     * to their length. Used when the API does not return alignment data.
     *
     * @return array<int, array{word: string, start: float, end: float}>
     */
    public function estimateWordsFromAudio(string $text, string $audio, string $outputFormat = 'mp3_44100_128'): array
    {
        $bitrate = 128_000;
        if (preg_match('/mp3_\d+_(\d+)$/', $outputFormat, $matches) === 1) {
            $bitrate = ((int) $matches[1]) * 1000;
        }

        $duration = $bitrate > 0 ? (strlen($audio) * 8) / $bitrate : 0.0;

        return $this->distributeWords($text, $duration);
    }

    /**
     * Spread a duration across words proportionally to their character length.
     *
     * @return array<int, array{word: string, start: float, end: float}>
     */
    public function distributeWords(string $text, float $duration, float $startOffset = 0.0): array
    {
        $words = WordTokenizer::split($text);

        if ($words === []) {
            return [];
        }

        $weights = array_map(static fn (string $word): int => max(1, mb_strlen($word)), $words);
        $total = array_sum($weights) ?: 1;
        $cursor = $startOffset;
        $result = [];

        foreach ($words as $position => $word) {
            $span = $duration * ($weights[$position] / $total);
            $result[] = [
                'word' => $word,
                'start' => round($cursor, 2),
                'end' => round($cursor + $span, 2),
            ];
            $cursor += $span;
        }

        return $result;
    }

    /**
     * Build karaoke word timings from the poem's own recording.
     *
     *  - the poem has a real text → POST /forced-alignment (exact timing for
     *    the known text, works for archive recordings in any language);
     *  - no text yet → POST /speech-to-text with word timestamps, which also
     *    returns the transcript so the poem text can be filled automatically.
     *
     * @return array{words: array<int, array{word: string, start: float, end: float}>, loss: float|null, method: string, text: string|null}
     */
    public function alignFromPoemAudio(Poem $poem, ?string $language = null): array
    {
        $audio = $this->audioContents($poem);
        $fileName = basename((string) $poem->audio_path) ?: ($poem->slug ?: 'poem').'.mp3';

        if (PoemText::looksReal($poem->content)) {
            return $this->alignAudioToText($audio, $fileName, (string) $poem->content);
        }

        return $this->transcribeAudio($audio, $fileName, $language);
    }

    /**
     * Raw bytes of the active recording (archive or AI rendered) of a poem.
     */
    public function audioContents(Poem $poem): string
    {
        $path = (string) $poem->audio_path;
        $disk = Storage::disk($this->disk());

        if ($path === '' || ! $disk->exists($path)) {
            throw new RuntimeException('The audio file of the poem was not found on the "'.$this->disk().'" disk.');
        }

        return (string) $disk->get($path);
    }

    /**
     * POST /forced-alignment — timing information for a known transcript.
     *
     * @return array{words: array<int, array{word: string, start: float, end: float}>, loss: float|null, method: string, text: string|null}
     */
    public function alignAudioToText(string $audio, string $fileName, string $text): array
    {
        $this->guardConfigured(withVoice: false);

        $response = $this->http()
            ->attach('file', $audio, $fileName)
            ->post($this->baseUrl.'/forced-alignment', ['text' => trim($text)]);

        $data = $this->decodeOrFail($response, 'forced alignment');

        return [
            'words' => $this->normaliseApiWords($data['words'] ?? []),
            'loss' => isset($data['loss']) ? (float) $data['loss'] : null,
            'method' => 'alignment',
            'text' => null,
        ];
    }

    /**
     * POST /speech-to-text — transcribe a recording with word level timestamps.
     *
     * @return array{words: array<int, array{word: string, start: float, end: float}>, loss: float|null, method: string, text: string|null}
     */
    public function transcribeAudio(string $audio, string $fileName, ?string $language = null): array
    {
        $this->guardConfigured(withVoice: false);

        $payload = [
            'model_id' => (string) config('services.elevenlabs.stt_model', 'scribe_v2'),
            'timestamps_granularity' => 'word',
            'tag_audio_events' => 'false',
        ];

        $language = $language ?: config('services.elevenlabs.stt_language');

        if (filled($language)) {
            $payload['language_code'] = (string) $language;
        }

        $response = $this->http()
            ->attach('file', $audio, $fileName)
            ->post($this->baseUrl.'/speech-to-text', $payload);

        $data = $this->decodeOrFail($response, 'transcription');

        return [
            'words' => $this->normaliseApiWords($data['words'] ?? []),
            'loss' => null,
            'method' => 'transcription',
            'text' => filled($data['text'] ?? null) ? trim((string) $data['text']) : null,
        ];
    }

    /**
     * Speech to text / alignment only need the API key, not the voice id.
     *
     * @throws RuntimeException
     */
    protected function guardConfigured(bool $withVoice = true): void
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('ElevenLabs is not configured. Set ELEVENLABS_API_KEY.');
        }

        if ($withVoice && blank($this->voiceId)) {
            throw new RuntimeException('ElevenLabs voice id is not configured. Set ELEVENLABS_VOICE_ID.');
        }
    }

    /**
     * Decode a JSON response, surfacing API and Cloudflare errors clearly.
     *
     * @return array<string, mixed>
     */
    protected function decodeOrFail(Response $response, string $what): array
    {
        if ($response->failed()) {
            Log::error('ElevenLabs '.$what.' failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            if ($this->isCloudflareChallenge($response->body())) {
                throw new RuntimeException(
                    'ElevenLabs declined the request (Cloudflare 403). Check ELEVENLABS_API_KEY / ELEVENLABS_USER_AGENT, or route the calls through a proxy Worker via ELEVENLABS_BASE_URL.'
                );
            }

            throw new RuntimeException(
                'ElevenLabs '.$what.' failed with HTTP '.$response->status().': '.Str::limit($response->body(), 300)
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('ElevenLabs '.$what.' returned an unexpected (non-JSON) response.');
        }

        return $data;
    }

    /**
     * Map the API word list (alignment or Scribe) to {word, start, end} rows.
     *
     * Scribe also returns "spacing"/"audio_event" entries — those are skipped.
     *
     * @param  array<int|string, mixed>  $words
     * @return array<int, array{word: string, start: float, end: float}>
     */
    protected function normaliseApiWords(array $words): array
    {
        $result = [];

        foreach ($words as $word) {
            if (! is_array($word)) {
                continue;
            }

            if (isset($word['type']) && $word['type'] !== 'word') {
                continue;
            }

            $text = trim((string) ($word['text'] ?? $word['word'] ?? ''));

            if ($text === '') {
                continue;
            }

            $result[] = [
                'word' => $text,
                'start' => round((float) ($word['start'] ?? 0), 2),
                'end' => round((float) ($word['end'] ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * Remove the cached AI audio (and alignment) for a poem.
     */
    public function forget(Poem $poem): void
    {
        $disk = Storage::disk($this->disk());
        $audioPath = $this->pathForPoem($poem);

        foreach ([$audioPath, $this->alignmentPath($audioPath)] as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    protected function http(): PendingRequest
    {
        $options = array_filter([
            // Optional outbound proxy, see ELEVENLABS_PROXY.
            'proxy' => config('services.elevenlabs.proxy') ?: null,
        ]);

        // NB: do not set the "version" option here. Guzzle expects a string
        // ("2.0"), while CURL_HTTP_VERSION_2_0 === 3, which Guzzle interprets as
        // HTTP/3 and fails with "HTTP/3 is not supported by the cURL handler".
        // libcurl negotiates HTTP/2 over TLS via ALPN on its own.

        return Http::withHeaders([
            'xi-api-key' => (string) $this->apiKey,
            'Accept' => 'application/json, audio/mpeg',
            // Cloudflare rejects requests without a browser-like User-Agent.
            'User-Agent' => (string) config('services.elevenlabs.user_agent'),
            'Accept-Language' => 'en-US,en;q=0.9',
            'Origin' => 'https://elevenlabs.io',
            'Referer' => 'https://elevenlabs.io/',
        ])
            ->withOptions($options)
            ->timeout((int) config('services.elevenlabs.timeout', 180))
            ->retry(2, 1500, throw: false);
    }

    /**
     * Detect Cloudflare's "Just a moment..." challenge page.
     */
    protected function isCloudflareChallenge(string $body): bool
    {
        return str_contains($body, 'Just a moment')
            || str_contains($body, 'challenges.cloudflare.com');
    }

    protected function alignmentPath(string $audioPath): string
    {
        return preg_replace('/\.mp3$/i', '.json', $audioPath) ?: $audioPath.'.json';
    }

    /**
     * Persist word timings next to the audio file for later reuse.
     *
     * @param  array<int, array{word: string, start: float, end: float}>  $words
     */
    protected function storeAlignment(Poem $poem, array $words): void
    {
        if ($words === []) {
            return;
        }

        Storage::disk($this->disk())->put(
            $this->alignmentPath($this->pathForPoem($poem)),
            (string) json_encode(['words' => $words], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return array<int, array{word: string, start: float, end: float}>
     */
    protected function loadAlignment(Poem $poem): array
    {
        $disk = Storage::disk($this->disk());
        $path = $this->alignmentPath($this->pathForPoem($poem));

        if (! $disk->exists($path)) {
            return [];
        }

        $data = json_decode((string) $disk->get($path), true);

        return is_array($data['words'] ?? null) ? $data['words'] : [];
    }
}
