<?php

namespace App\Services\Mockup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeviceSceneGenerator
{
    /**
     * @param  array{
     *     device: string,
     *     angle: string,
     *     scene: string,
     *     size: string,
     *     quality: string,
     *     references: list<string>,
     *     note?: string|null
     * }  $input
     */
    public function generate(array $input): string
    {
        $attachments = [];

        foreach ($input['references'] as $id) {
            $path = MockupCatalog::referencePath($id);

            if (! is_file($path)) {
                throw new RuntimeException('Referência ausente no disco: '.$id.'.');
            }

            $attachments[] = [
                'binary' => (string) file_get_contents($path),
                'filename' => basename($path),
                'mime' => str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg',
            ];
        }

        return $this->edit($attachments, $this->prompt($input), $input['size'], $input['quality']);
    }

    /**
     * @param  array{motion: string, device: string, size: string, quality: string, step: int, steps: int, note?: string|null}  $input
     */
    public function advance(string $frame, array $input): string
    {
        return $this->edit(
            [['binary' => $frame, 'filename' => 'frame.png', 'mime' => 'image/png']],
            $this->motionPrompt($input),
            $input['size'],
            $input['quality'],
        );
    }

    /**
     * @param  list<array{binary: string, filename: string, mime: string}>  $attachments
     */
    private function edit(array $attachments, string $prompt, string $size, string $quality): string
    {
        $key = config('services.meta.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('META_API_KEY não está no .env.');
        }

        $request = Http::withToken($key)
            ->connectTimeout(15)
            ->timeout((int) config('services.meta.image_timeout', 180));

        foreach ($attachments as $index => $attachment) {
            $request = $request->attach(
                'image['.$index.']',
                $attachment['binary'],
                $attachment['filename'],
                ['Content-Type' => $attachment['mime']],
            );
        }

        try {
            $response = $request->post($this->endpoint(), [
                'model' => (string) config('services.meta.image_model'),
                'prompt' => $prompt,
                'size' => $size,
                'output_format' => 'png',
                'reasoning_strength' => in_array($quality, ['high', 'xhigh'], true) ? 'high' : 'low',
                'n' => '1',
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('A Meta não respondeu a tempo.');
        }

        if ($response->failed()) {
            $message = (string) ($response->json('error.message') ?? $response->body());

            throw new RuntimeException('Meta '.$response->status().': '.mb_substr($message, 0, 300));
        }

        $encoded = $response->json('data.0.b64_json');
        $binary = is_string($encoded) ? base64_decode($encoded, true) : false;

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('A Meta não devolveu imagem.');
        }

        return $binary;
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.meta.base_url'), '/').'/images/edits';
    }

    /**
     * @param  array{
     *     device: string,
     *     angle: string,
     *     scene: string,
     *     size: string,
     *     quality: string,
     *     references: list<string>,
     *     note?: string|null
     * }  $input
     */
    private function prompt(array $input): string
    {
        [$width, $height] = array_map('intval', explode('x', $input['size']));
        $orientation = $height > $width ? 'portrait orientation' : 'landscape orientation';
        $note = trim(strip_tags((string) ($input['note'] ?? '')));

        $devices = [
            'iphone' => 'a modern black smartphone with flat edges, thin bezels and a small camera island',
            'ipad' => 'a modern black tablet with thin bezels',
            'macbook' => 'a space-gray laptop, open, with the keyboard and trackpad visible and thin screen bezels',
            'studio-display' => 'an Apple Studio Display on its aluminum stand: a 27-inch monitor with a thin black glass, a silver chin and no keyboard',
        ];
        $angles = [
            'frontal' => 'viewed nearly straight on, only a few degrees of turn',
            'three-quarter-left' => 'three-quarter view from the left',
            'three-quarter-right' => 'three-quarter view from the right',
            'top' => 'high angle looking down at the device',
            'standing' => 'the device standing upright on the surface',
        ];
        $scenes = [
            'rail' => 'The device rests on a black machined metal rail with a clamp. A thin red light line runs along the rail.',
            'perforated' => 'The device lies on a black perforated metal shelf. A thin red edge light is visible.',
            'grate' => 'The device stands next to a dark metal floor grate. A thin red accent line sits in the set.',
            'plain' => 'The device sits on a smooth dark surface. A thin red accent line sits in the set.',
        ];

        $lines = [
            'Create a new photorealistic studio photograph in the same material and lighting language as the reference images: dark charcoal set, soft directional light, black metal, one thin red accent, no people.',
            $input['device'] === 'studio-display'
                ? 'Copy the monitor hardware from the reference. It is an Apple Studio Display: one flat rectangular panel, not curved, not bent, and not an iMac. Thin black bezel of even width on all four sides. A small circular camera sits flush in the top bezel, centered, not a webcam perched above the frame and not a notch. The stand is the one in the reference: a short center neck and a flat rectangular aluminum foot on the surface. Not a curved iMac arm, not a round base, not a pole, not a laptop.'
                : 'The references are style only. Do not copy their screen content, logos, or exact crop.',
            'Device: '.($devices[$input['device']] ?? 'a black device').', '.($angles[$input['angle']] ?? 'three-quarter view').', '.$orientation.'.',
            $scenes[$input['scene']] ?? 'Dark studio surface.',
            $this->screenInstruction($input['device']),
            $input['device'] === 'studio-display'
                ? 'Do not copy the website or the logos on the reference screen. You may change the angle and the set. Do not redesign the monitor. No interface, no icons, no text and no wallpaper. The only magenta in the picture is the flat lit display. The glass is one rigid plane.'
                : 'No interface, no icons, no text, no wallpaper and no colored frame. The only magenta in the picture is the flat lit display. Frame the device large. New composition, not a duplicate of a reference.',
        ];

        if ($note !== '') {
            $lines[] = 'Additional direction: '.$note;
        }

        return implode("\n", $lines);
    }

    private function screenInstruction(string $device): string
    {
        return match ($device) {
            'iphone' => 'The lit display is a perfectly flat solid magenta #FF00FF and stops at a realistic corner radius. A black dynamic island with the front camera sits at the top center of the glass. The island and the rounded corners are physical black hardware, not magenta.',
            'macbook' => 'The lit display is a perfectly flat solid magenta #FF00FF with slightly rounded corners. A black webcam notch is cut into the top center of the display. The notch and the corner radius are aluminum, not magenta.',
            'studio-display' => 'The lit display is a perfectly flat solid magenta #FF00FF. The panel is not curved. A small black camera is flush in the top center of the bezel, outside the magenta. The bezel and the stand are metal, not magenta.',
            default => 'The lit display is a perfectly flat solid magenta #FF00FF rounded rectangle. The corner radius is black bezel, not magenta. The front camera stays in the bezel, outside the magenta.',
        };
    }

    /**
     * @param  array{motion: string, device: string, size: string, quality: string, step: int, steps: int, note?: string|null}  $input
     */
    private function motionPrompt(array $input): string
    {
        $step = (int) $input['step'];
        $steps = (int) $input['steps'];
        $degrees = max(4, (int) round(48 / max($steps, 1)));
        $note = trim(strip_tags((string) ($input['note'] ?? '')));

        $move = match ($input['motion']) {
            'open' => match ($input['device']) {
                'macbook' => 'Open the laptop lid a little more, one small step from a more closed angle toward open. Do not jump the lid to fully open.',
                'studio-display' => 'Tilt the monitor a few degrees on its stand. Do not detach the stand and do not turn it into a laptop.',
                default => 'Raise the device a little more toward upright, one small step from lying on the surface toward standing. Do not jump to a new pose.',
            },
            'flat' => 'Do not move the device, the camera, or the set. Replace only the display contents.',
            default => 'Rotate the device a little in place, about '.$degrees.' degrees to its right. Do not orbit the camera and do not change the crop.',
        };

        $lines = [
            'Edit this photograph in place. Do not create a new scene and do not replace the device.',
            'Keep the same device, materials, color, studio, lighting, red accent, camera distance and framing.',
            $move,
            'This is photo '.$step.' of '.$steps.' of one continuous move.',
            'The entire display must be a perfectly flat solid magenta #FF00FF, edge to edge inside the bezel. No interface, no icons, no text, no wallpaper, no window chrome, and no reflection that breaks the flat magenta.',
            'Photorealistic product photograph, no people.',
        ];

        if ($note !== '') {
            $lines[] = 'Additional direction: '.$note;
        }

        return implode("\n", $lines);
    }
}
