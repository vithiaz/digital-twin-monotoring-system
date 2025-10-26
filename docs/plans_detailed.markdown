# OpenAQ Laravel + Livewire + Volt — Implementation Plan

## 🎯 Goals
- Build a small web app that **only uses OpenAQ v3**.
- Tech stack: **Laravel**, **Livewire v3**, **Laravel Volt**.
- Pages:
  1) **Discover** locations (filtered to **Indonesia** by default).  
  2) **Location Detail** (latest values + sensors list).  
  3) **Sensor Chart** (hourly series; embedded component).  
  4) **Parameters** glossary.

---

## 📦 Install & Scaffold

```bash
# Install Livewire + Volt
composer require livewire/livewire
composer require livewire/volt

# Install Volt assets (uses your existing Vite setup)
php artisan volt:install

# Frontend build
npm i
npm run dev
```

---

## ⚙️ Configuration

### `.env`
```env
APP_NAME="OpenAQ Demo"
OPENAQ_BASE=https://api.openaq.org
OPENAQ_KEY=your-openaq-key-here

# Defaults: Indonesia + Manado for geospatial search
DEFAULT_ISO=ID
DEFAULT_LAT=1.4748
DEFAULT_LON=124.8421
DEFAULT_RADIUS=12000
```

### `config/services.php`
```php
// config/services.php
return [
    // ...
    'openaq' => [
        'base' => env('OPENAQ_BASE', 'https://api.openaq.org'),
        'key'  => env('OPENAQ_KEY'),
    ],
];
```

---

## 🔌 OpenAQ Client (cached)

```php
<?php
// app/Services/OpenAQ.php
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

    // ---- OpenAQ v3 resources ----
    public function locations(array $q): array                 { return $this->get('/v3/locations', $q); }
    public function location(int $id): array                   { return $this->get("/v3/locations/{$id}"); }
    public function latest(int $locationId): array             { return $this->get("/v3/locations/{$locationId}/latest"); }
    public function sensorsAt(int $locationId): array          { return $this->get("/v3/locations/{$locationId}/sensors"); }
    public function sensor(int $sensorId): array               { return $this->get("/v3/sensors/{$sensorId}"); }
    public function hours(int $sensorId, array $q): array      { return $this->get("/v3/sensors/{$sensorId}/hours", $q); }
    public function parameters(): array                        { return $this->get('/v3/parameters', [], 3600); }
}
```

---

## 🗺️ Routes (Volt full-page components)

```php
<?php
// routes/web.php

use Livewire\Volt\Volt;

Volt::route('/', 'pages.discover')->name('discover');
Volt::route('/locations/{id}', 'pages.location-show')->name('locations.show');
Volt::route('/parameters', 'pages.parameters')->name('parameters');
```
---

## 🧱 Layout

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>@yield('title', config('app.name'))</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
  @livewireStyles
</head>
<body class="antialiased text-slate-800">
  <nav class="p-4 border-b flex gap-4">
    <a href="{{ route('discover') }}" wire:navigate>Discover</a>
    <a href="{{ route('parameters') }}" wire:navigate>Parameters</a>
  </nav>
  <main class="p-6">
    {{ $slot }}
  </main>
  @livewireScripts
</body>
</html>
```

---

## 📄 Page 1 — **Discover** (Indonesia + optional Manado radius)

```blade
{{-- resources/views/pages/discover.blade.php --}}
<?php
use function Livewire\Volt\{state, mount, layout, title};
use App\Services\OpenAQ;

layout('layouts.app');
title('Discover stations');

state([
  'iso' => env('DEFAULT_ISO', 'ID'),
  'lat' => (float) env('DEFAULT_LAT', 1.4748),
  'lon' => (float) env('DEFAULT_LON', 124.8421),
  'radius' => (int) env('DEFAULT_RADIUS', 12000),
  'parameterId' => null,
  'limit' => 20,
  'page' => 1,
  'results' => [],
  'quota' => null,
  'error' => null,
]);

$load = function (OpenAQ $aq) {
  try {
    $query = [
      'iso'   => $this->iso,            // filter to Indonesia
      'limit' => $this->limit,
      'page'  => $this->page,
    ];
    if ($this->lat && $this->lon && $this->radius) {
      $query['coordinates'] = "{$this->lat},{$this->lon}";
      $query['radius'] = $this->radius; // don't combine with bbox
    }
    if ($this->parameterId) $query['parameters_id'] = $this->parameterId;

    $res = $aq->locations($query);
    $this->results = $res['data']['results'] ?? [];
    $this->quota   = $res['headers'] ?? null;
    $this->error   = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq) => $this->load($aq));
