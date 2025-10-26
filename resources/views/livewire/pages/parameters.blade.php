<?php
use function Livewire\Volt\{state, mount, layout, title};
use App\Services\OpenAQ;
use App\Models\OpenAQParameter;

layout('layouts.app');
title('Parameters');

state(['rows' => [], 'error' => null, 'notice' => null]);

// Load parameters from the local database on mount
mount(function () {
  $this->rows = OpenAQParameter::query()
    ->orderBy('display_name')
    ->orderBy('name')
    ->get()
    ->toArray();
});

// Manual sync action to fetch from OpenAQ and persist locally
$sync = function (OpenAQ $aq) {
  try {
    $res = $aq->parameters();
    $items = data_get($res, 'data.results', []);

    $payload = collect($items)->map(function ($p) {
      return [
        'openaq_id'    => $p['id'] ?? null,
        'name'         => $p['name'] ?? null,
        'display_name' => $p['displayName'] ?? $p['display_name'] ?? null,
        'units'        => $p['units'] ?? $p['unit'] ?? null,
        'description'  => $p['description'] ?? null,
        'updated_at'   => now(),
        'created_at'   => now(),
      ];
    })->filter(fn ($row) => !is_null($row['openaq_id']) && !is_null($row['name']))->values()->all();

    if (!empty($payload)) {
      OpenAQParameter::upsert($payload, ['openaq_id'], ['name','display_name','units','description','updated_at']);
    }

    // Reload from DB
    $this->rows = OpenAQParameter::query()
      ->orderBy('display_name')
      ->orderBy('name')
      ->get()
      ->toArray();

    $this->notice = 'Parameters synced: '.count($payload);
    $this->error = null;
  } catch (\Throwable $e) {
    $this->error = $e->getMessage();
    $this->notice = null;
  }
};
?>

<div>
  <h1 class="text-xl font-semibold mb-3">Parameters</h1>

  @if($error)
    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4 text-sm">{{ $error }}</div>
  @endif
  @if($notice)
    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded mb-4 text-sm">{{ $notice }}</div>
  @endif

  <div class="mb-4 flex items-center gap-2">
    <button wire:click="sync" class="px-3 py-1.5 border rounded bg-white hover:bg-slate-50" wire:loading.attr="disabled">
      <span wire:loading.remove>Sync from OpenAQ</span>
      <span wire:loading>Syncing…</span>
    </button>
    <div class="text-xs text-slate-500">Rows: {{ count($rows) }}</div>
  </div>

  <table class="w-full text-sm border">
    <thead>
      <tr class="bg-slate-50">
        <th class="p-2 text-left">Name</th>
  <th class="p-2 text-left">Unit</th>
        <th class="p-2 text-left">Description</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $p)
        <tr class="border-t">
          <td class="p-2">{{ $p['display_name'] ?? $p['displayName'] ?? $p['name'] }}</td>
          <td class="p-2">{{ $p['units'] ?? '—' }}</td>
          <td class="p-2">{{ $p['description'] ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="p-3 text-sm text-slate-500">No parameters found. Click "Sync from OpenAQ" to import.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
