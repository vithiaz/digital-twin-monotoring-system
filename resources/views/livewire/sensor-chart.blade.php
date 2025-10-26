<?php
use function Livewire\Volt\{state, mount, on};
use App\Services\OpenAQ;

state([
  'sensorId' => null,
  'agg' => 'hour', // hour|day
  'from' => now()->subDays(7)->toDateString(),
  'to'   => now()->toDateString(),
  'series' => [],
  'error' => null,
  'paramName' => null,
  'paramUnit' => null,
]);

$load = function (OpenAQ $aq) {
  try {
    if ($this->agg === 'day') {
      $res = $aq->days((int) $this->sensorId, [
        'datetime_from' => $this->from, 'datetime_to' => $this->to, 'limit' => 1000,
      ]);
    } else {
      $res = $aq->hours((int) $this->sensorId, [
        'datetime_from' => $this->from, 'datetime_to' => $this->to, 'limit' => 1000,
      ]);
    }
  $this->series = $res['data']['results'] ?? [];
  $this->error = null;

  // Extract parameter name/unit if present on first point
  $first = $this->series[0] ?? null;
  $param = $first['parameter'] ?? [];
  $this->paramName = $param['displayName'] ?? $param['display_name'] ?? $param['name'] ?? null;
  $this->paramUnit = $param['units'] ?? $param['unit'] ?? null;

  // Tell the front-end to (re)draw
  $this->dispatch('draw-chart', series: $this->series, agg: $this->agg, paramName: $this->paramName, paramUnit: $this->paramUnit);
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
  }
};

mount(fn (OpenAQ $aq) => $this->load($aq));
on(['refresh-chart' => fn () => $this->load(app(App\Services\OpenAQ::class))]);
?>

<div class="rounded-2xl border bg-white/70 p-5">
  <div class="flex items-end gap-3 mb-3">
    <h3 class="font-semibold">Sensor {{ $sensorId }} — {{ $agg === 'day' ? 'Daily' : 'Hourly' }} values</h3>
    <div class="ml-auto text-xs text-slate-500 flex items-center gap-2">
      <label>From</label>
      <input type="date" class="border rounded px-2 py-1" wire:model.live="from">
      <label>To</label>
      <input type="date" class="border rounded px-2 py-1" wire:model.live="to">
      <button class="px-3 py-1.5 text-sm rounded border bg-white"
              wire:click="$dispatch('refresh-chart')">Reload</button>
    </div>
  </div>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded text-sm mb-3">{{ $error }}</div>
  @endif

  <div wire:ignore>
    <canvas id="aq-line-{{ $sensorId }}" height="120"></canvas>
  </div>
</div>

@push('scripts')
<script type="module">
  // Ensure Chart is available from Vite bundle (resources/js/app.js should expose window.Chart)
  window.addEventListener('alpine:init', () => {});
  let aqCharts = window.aqCharts || (window.aqCharts = {});

  document.addEventListener('livewire:load', () => {
    Livewire.on('draw-chart', function (e) {
      const payload = e.detail || {};
      const series = payload.series || [];
      const agg = payload.agg || 'hour';
      const paramName = payload.paramName || payload.param?.displayName || payload.param?.display_name || payload.param?.name || '';
      const paramUnit = payload.paramUnit || payload.param?.units || payload.param?.unit || '';

      // Build labels from v3 hours contract: period.datetimeFrom.local or utc
      const labels = series.map(p => {
        if (p.period) {
          const df = p.period.datetimeFrom;
          if (df) return (df.local ?? df.utc ?? df);
        }
        // fallbacks for older shapes
        if (p.datetime) return (p.datetime.local ?? p.datetime.utc ?? p.datetime);
        if (p.date) return p.date;
        return '';
      });

      const values = series.map(p => p.value ?? (p.summary?.avg ?? null));

      const ctx = document.getElementById('aq-line-{{ $sensorId }}').getContext('2d');
      if (window._aqChart) {
        window._aqChart.destroy();
      }

      window._aqChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: `${paramName || agg} ${paramUnit ? `(${paramUnit})` : ''}`.trim(),
            data: values,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.2)',
            tension: 0.2,
          }]
        },
        options: {
          parsing: false,
          scales: {
            x: { type: 'time', time: { tooltipFormat: 'yyyy-MM-dd HH:mm' } },
            y: { beginAtZero: false }
          }
        }
      });
    });
  });
</script>
@endpush
