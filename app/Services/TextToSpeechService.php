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
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function synthesize(string $text, string $locale): string
    {
        $modelId = (string) config('services.elevenlabs.model_id');
        $voiceId = rawurlencode($this->voiceIdFor($locale));

        return Http::withHeaders(['xi-api-key' => (string) config('services.elevenlabs.key')])
            ->accept('audio/mpeg')
            ->timeout(60)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format=".self::OUTPUT_FORMAT, array_filter([
                'text' => $text,
                'model_id' => $modelId,
                'language_code' => in_array($modelId, self::MODELS_WITHOUT_LANGUAGE_CODE, true) ? null : Str::before($locale, '-'),
            ]))
            ->throw()
            ->body();
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