?>

@section('title', 'Discover stations')
<div>
  <form class="mb-4 grid grid-cols-6 gap-3" wire:submit.prevent="load">
    <div class="col-span-2">
      <label class="block text-sm">ISO Country</label>
      <input type="text" class="input w-full border p-2 rounded" wire:model.live="iso" placeholder="ID">
    </div>
    <div>
      <label class="block text-sm">Lat</label>
      <input type="number" step="0.0001" class="input w-full border p-2 rounded" wire:model.live="lat">
    </div>
    <div>
      <label class="block text-sm">Lon</label>
      <input type="number" step="0.0001" class="input w-full border p-2 rounded" wire:model.live="lon">
    </div>
    <div>
      <label class="block text-sm">Radius (m)</label>
      <input type="number" class="input w-full border p-2 rounded" wire:model.live="radius">
    </div>
    <div>
      <label class="block text-sm">Param ID</label>
      <input type="number" class="input w-full border p-2 rounded" wire:model.live="parameterId" placeholder="e.g. 2 for PM2.5">
    </div>
    <div class="col-span-6">
      <button class="px-4 py-2 border rounded" type="submit">Search</button>
    </div>
  </form>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4 text-sm">{{ $error }}</div>
  @endif

  <div class="text-xs text-slate-500 mb-3">
    Rate limit — used: {{ $quota['used'] ?? '–' }}, remaining: {{ $quota['remaining'] ?? '–' }}, reset: {{ $quota['reset'] ?? '–' }}
  </div>

  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($results as $loc)
      <a wire:navigate href="{{ route('locations.show', $loc['id']) }}" class="p-4 rounded border hover:bg-slate-50">
        <div class="font-semibold">{{ $loc['name'] }}</div>
        <div class="text-xs">
          {{ $loc['country'] ?? '—' }} • {{ $loc['timezone'] ?? '—' }}
        </div>
        <div class="mt-1 text-xs">Params:
          @foreach(($loc['parameters'] ?? []) as $p)
            <span class="inline-block px-1.5 py-0.5 border rounded mr-1">{{ $p['display_name'] ?? $p['name'] }}</span>
          @endforeach
        </div>
        <div class="mt-1 text-xs">Provider: {{ $loc['provider']['name'] ?? '—' }}</div>
      </a>
    @empty
      <div class="text-sm text-slate-500">No results.</div>
    @endforelse
  </div>
</div>
```

---

## 📄 Page 2 — **Location Detail** (latest + sensors)

```blade
{{-- resources/views/pages/location-show.blade.php --}}
<?php
use function Livewire\Volt\{state, mount, layout, title};
use App\Services\OpenAQ;

layout('layouts.app');
title(fn () => 'Location '.$this->id);

state([
  'id',
  'location' => null,
  'latest'   => [],
  'sensors'  => [],
  'error'    => null,
]);

$load = function (OpenAQ $aq) {
  try {
    $this->location = $aq->location((int) $this->id)['data']['results'][0] ?? null;
    $this->latest   = $aq->latest((int) $this->id)['data']['results'] ?? [];
    $this->sensors  = $aq->sensorsAt((int) $this->id)['data']['results'] ?? [];
    $this->error    = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq, int $id) => $this->load($aq));
?>

