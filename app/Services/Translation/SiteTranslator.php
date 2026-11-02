<?php

namespace App\Services\Translation;

use App\Cms\FormDefinition;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\SystemOptions;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;

class SiteTranslator
{
    /**
     * @var list<string>
     */
    private const SETTINGS_FIELDS = ['tagline'];

    /**
     * @var list<string>
     */
    private const FORM_KEYS = ['label', 'hint', 'placeholder', 'submit_label', 'lgpd_label', 'title', 'description', 'success_message'];

    public function __construct(private TextTranslator $translator, private SystemOptions $options) {}

    /**
     * Recria os menus do idioma a partir dos menus pt-BR, com rótulos traduzidos.
     * Os hrefs ficam em pt-BR; o PublicContent troca pelos caminhos traduzidos na renderização.
     */
    public function menus(string $locale): int
    {
        $items = MenuItem::query()->where('locale', Locales::default())->orderBy('sort')->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $labels = $items->mapWithKeys(fn (MenuItem $item) => ['m'.$item->id => (string) $item->label])->all();
        $translated = $this->translator->translate($labels, $locale, 'Navigation menu labels. Keep them short (1 to 4 words).');

        $pageIds = Page::query()->where('locale', $locale)->whereNotNull('translation_of')->pluck('id', 'translation_of');

        DB::transaction(function () use ($items, $translated, $locale, $pageIds) {
            MenuItem::query()->where('locale', $locale)->whereNotNull('parent_id')->delete();
            MenuItem::query()->where('locale', $locale)->delete();

            $map = [];
            foreach ($items->sortBy(fn (MenuItem $item) => $item->parent_id === null ? 0 : 1) as $item) {
                $copy = MenuItem::query()->create([
                    'locale' => $locale,
                    'group' => $item->group,
                    'label' => $translated['m'.$item->id] ?? $item->label,
                    'href' => $item->href,
                    'page_id' => $item->page_id !== null ? ($pageIds[$item->page_id] ?? null) : null,
                    'parent_id' => $item->parent_id !== null ? ($map[$item->parent_id] ?? null) : null,
                    'sort' => $item->sort,
                    'is_active' => $item->is_active,
                ]);
                $map[$item->id] = $copy->id;
            }
        });

        $this->options->flush();

        return $items->count();
    }

    public function settings(string $locale): void
    {
        $site = SiteSetting::query()->first();
        if ($site === null) {
            return;
        }

        $strings = [];
        foreach (self::SETTINGS_FIELDS as $field) {
            $value = (string) $site->getAttribute($field);
            if (ContentStrings::isText($value)) {
                $strings[$field] = $value;
            }
        }

        $formTexts = array_values(array_unique(self::formTexts(FormDefinition::withOptions('contact') ?? [])));
        foreach ($formTexts as $index => $value) {
            $strings['form.'.$index] = $value;
        }

        $translated = $this->translator->translate($strings, $locale, 'Company tagline and contact form labels.');

        $formStrings = [];
        foreach ($formTexts as $index => $value) {
            if (isset($translated['form.'.$index])) {
                $formStrings[$value] = $translated['form.'.$index];
            }
            unset($translated['form.'.$index]);
        }

        $all = is_array($site->translations) ? $site->translations : [];
        $all[$locale] = array_merge($all[$locale] ?? [], $translated, ['form_strings' => $formStrings]);
        $site->forceFill(['translations' => $all])->save();

        $this->options->flush();
    }

    /**
     * @param  array<int|string, mixed>  $form
     * @return list<string>
     */
    public static function formTexts(array $form): array
    {
        $texts = [];
        array_walk_recursive($form, function ($value, $key) use (&$texts) {
            if (is_string($key) && in_array($key, self::FORM_KEYS, true) && is_string($value) && ContentStrings::isText($value)) {
                $texts[] = $value;
            }
        });

        return $texts;
    }

    /**
     * @param  array<int|string, mixed>  $form
     * @param  array<string, string>  $dictionary
     * @return array<int|string, mixed>
     */
    public static function localizeForm(array $form, array $dictionary): array
    {
        if ($dictionary === []) {
            return $form;
        }

        foreach ($form as $key => $value) {
            if (is_array($value)) {
                $form[$key] = self::localizeForm($value, $dictionary);
            } elseif (is_string($key) && in_array($key, self::FORM_KEYS, true) && is_string($value) && isset($dictionary[$value])) {
                $form[$key] = $dictionary[$value];
            }
        }

        return $form;
    }
}
