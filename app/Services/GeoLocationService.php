<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoLocationService
{
    /**
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    public function locate(Request $request): array
    {
        $fromHeaders = $this->fromCloudflare($request);

        if ($this->hasCoordinates($fromHeaders) || filled($fromHeaders['city']) || filled($fromHeaders['country'])) {
            return $fromHeaders;
        }

        $ip = (string) $request->ip();

        if ($ip === '' || $this->isPrivate($ip)) {
            return $this->empty();
        }

        return Cache::remember('geo:'.$ip, now()->addDays(7), fn () => $this->lookup($ip));
    }

    /**
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    private function fromCloudflare(Request $request): array
    {
        $country = $this->cleanHeader($request->header('CF-IPCountry'));
        $region = $this->cleanHeader($request->header('CF-Region'));
        $city = $this->cleanHeader($request->header('CF-IPCity'));
        $latitude = $this->floatOrNull($request->header('CF-IPLatitude'));
        $longitude = $this->floatOrNull($request->header('CF-IPLongitude'));

        if ($country === 'XX' || $country === 'T1') {
            $country = null;
        }

        return [
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    private function lookup(string $ip): array
    {
        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->get('http://ip-api.com/json/'.$ip, [
                    'fields' => 'status,country,regionName,city,lat,lon',
                ]);

            if (! $response->ok() || $response->json('status') !== 'success') {
                return $this->empty();
            }

            return [
                'country' => $this->cleanHeader($response->json('country')),
                'region' => $this->cleanHeader($response->json('regionName')),
                'city' => $this->cleanHeader($response->json('city')),
                'latitude' => $this->floatOrNull($response->json('lat')),
                'longitude' => $this->floatOrNull($response->json('lon')),
            ];
        } catch (Throwable) {
            return $this->empty();
        }
    }

    /**
     * @param  array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}  $location
     */
    private function hasCoordinates(array $location): bool
    {
        return $location['latitude'] !== null && $location['longitude'] !== null;
    }

    private function isPrivate(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function cleanHeader(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    private function empty(): array
    {
        return [
            'country' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
