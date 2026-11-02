<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactSubmission extends Model
{
    use SoftDeletes;

    private const NAME_PARTICLES = ['da', 'das', 'de', 'del', 'di', 'do', 'dos', 'du', 'e', 'van', 'von'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }

    /**
     * Nome digitado todo em minúsculas ou maiúsculas vira "Maria da Silva"; caixa mista é mantida.
     */
    public static function formatName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '' || ($name !== mb_strtolower($name) && $name !== mb_strtoupper($name))) {
            return $name;
        }

        $words = explode(' ', mb_convert_case($name, MB_CASE_TITLE));

        foreach ($words as $index => $word) {
            if ($index > 0 && in_array(mb_strtolower($word), self::NAME_PARTICLES, true)) {
                $words[$index] = mb_strtolower($word);
            }
        }

        return implode(' ', $words);
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : self::formatName($value));
    }
}
