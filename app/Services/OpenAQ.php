<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class OpenAQ
{
    private string $base;

    public function __construct()
    {
        $this->base = rtrim(config('services.openaq.base'), '/');
    }

    private function get(string $path, array $query = [], int $ttl = 60): array
    {
        $key = 'openaq:'.md5($path.json_encode($query));
        return Cache::remember($key, $ttl, function () use ($path, $query) {
            $res = Http::withHeaders(['X-API-Key' => config('services.openaq.key')])
                ->get($this->base.$path, $query)
                ->throw();

            return [
                'data' => $res->json(),
                'headers' => [
                    'used'      => $res->header('x-ratelimit-used'),
                    'remaining' => $res->header('x-ratelimit-remaining'),
                    'reset'     => $res->header('x-ratelimit-reset'),
                ],
            ];
        });
    }

    // ---- OpenAQ v3: Locations ----
    public function locations(array $q): array                 { return $this->get('/v3/locations', $q); }
    public function location(int $id): array                   { return $this->get("/v3/locations/{$id}"); }
    public function latest(int $locationId): array             { return $this->get("/v3/locations/{$locationId}/latest"); }
    public function sensorsAt(int $locationId): array          { return $this->get("/v3/locations/{$locationId}/sensors"); }
    public function locationFlags(int $locationId): array      { return $this->get("/v3/locations/{$locationId}/flags"); }

    // ---- Sensors & Series ----
    public function sensors(array $q = []): array            { return $this->get('/v3/sensors', $q); }
    public function sensor(int $sensorId): array               { return $this->get("/v3/sensors/{$sensorId}"); }
    public function sensorFlags(int $sensorId): array          { return $this->get("/v3/sensors/{$sensorId}/flags"); }

    // Raw measurements (fine-grained)
    public function measurements(int $sensorId, array $q): array
    {
        // e.g. ['datetime_from'=>'2025-10-01','datetime_to'=>'2025-10-19','limit'=>1000]
        return $this->get("/v3/sensors/{$sensorId}/measurements", $q);
    }

    // Aggregations (preferred for charts)
    public function hours(int $sensorId, array $q): array      { return $this->get("/v3/sensors/{$sensorId}/hours", $q); }
    public function days(int $sensorId, array $q): array       { return $this->get("/v3/sensors/{$sensorId}/days", $q); }
    public function daysYearly(int $sensorId, array $q = []): array
    {
        return $this->get("/v3/sensors/{$sensorId}/days/yearly", $q);
    }

    // ---- Parameters / Providers / Countries ----
    public function parameters(): array                        { return $this->get('/v3/parameters', [], 3600); }
    public function parameterLatest(int $paramId, array $q = []): array
    {
        // e.g. ['datetime_min'=>'2025-10-01','limit'=>1000]
        return $this->get("/v3/parameters/{$paramId}/latest", $q);
    }

    public function providers(array $q = []): array            { return $this->get('/v3/providers', $q, 600); }
    public function countries(array $q = []): array            { return $this->get('/v3/countries', $q, 600); }
}
