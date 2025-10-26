<?php
use function Livewire\Volt\{state, mount};
use App\Services\OpenAQ;

state([
  'sensorId' => null,
  'sensor'   => null,
  'flags'    => [],
  'error'    => null,
]);

$load = function (OpenAQ $aq) {
  try {
    $this->sensor = data_get($aq->sensor((int)$this->sensorId), 'data.results.0');
    $this->flags  = data_get($aq->sensorFlags((int)$this->sensorId), 'data.results', []);
    $this->error  = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq) => $this->load($aq));
?>

<div class="rounded-2xl border bg-white/70 p-5 space-y-4">
  <div class="flex items-start justify-between">
    <h3 class="font-semibold">Sensor Detail — ID {{ $sensorId }}</h3>
    <a href="{{ url()->previous() }}" class="text-sm text-emerald-700 hover:underline">Back</a>
  </div>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded text-sm">{{ $error }}</div>
  @endif

  @if($sensor)
    @php
      $p         = data_get($sensor, 'parameter', []);
      $pName     = $p['displayName'] ?? $p['display_name'] ?? $p['name'] ?? 'parameter';
      $pUnit     = $p['units'] ?? $p['unit'] ?? '—';

      // Relations / references
      $locId     = data_get($sensor, 'location.id')
                   ?? data_get($sensor, 'locations_id')
                   ?? data_get($sensor, 'location_id');

      // Datetimes per v3 contract
      $dtFirst   = data_get($sensor, 'datetimeFirst');
      $dtLast    = data_get($sensor, 'datetimeLast');

      // Latest block (value + datetime + coordinates)
      $latest    = data_get($sensor, 'latest');
      $latestVal = data_get($latest, 'value');
      $latestDt  = data_get($latest, 'datetime.local') ?? data_get($latest, 'datetime.utc');
      $latestC   = data_get($latest, 'coordinates');

      // Coverage + Summary per v3 contract
      $coverage  = data_get($sensor, 'coverage', []);
      $summary   = data_get($sensor, 'summary', []);

      // Optional hardware/provider fields (not guaranteed in Sensors contract)
      $manuf     = data_get($sensor, 'manufacturer');
      $model     = data_get($sensor, 'model');
    @endphp

    <div class="grid md:grid-cols-2 gap-4">
      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Parameter</div>
        <div class="text-lg font-semibold">{{ $pName }}</div>
        <div class="text-xs text-slate-500">Unit: {{ $pUnit }}</div>
      </div>

      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Latest</div>
        <div class="text-slate-700">Value: {{ $latestVal ?? '—' }} {{ $pUnit }}</div>
        <div class="text-xs text-slate-500">Time: {{ $latestDt ?? '—' }}</div>
        <div class="text-xs text-slate-500 mt-1">
          Coords:
          @if($latestC && isset($latestC['latitude'],$latestC['longitude']))
            {{ $latestC['latitude'] }}, {{ $latestC['longitude'] }}
          @else
            —
          @endif
        </div>
      </div>

      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Seen Range</div>
        <div class="text-slate-700">First: {{ data_get($dtFirst, 'local') ?? data_get($dtFirst, 'utc') ?? '—' }}</div>
        <div class="text-slate-700">Last:  {{ data_get($dtLast,  'local') ?? data_get($dtLast,  'utc')  ?? '—' }}</div>
      </div>

      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Coverage</div>
        <div class="text-slate-700">Expected: {{ data_get($coverage,'expectedCount','—') }} • Observed: {{ data_get($coverage,'observedCount','—') }}</div>
        <div class="text-xs text-slate-500">Percent: {{ data_get($coverage,'percentComplete') ?? data_get($coverage,'percentCoverage') ?? '—' }}</div>
        <div class="text-xs text-slate-500">Intervals: {{ data_get($coverage,'expectedInterval','—') }} • {{ data_get($coverage,'observedInterval','—') }}</div>
        <div class="text-xs text-slate-500">Period: {{ data_get($coverage,'datetimeFrom.local') ?? data_get($coverage,'datetimeFrom.utc') ?? '—' }} → {{ data_get($coverage,'datetimeTo.local') ?? data_get($coverage,'datetimeTo.utc') ?? '—' }}</div>
      </div>

      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Summary</div>
        <div class="text-slate-700">Min: {{ data_get($summary,'min','—') }} • Max: {{ data_get($summary,'max','—') }} • Avg: {{ data_get($summary,'avg','—') }}</div>
        <div class="text-xs text-slate-500">Q02: {{ data_get($summary,'q02','—') }} • Q25: {{ data_get($summary,'q25','—') }} • Median: {{ data_get($summary,'median','—') }} • Q75: {{ data_get($summary,'q75','—') }} • Q98: {{ data_get($summary,'q98','—') }}</div>
        <div class="text-xs text-slate-500">SD: {{ data_get($summary,'sd','—') }}</div>
      </div>

      <div class="rounded-xl border p-4 bg-white/60">
        <div class="text-sm text-slate-500 mb-1">Location</div>
        @if($locId)
          <a href="{{ route('locations.show', $locId) }}" class="text-emerald-700 hover:underline">Location #{{ $locId }}</a>
        @else
          <div class="text-slate-600">—</div>
        @endif
      </div>

      @if($manuf || $model)
        <div class="rounded-xl border p-4 bg-white/60 md:col-span-2">
          <div class="text-sm text-slate-500 mb-1">Hardware</div>
          <div class="text-slate-700">Manufacturer: {{ $manuf ?? '—' }} • Model: {{ $model ?? '—' }}</div>
        </div>
      @endif
    </div>

    @if(!empty($flags))
      <div class="pt-2">
        <div class="text-sm text-slate-500 mb-2">Flags</div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($flags as $flag)
            <span class="text-[11px] px-2 py-0.5 rounded-full border bg-amber-50 border-amber-200 text-amber-700">
              {{ $flag['name'] ?? 'flag' }}
            </span>
          @endforeach
        </div>
      </div>
    @endif
  @endif
</div>
