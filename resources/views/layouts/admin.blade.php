<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Panel Admin POS Rental PlayStation')</title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
  <div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Sidebar -->
    <aside class="w-full lg:w-64 bg-slate-900 text-slate-300 shrink-0 lg:sticky lg:top-0 lg:h-screen flex flex-col">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-800">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-700 flex items-center justify-center text-white font-extrabold text-lg">PS</div>
        <div>
          <h1 class="font-bold text-white text-base leading-none">PlayZone Admin</h1>
          <span class="text-xs text-blue-400 font-medium mt-1 block">POS Rental PlayStation</span>
        </div>
      </div>

      <nav class="px-3 py-4 flex-1 space-y-1">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Ringkasan &amp; Live Bilik</span>
        </a>
        <a href="{{ route('admin.consoles.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.consoles.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Master Konsol &amp; Tarif</span>
        </a>
        <a href="{{ route('admin.packages.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.packages.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Paket Harga Main</span>
        </a>
        <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.products.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Inventori &amp; Menu F&amp;B</span>
        </a>
        <a href="{{ route('admin.shifts.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.shifts.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Kasir &amp; Rekonsiliasi Shift</span>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.reports.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
          <span>Laporan &amp; Audit Void</span>
        </a>
      </nav>

      <div class="p-3 border-t border-slate-800 space-y-2">
        <a href="{{ route('dashboard') }}" class="w-full flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition">
          <span>&larr; Ke Layar Kasir (POS)</span>
        </a>
        <div class="px-2 py-1 flex items-center justify-between text-xs">
          <div>
            <div class="font-semibold text-white">{{ auth()->user()->name ?? 'Administrator' }}</div>
            <div class="text-slate-400">role: {{ auth()->user()->role ?? 'admin' }}</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold text-slate-900">@yield('header_title')</h2>
          <p class="text-xs text-slate-500">@yield('header_subtitle')</p>
        </div>
        <div class="text-xs font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg">
          {{ now()->translatedFormat('l, d M Y • H:i') }} WIB
        </div>
      </header>

      <main class="p-6 space-y-6 flex-1">
        @if(session('success'))
          <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold">
            {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
            {{ session('error') }}
          </div>
        @endif
        @if($errors->any())
          <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
            {{ $errors->first() }}
          </div>
        @endif

        @yield('content')
      </main>
    </div>
  </div>
  @stack('scripts')
</body>
</html>
