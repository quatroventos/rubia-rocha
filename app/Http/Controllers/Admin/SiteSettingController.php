<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\MailSettings;
use App\Services\WhatsApp\ContactWhatsApp;
use App\Services\WhatsApp\EvolutionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('view', $settings);

        return response()->json([
            'data' => $settings,
            'whatsapp_defaults' => [
                'admin_template' => ContactWhatsApp::DEFAULT_ADMIN_TEMPLATE,
                'visitor_template' => ContactWhatsApp::DEFAULT_VISITOR_TEMPLATE,
                'variables' => ContactWhatsApp::VARIABLES,
            ],
            'hosting_plans' => config('cms.hosting_plans'),
        ]);
    }

    public function testWhatsApp(): JsonResponse
    {
        $settings = SiteSetting::query()->firstOrFail();
        $this->authorize('update', $settings);

        $client = EvolutionClient::fromSettings($settings);
        $number = ContactWhatsApp::normalizeNumber($settings->whatsapp_notify_number);

        if (! $client->configured() || $number === null) {
            throw ValidationException::withMessages([
                'evolution_url' => 'Salve a URL, a apikey, a instância e o número que recebe os avisos antes de testar.',
            ]);
        }

        try {
            $state = $client->connectionState();

            if ($state !== 'open') {
                throw new RuntimeException('A instância "'.$settings->evolution_instance.'" não está conectada (estado: '.$state.').');
            }

            $client->sendText($number, 'Teste do site '.($settings->name ?: config('app.name')).': os avisos de formulário por WhatsApp estão funcionando.');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['evolution_url' => $e->getMessage()]);
        }

        return response()->json(['status' => 'sent', 'state' => $state]);
    }

    public function update(Request $request, MailSettings $mailSettings): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('update', $settings);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'favicon_path' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:64'],
            'phone_href' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'whatsapp_url' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string'],
            'map_embed_url' => ['nullable', 'string'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'linkedin_url' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'string', 'max:500'],
            'og_site_name' => ['nullable', 'string', 'max:255'],
            'og_locale' => ['nullable', 'string', 'max:16'],
            'twitter_card' => ['nullable', 'string', 'max:64'],
            'smtp_enabled' => ['sometimes', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'evolution_url' => ['nullable', 'url', 'max:255'],
            'evolution_api_key' => ['nullable', 'string', 'max:255'],
            'evolution_instance' => ['nullable', 'string', 'max:120'],
            'whatsapp_notify_number' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s()+.-]{10,}$/'],
            'whatsapp_admin_template' => ['nullable', 'string', 'max:2000'],
            'whatsapp_visitor_template' => ['nullable', 'string', 'max:2000'],
            'plan_links' => ['nullable', 'array'],
            'plan_links.*' => ['nullable', 'url:http,https', 'max:500'],
            'client_logos' => ['nullable', 'array', 'max:200'],
            'client_logos.*.name' => ['nullable', 'string', 'max:120'],
            'client_logos.*.image' => ['required', 'string', 'max:500'],
            'client_logos.*.image_alt' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('client_logos', $data)) {
            $data['client_logos'] = collect($data['client_logos'] ?? [])
                ->map(fn (array $logo) => [
                    'name' => trim((string) ($logo['name'] ?? '')),
                    'image' => trim((string) $logo['image']),
                    'image_alt' => trim((string) ($logo['image_alt'] ?? '')),
                ])
                ->values()
                ->all();
        }

        if (array_key_exists('plan_links', $data)) {
            $data['plan_links'] = collect($data['plan_links'] ?? [])
                ->only(array_keys((array) config('cms.hosting_plans')))
                ->map(fn ($url) => trim((string) $url))
                ->filter()
                ->all();
        }

        foreach (['smtp_password', 'evolution_api_key'] as $secret) {
            if (! array_key_exists($secret, $data) || $data[$secret] === null || $data[$secret] === '') {
                unset($data[$secret]);
            }
        }

        if (($data['smtp_encryption'] ?? null) === '') {
            $data['smtp_encryption'] = null;
        }

        $settings->update($data);
        $mailSettings->apply($settings->fresh());

        return response()->json(['data' => $settings->fresh()]);
    }

    public function tags(): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('view', $settings);

        return response()->json([
            'data' => $settings->only(['tag_header', 'tag_body', 'tag_footer']),
        ]);
    }

    public function updateTags(Request $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('update', $settings);

        $data = $request->validate([
            'tag_header' => ['nullable', 'string'],
            'tag_body' => ['nullable', 'string'],
            'tag_footer' => ['nullable', 'string'],
        ]);

        $settings->update($data);

        return response()->json([
            'data' => $settings->fresh()->only(['tag_header', 'tag_body', 'tag_footer']),
        ]);
    }

    public function sitemap(): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('view', $settings);

        return response()->json([
            'data' => ['sitemap_extra' => $settings->sitemap_extra ?? []],
        ]);
    }

    public function updateSitemap(Request $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('update', $settings);

        $data = $request->validate([
            'sitemap_extra' => ['nullable', 'array'],
            'sitemap_extra.*' => ['string', 'max:500'],
        ]);

        $settings->update(['sitemap_extra' => $data['sitemap_extra'] ?? []]);

        return response()->json([
            'data' => ['sitemap_extra' => $settings->fresh()->sitemap_extra ?? []],
        ]);
    }
}
