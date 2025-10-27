<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>@yield('title', config('app.name'))</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
  @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-800">
  <!-- Top Nav -->
  <header class="sticky top-0 z-40 backdrop-blur bg-white/80 border-b">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="{{ route('discover') }}" class="flex items-center gap-2" wire:navigate>
        <img src="/logo.png" alt="App Logo" class="w-8 h-8">
        <span class="font-semibold">Air Quality Monitoring System</span>
      </a>
      <nav class="flex items-center gap-4 text-sm">
        <a href="{{ route('discover') }}" class="hover:text-emerald-600" wire:navigate>Discover</a>
        <a href="{{ route('parameters') }}" class="hover:text-emerald-600" wire:navigate>Parameters</a>
      </nav>
    </div>
  </header>

  <!-- Page Container -->
  <main class="max-w-7xl mx-auto px-4 py-8">
    {{ $slot }}
  </main>

  <!-- Footer -->
  {{-- NOTE: Temporary disabled for copyright section --}}
  {{-- <footer class="border-t">
    <div class="max-w-7xl mx-auto px-4 py-6 text-xs text-slate-500">
      Built with Laravel + Livewire + Volt • Data: OpenAQ v3
    </div>
  </footer> --}}

  @livewireScripts
</body>
</html>
