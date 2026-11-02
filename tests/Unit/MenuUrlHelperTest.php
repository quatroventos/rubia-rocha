<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuUrlHelperTest extends TestCase
{
    #[DataProvider('externalHrefs')]
    public function test_detects_external_hrefs(string $href): void
    {
        $this->assertTrue(menu_href_is_external($href));
    }

    #[DataProvider('internalHrefs')]
    public function test_detects_internal_hrefs(string $href): void
    {
        $this->assertFalse(menu_href_is_external($href));
    }

    public function test_menu_url_keeps_absolute_external_addresses(): void
    {
        $this->assertSame('https://www.google.com', menu_url('https://www.google.com'));
        $this->assertSame('http://example.com/path', menu_url('http://example.com/path'));
        $this->assertSame('//cdn.example.com/a.js', menu_url('//cdn.example.com/a.js'));
        $this->assertSame('mailto:contato@example.com', menu_url('mailto:contato@example.com'));
        $this->assertSame('tel:+5511999999999', menu_url('tel:+5511999999999'));
    }

    public function test_menu_url_resolves_internal_paths_through_url_helper(): void
    {
        $this->assertSame(url('/'), menu_url('/'));
        $this->assertSame(url('/esg'), menu_url('/esg'));
        $this->assertSame(url('/quem-somos'), menu_url('quem-somos'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function externalHrefs(): array
    {
        return [
            'https' => ['https://www.googe.com'],
            'http' => ['http://example.com'],
            'protocol-relative' => ['//example.com'],
            'mailto' => ['mailto:a@b.com'],
            'tel' => ['tel:123'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function internalHrefs(): array
    {
        return [
            'root' => ['/'],
            'slug' => ['/esg'],
            'bare-slug' => ['quem-somos'],
            'hash' => ['#'],
            'empty' => [''],
        ];
    }
}
