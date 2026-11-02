<?php

namespace App\Support;

class SiteIcons
{
    public static function value(int $index): string
    {
        return self::pick('value', $index);
    }

    public static function purpose(int $index): string
    {
        return self::pick('purpose', $index);
    }

    public static function feature(int $index): string
    {
        return self::pick('feature', $index);
    }

    public static function specialty(int $index): string
    {
        return self::pick('specialty', $index);
    }

    public static function techMini(int $index): string
    {
        return self::pick('techMini', $index);
    }

    public static function techWide(int $index): string
    {
        return self::pick('techWide', $index);
    }

    private static function pick(string $group, int $index): string
    {
        $icons = self::catalog()[$group];
        if ($icons === []) {
            return '';
        }

        return $icons[$index % count($icons)];
    }

    /** @return array<string, list<string>> */
    private static function catalog(): array
    {
        return [
        'value' => [
            '<svg viewBox="0 0 48 48"> <circle cx="18" cy="17" r="7"></circle> <circle cx="33" cy="19" r="5"></circle> <path d="M6 39c2.5-8 7-12 12-12s9.5 4 12 12"></path> <path d="M28 31c3.8.4 7 3.3 9 8"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <circle cx="24" cy="24" r="17"></circle> <path d="M7 24h34"></path> <path d="M24 7c5 5 7.5 10.5 7.5 17S29 36 24 41"></path> <path d="M24 7c-5 5-7.5 10.5-7.5 17S19 36 24 41"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M17 30c-3-2.6-5-6.2-5-10a12 12 0 0 1 24 0c0 3.8-2 7.4-5 10" ></path> <path d="M18 34h12"></path> <path d="M20 39h8"></path> <path d="M21 30h6"></path> <path d="M24 4v4"></path> <path d="M40 20h4"></path> <path d="M4 20h4"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M24 39s15-8.8 15-22a8 8 0 0 0-14-5.2L24 13l-1-1.2A8 8 0 0 0 9 17c0 13.2 15 22 15 22z" ></path> <path d="M16 24h5l3-7 4 13 3-6h4"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M24 5 38 11v11c0 9.5-5.6 16.6-14 21-8.4-4.4-14-11.5-14-21V11z" ></path> <path d="m16 24 5 5 11-12"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M10 14h28"></path> <path d="M10 24h22"></path> <path d="M10 34h14"></path> <path d="M38 24l-7 7"></path> <path d="M31 17l7 7"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M12 10h24v8c0 8-5.4 14-12 14s-12-6-12-14z"></path> <path d="M12 14H7v4c0 5 3.2 8 8 8"></path> <path d="M36 14h5v4c0 5-3.2 8-8 8"></path> <path d="M24 32v7"></path> <path d="M17 41h14"></path> </svg>',
            '<svg viewBox="0 0 48 48"> <path d="M24 13c-4-4-9-5-15-5v28c6 0 11 1 15 5"></path> <path d="M24 13c4-4 9-5 15-5v28c-6 0-11 1-15 5"></path> <path d="M24 13v28"></path> </svg>',
        ],
        'purpose' => [
            '<svg viewBox="0 0 32 32"> <path d="M16 5v4" /> <path d="M16 23v4" /> <path d="M5 16h4" /> <path d="M23 16h4" /> <path d="m8.8 8.8 2.8 2.8" /> <path d="m20.4 20.4 2.8 2.8" /> <path d="m23.2 8.8-2.8 2.8" /> <path d="m11.6 20.4-2.8 2.8" /> <circle cx="16" cy="16" r="6" /> </svg>',
            '<svg viewBox="0 0 32 32"> <path d="M6 25h20" /> <path d="M8 21v-5" /> <path d="M14 21v-9" /> <path d="M20 21V8" /> <path d="M6 14l6-5 5 4 8-7" /> <path d="M23 6h3v3" /> </svg>',
            '<svg viewBox="0 0 32 32"> <path d="M24 11a9 9 0 0 0-15-3l-3 3" /> <path d="M6 6v5h5" /> <path d="M8 21a9 9 0 0 0 15 3l3-3" /> <path d="M26 26v-5h-5" /> </svg>',
        ],
        'feature' => [
            '<svg viewBox="0 0 24 24"> <path d="M8 12h8"></path> <path d="M12 8v8"></path> <path d="M7 4h10l3 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8z" ></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M4 20h16"></path> <path d="M7 20V8h10v12"></path> <path d="M9 11h2"></path> <path d="M13 11h2"></path> <path d="M9 15h2"></path> <path d="M13 15h2"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z" ></path> <circle cx="12" cy="10" r="2.5"></circle> </svg>',
        ],
        'specialty' => [
            '<svg viewBox="0 0 24 24"> <path d="M8 7h8"></path> <path d="M8 11h8"></path> <path d="M8 15h5"></path> <path d="M6 3h9l3 3v15H6z"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M7 4h10v16H7z"></path> <path d="M10 8h4"></path> <path d="M10 12h4"></path> <path d="M10 16h2"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M4 20h16"></path> <path d="M6 20V9l6-4 6 4v11"></path> <path d="M10 20v-6h4v6"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M12 3 4 7v6c0 5 3.5 7.5 8 8 4.5-.5 8-3 8-8V7z" ></path> <path d="m9 12 2 2 4-4"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M4 19V5"></path> <path d="M4 19h16"></path> <path d="m7 15 4-4 3 3 5-7"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M4 7h16v10H4z"></path> <path d="M8 21h8"></path> <path d="M12 17v4"></path> </svg>',
        ],
        'techMini' => [
            '<svg viewBox="0 0 24 24"> <path d="M4 19V5"></path> <path d="M4 19h16"></path> <path d="M8 15v-4"></path> <path d="M12 15V8"></path> <path d="M16 15v-6"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M12 3v18"></path> <path d="M4 12h16"></path> <path d="M6 6l12 12"></path> <path d="M18 6 6 18"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M5 7h14"></path> <path d="M5 12h14"></path> <path d="M5 17h14"></path> <path d="M8 5v14"></path> <path d="M16 5v14"></path> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M4 5h16v11H4z"></path> <path d="M8 20h8"></path> <path d="M12 16v4"></path> </svg>',
        ],
        'techWide' => [
            '<svg viewBox="0 0 24 24"> <path d="M8 2v4"></path> <path d="M16 2v4"></path> <path d="M8 18v4"></path> <path d="M16 18v4"></path> <path d="M2 8h4"></path> <path d="M18 8h4"></path> <path d="M2 16h4"></path> <path d="M18 16h4"></path> <rect x="6" y="6" width="12" height="12" rx="2"></rect> </svg>',
            '<svg viewBox="0 0 24 24"> <path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z" ></path> <path d="M12 8v5"></path> <path d="M12 16h.01"></path> </svg>',
        ],
        ];
    }
}
