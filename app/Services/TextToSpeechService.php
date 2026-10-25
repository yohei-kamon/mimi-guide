<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TextToSpeechService
{
    /**
     * The audio format requested from ElevenLabs. It is saved as audio/mpeg.
     */
    public const OUTPUT_FORMAT = 'mp3_44100_128';

    /**
     * The length of the silence added before the speech.
     */
    public const LEADING_SILENCE_SECONDS = 0.5;

    /**
     * The header of a silent frame in the output format: MPEG-1 Layer III, 128kbps, 44.1kHz, mono.
     */
    private const SILENT_FRAME_HEADER = "\xFF\xFB\x90\xC4";

    /**
     * The byte length of one 128kbps 44.1kHz frame without padding (144 * 128000 / 44100).
     */
    private const FRAME_BYTES = 417;

    /**
     * The number of samples in one MPEG-1 Layer III frame.
     */
    private const SAMPLES_PER_FRAME = 1152;

    /**
     * The sample rate of the output format.
     */
    private const SAMPLE_RATE = 44100;

    /**
     * The models that reject `language_code`. They detect the language from the text instead.
     *
     * @var list<string>
     */
    private const MODELS_WITHOUT_LANGUAGE_CODE = ['eleven_multilingual_v2'];

    /**
     * Convert the text in the given locale to speech with ElevenLabs and return the MP3 audio.
     *
     * The locale is sent as an ISO 639-1 code (`zh-Hans` becomes `zh`) when the model supports it.
     * Japanese uses its own voice when one is configured; the other locales use the default voice.
     * About 0.5 seconds of silence is added before the speech.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function synthesize(string $text, string $locale): string
    {
        $modelId = (string) config('services.elevenlabs.model_id');
        $voiceId = rawurlencode($this->voiceIdFor($locale));

        $audio = Http::withHeaders(['xi-api-key' => (string) config('services.elevenlabs.key')])
            ->accept('audio/mpeg')
            ->timeout(60)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format=".self::OUTPUT_FORMAT, array_filter([
                'text' => $text,
                'model_id' => $modelId,
                'language_code' => in_array($modelId, self::MODELS_WITHOUT_LANGUAGE_CODE, true) ? null : Str::before($locale, '-'),
            ]))
            ->throw()
            ->body();

        return $this->withLeadingSilence($audio);
    }

    /**
     * Insert silent MP3 frames before the first audio frame, after the ID3v2 tag when there is one.
     */
    private function withLeadingSilence(string $audio): string
    {
        $frameCount = (int) ceil(self::LEADING_SILENCE_SECONDS * self::SAMPLE_RATE / self::SAMPLES_PER_FRAME);
        $silence = str_repeat(str_pad(self::SILENT_FRAME_HEADER, self::FRAME_BYTES, "\0"), $frameCount);

        $tagLength = 0;

        if (str_starts_with($audio, 'ID3') && strlen($audio) >= 10) {
            $size = array_reduce(str_split(substr($audio, 6, 4)), fn (int $carry, string $byte): int => ($carry << 7) | (ord($byte) & 0x7F), 0);
            $tagLength = min(10 + $size, strlen($audio));
        }

        return substr($audio, 0, $tagLength).$silence.substr($audio, $tagLength);
    }

    /**
     * Pick the Japanese voice for Japanese, falling back to the default voice when it is not configured.
     */
    private function voiceIdFor(string $locale): string
    {
        $japaneseVoiceId = config('services.elevenlabs.japanese_voice_id');

        if (Str::before($locale, '-') === 'ja' && filled($japaneseVoiceId)) {
            return (string) $japaneseVoiceId;
        }

        return (string) config('services.elevenlabs.voice_id');
    }
}
