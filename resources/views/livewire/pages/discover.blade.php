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
      'iso'   => $this->iso,
      'limit' => $this->limit,
      'page'  => $this->page,
      'order_by'  => 'id',
      'sort_order'  => 'desc',
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
          {{ $loc['country']['name'] ?? $loc['country']['code'] ?? '—' }} • {{ $loc['timezone'] ?? '—' }}
        </div>
        <div class="mt-1 text-xs">Params:
          @foreach(($loc['parameters'] ?? []) as $p)
            <span class="inline-block px-1.5 py-0.5 border rounded mr-1">{{ $p['displayName'] ?? $p['display_name'] ?? $p['name'] }}</span>
          @endforeach
        </div>
        <div class="mt-1 text-xs">Provider: {{ $loc['provider']['name'] ?? '—' }}</div>
      </a>
    @empty
      <div class="text-sm text-slate-500">No results.</div>
    @endforelse
  </div>
</div>
