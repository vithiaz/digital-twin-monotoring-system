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
  'flags'    => [],
  'error'    => null,
]);

$load = function (OpenAQ $aq) {
  try {
    $this->location = $aq->location((int) $this->id)['data']['results'][0] ?? null;
    $this->latest   = $aq->latest((int) $this->id)['data']['results'] ?? [];
    $this->sensors  = $aq->sensorsAt((int) $this->id)['data']['results'] ?? [];
    $this->flags    = $aq->locationFlags((int) $this->id)['data']['results'] ?? [];
    $this->error    = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq, int $id) => $this->load($aq));
?>

<div class="space-y-6">

  @if($location)
    <!-- Header -->
    <div class="rounded-2xl border bg-white/70 p-5">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-xl font-semibold">{{ $location['name'] }}</h1>
          <div class="text-sm text-slate-500">
            {{ data_get($location,'country.name', data_get($location,'country','')) }}
            <span class="mx-1">•</span>
            {{ $location['timezone'] ?? '' }}
            <span class="mx-1">•</span>
            Provider: {{ data_get($location,'provider.name','—') }}
          </div>
        </div>
        <div class="text-xs text-slate-500">ID: {{ $id }}</div>
      </div>

      @if(!empty($flags))
        <div class="mt-3 flex flex-wrap gap-1.5">
          @foreach($flags as $flag)
            <span class="text-[11px] px-2 py-0.5 rounded-full border bg-amber-50 border-amber-200 text-amber-700">
              {{ $flag['name'] ?? 'flag' }}
            </span>
          @endforeach
        </div>
      @endif
    </div>

    <!-- Latest Table -->
    <div class="rounded-2xl border bg-white/70 p-5">
      <h2 class="font-semibold mb-3">Latest measurements</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50">
              <th class="p-2 text-left">Time</th>
              <th class="p-2 text-left">Value</th>
              <th class="p-2 text-left">Coordinates</th>
              <th class="p-2 text-left">Sensor</th>
              <th class="p-2 text-left">Parameter</th>
              <th class="p-2 text-left">Unit</th>
            </tr>
          </thead>
          <tbody class="divide-y">
          @foreach($latest as $row)
            @php
              $dt      = $row['datetime'] ?? $row['date'] ?? null;
              $timeStr = is_array($dt) ? ($dt['local'] ?? $dt['utc'] ?? '') : ($dt ?? '');
              $val     = $row['value'] ?? '';
              $coords  = $row['coordinates'] ?? null;
              $coordStr = ($coords && isset($coords['latitude'],$coords['longitude']))
                          ? ($coords['latitude'].', '.$coords['longitude'])
                          : '—';

              $sid     = $row['sensorsId'] ?? $row['sensorId'] ?? null;
              $sensor  = null;
              if ($sid) {
                  foreach ($sensors as $sx) {
                      if (($sx['id'] ?? null) == $sid) { $sensor = $sx; break; }
                  }
              }

              $p       = $sensor['parameter'] ?? [];
              $pName   = $p['displayName'] ?? $p['display_name'] ?? $p['name'] ?? '—';
              $pUnit   = $p['units'] ?? $p['unit'] ?? '—';
            @endphp
            <tr>
              <td class="p-2">{{ $timeStr }}</td>
              <td class="p-2">{{ $val }}</td>
              <td class="p-2">{{ $coordStr }}</td>
              <td class="p-2">
                @if($sid)
                  <a wire:navigate href="{{ url('/locations/'.$id.'?sensor='.$sid) }}" class="text-emerald-700 hover:underline">#{{ $sid }}</a>
                @else
                  —
                @endif
              </td>
              <td class="p-2">{{ $pName }}</td>
              <td class="p-2">{{ $pUnit }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <!-- Sensors List -->
    <div class="rounded-2xl border bg-white/70 p-5">
      <h2 class="font-semibold mb-3">Sensors</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($sensors as $s)
          @php
            $p = $s['parameter'] ?? [];
            $pName = $p['display_name'] ?? $p['displayName'] ?? $p['name'] ?? 'parameter';
            $pUnit = $p['unit'] ?? $p['units'] ?? '—';
          @endphp
          <div class="rounded-xl border p-3 bg-white/60">
            <div class="font-medium">{{ $pName }}</div>
            <div class="text-xs text-slate-500">Sensor ID: {{ $s['id'] }} • Unit: {{ $pUnit }}</div>
            <div class="mt-2 flex flex-wrap gap-2 text-sm">
              <a wire:navigate href="{{ url('/locations/'.$id.'?sensor='.$s['id'].'&view=detail') }}"
                class="text-emerald-700 hover:underline">Details</a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @php
    $sid  = request('sensor');
    $view = request('view');   // 'detail' shows sensor detail
    $agg  = request('agg','hour'); // hour|day for charts
  @endphp

  @if($sid && $view === 'detail')
    <livewire:sensor-detail :sensor-id="$sid" />
  @elseif($sid)
    <livewire:sensor-chart :sensor-id="$sid" :agg="$agg" />
  @endif

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded text-sm">{{ $error }}</div>
  @endif
</div>
