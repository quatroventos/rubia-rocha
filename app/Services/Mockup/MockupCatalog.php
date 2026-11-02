<?php

namespace App\Services\Mockup;

final class MockupCatalog
{
    /**
     * @return array<string, array{label: string, device: string, file: string}>
     */
    public static function references(): array
    {
        return [
            'tripfy' => [
                'label' => 'iPhone no trilho',
                'device' => 'iphone',
                'file' => 'tripfy.jpg',
            ],
            'conexme' => [
                'label' => 'iPhone em pé',
                'device' => 'iphone',
                'file' => 'conexme.jpg',
            ],
            'assessment' => [
                'label' => 'iPad na chapa',
                'device' => 'ipad',
                'file' => 'assessment.jpg',
            ],
            'afbrasilia' => [
                'label' => 'MacBook',
                'device' => 'macbook',
                'file' => 'afbrasilia.jpg',
            ],
            'afcuritiba' => [
                'label' => 'Studio Display',
                'device' => 'studio-display',
                'file' => 'afcuritiba.jpg',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function devices(): array
    {
        return [
            'iphone' => 'iPhone',
            'ipad' => 'iPad',
            'macbook' => 'MacBook',
            'studio-display' => 'Studio Display',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function angles(): array
    {
        return [
            'frontal' => 'Quase frontal',
            'three-quarter-left' => '3/4 esquerda',
            'three-quarter-right' => '3/4 direita',
            'top' => 'De cima',
            'standing' => 'Em pé',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function scenes(): array
    {
        return [
            'rail' => 'Trilho com grampo',
            'perforated' => 'Chapa perfurada',
            'grate' => 'Grelha',
            'plain' => 'Superfície lisa',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sizes(): array
    {
        return [
            '2048x1152' => '2048×1152',
            '2560x1440' => '2560×1440',
            '1536x1024' => '1536×1024',
            '1152x2048' => '1152×2048',
            '1440x2560' => '1440×2560',
            '1024x1536' => '1024×1536',
        ];
    }

    /**
     * Viewport CSS do aparelho. A print sai em width×scale por height×scale.
     *
     * @return array<string, array{label: string, width: int, height: int, scale: int, user_agent: string}>
     */
    public static function viewports(): array
    {
        return [
            'iphone' => [
                'label' => '393×852 @3x',
                'width' => 393,
                'height' => 852,
                'scale' => 3,
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1',
            ],
            'ipad' => [
                'label' => '834×1194 @2x',
                'width' => 834,
                'height' => 1194,
                'scale' => 2,
                'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1',
            ],
            'macbook' => [
                'label' => '1512×982 @2x',
                'width' => 1512,
                'height' => 982,
                'scale' => 2,
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15',
            ],
            'studio-display' => [
                'label' => '2560×1440 @2x',
                'width' => 2560,
                'height' => 1440,
                'scale' => 2,
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function qualities(): array
    {
        return [
            'medium' => 'Média',
            'high' => 'Alta',
            'xhigh' => 'Muito alta',
        ];
    }

    public static function referencePath(string $id): string
    {
        $file = self::references()[$id]['file'] ?? null;

        if ($file === null) {
            throw new \InvalidArgumentException('Referência inválida.');
        }

        return public_path('assets/cases/'.$file);
    }

    /**
     * @return list<array{id: string, label: string, device: string, src: string}>
     */
    public static function referenceCards(): array
    {
        $cards = [];

        foreach (self::references() as $id => $reference) {
            $cards[] = [
                'id' => $id,
                'label' => $reference['label'],
                'device' => $reference['device'],
                'src' => '/assets/cases/'.$reference['file'],
            ];
        }

        return $cards;
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{id: string, label: string}>
     */
    public static function options(array $map): array
    {
        $options = [];

        foreach ($map as $id => $label) {
            $options[] = ['id' => $id, 'label' => $label];
        }

        return $options;
    }
}