<div>
  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4 text-sm">{{ $error }}</div>
  @endif

  @if($location)
    <h1 class="text-xl font-semibold mb-2">{{ $location['name'] }}</h1>
    <div class="text-sm mb-4">
      {{ $location['country'] ?? '' }} • {{ $location['timezone'] ?? '' }} •
      Provider: {{ $location['provider']['name'] ?? '—' }}
    </div>

    <h2 class="font-semibold mt-4 mb-1">Latest</h2>
    <table class="w-full text-sm border">
      <thead>
        <tr class="bg-slate-50">
          <th class="p-2 text-left">Parameter</th>
          <th class="p-2 text-left">Value</th>
          <th class="p-2 text-left">Unit</th>
          <th class="p-2 text-left">Time</th>
        </tr>
      </thead>
      <tbody>
      @foreach($latest as $row)
        <tr class="border-t">
          <td class="p-2">{{ $row['parameter']['display_name'] ?? $row['parameter']['name'] }}</td>
          <td class="p-2">{{ $row['value'] }}</td>
          <td class="p-2">{{ $row['unit'] }}</td>
          <td class="p-2">{{ $row['datetime'] ?? $row['date'] ?? '' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <h2 class="font-semibold mt-6 mb-2">Sensors</h2>
    <ul class="space-y-2">
      @foreach($sensors as $s)
        <li class="p-3 border rounded">
          <div class="font-medium">{{ $s['parameter']['display_name'] ?? $s['parameter']['name'] }}</div>
          <div class="text-xs">Sensor ID: {{ $s['id'] }} • Unit: {{ $s['parameter']['unit'] ?? '—' }}</div>
          <div class="mt-2">
            <a wire:navigate href="{{ url('/locations/'.$id.'?sensor='.$s['id']) }}" class="text-indigo-600 underline">
              View hourly table
            </a>
          </div>
        </li>
      @endforeach
    </ul>
  @endif

  @php $sid = request('sensor'); @endphp
  @if($sid)
    <livewire:sensor-chart :sensor-id="$sid" />
  @endif
</div>
```

---

## 🔧 Component — **Sensor Chart** (hourly table for now)

```blade
{{-- resources/views/livewire/sensor-chart.blade.php --}}
<?php
use function Livewire\Volt\{state, mount};
use App\Services\OpenAQ;

state([
  'sensorId' => null,
  'from' => now()->subDays(7)->toDateString(),
  'to'   => now()->toDateString(),
  'series' => [],
  'error' => null,
]);

$load = function (OpenAQ $aq) {
  try {
    $res = $aq->hours((int) $this->sensorId, [
      'datetime_from' => $this->from,
      'datetime_to'   => $this->to,
      'limit'         => 1000,
    ]);
    $this->series = $res['data']['results'] ?? [];
    $this->error = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq) => $this->load($aq));
?>

<div class="mt-6">
  <h3 class="font-semibold mb-2">Hourly values ({{ $from }} → {{ $to }})</h3>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4 text-sm">{{ $error }}</div>
  @endif

  <div class="overflow-x-auto text-xs">
    <table class="border w-full">
      <thead>
        <tr class="bg-slate-50">
          <th class="p-2 text-left">Hour</th>
          <th class="p-2 text-left">Value</th>
        </tr>
      </thead>
      <tbody>
        @foreach($series as $pt)
          <tr class="border-t">
            <td class="p-2">{{ $pt['datetime'] ?? $pt['date'] }}</td>
            <td class="p-2">{{ $pt['value'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
```

---

## 📄 Page 3 — **Parameters** (glossary)

```blade
{{-- resources/views/pages/parameters.blade.php --}}
<?php
use function Livewire\Volt\{state, mount, layout, title};
use App\Services\OpenAQ;

layout('layouts.app');
title('Parameters');

state(['rows' => [], 'error' => null]);

mount(function (OpenAQ $aq) {
  try {
    $this->rows = $aq->parameters()['data']['results'] ?? [];
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
});
?>

<div>
  <h1 class="text-xl font-semibold mb-3">Parameters</h1>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4 text-sm">{{ $error }}</div>
  @endif

  <table class="w-full text-sm border">
    <thead>
      <tr class="bg-slate-50">
        <th class="p-2 text-left">Name</th>
        <th class="p-2 text-left">Unit</th>
        <th class="p-2 text-left">Description</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $p)
        <tr class="border-t">
          <td class="p-2">{{ $p['display_name'] ?? $p['name'] }}</td>
          <td class="p-2">{{ $p['unit'] ?? '—' }}</td>
          <td class="p-2">{{ $p['description'] ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
```

---

## 🧭 UX & Data Rules
- Always send `X-API-Key` with every OpenAQ request.
- Show a small **rate-limit badge** (`x-ratelimit-used/remaining/reset`) on Discover.
- Geospatial: use **either** `coordinates+radius` **or** `bbox` (do not mix).
- Prefer **`/hours`** for charts; it’s clean and consistent.
- Cache read-only calls (60s for lists, 3600s for parameters) to keep UX snappy.

---

## ✅ What You Ship (MVP)
- **Discover** locations in **Indonesia** (default), optionally around **Manado** with radius & parameter filter.
- **Location detail** with **latest measurements** and **sensors** list.
- **Sensor hourly series** rendered as a simple table (chart can be added next).
- **Parameters** glossary with names, units, and descriptions.
- **Good DX**: Volt page routing, SPA-ish navigation (`wire:navigate`), caching, and rate-limit display.

---

## ➕ Next Steps (Nice-to-have)
- Add a line chart (Chart.js) to `sensor-chart` (CDN + a small Alpine.js block).
- Add pagination controls to Discover (`page` / `limit`).
- Add CSV export of the current sensor’s hourly data.
- Add a “Saved searches” table (iso + lat/lon + radius + parameterId).
- Add provider filter & provider info on cards.
