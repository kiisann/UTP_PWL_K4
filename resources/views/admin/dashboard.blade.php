@extends('layouts.admin')

@section('title', 'Dashboard Admin — PlayZone POS')
@section('header_title', 'Ringkasan Eksekutif & Monitoring Bilik')
@section('header_subtitle', 'Pantau performa harian, okupansi konsol main di tempat, dan pendapatan F&B secara real-time')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <span class="text-xs font-semibold uppercase text-slate-400">Total Omzet Hari Ini</span>
    <div class="text-2xl font-extrabold text-slate-900 mt-2">Rp {{ number_format($totalRevenueToday, 0, ',', '.') }}</div>
    <div class="mt-3 pt-2.5 border-t border-slate-100 text-xs text-slate-500">
      Sewa PS: <strong>Rp {{ number_format($rentalRevenueToday, 0, ',', '.') }}</strong>
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <span class="text-xs font-semibold uppercase text-slate-400">Omzet Makanan &amp; Minuman</span>
    <div class="text-2xl font-extrabold text-emerald-600 mt-2">Rp {{ number_format($fnbRevenueToday, 0, ',', '.') }}</div>
    <div class="mt-3 pt-2.5 border-t border-slate-100 text-xs text-slate-500">
      Peringatan Stok Tipis: <strong>{{ $lowStockCount }} Produk</strong>
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <span class="text-xs font-semibold uppercase text-slate-400">Okupansi Bilik Aktif</span>
    <div class="text-2xl font-extrabold text-slate-900 mt-2">{{ $occupiedCount }} / {{ $consoles->count() }} Unit</div>
    <div class="mt-3 pt-2.5 border-t border-slate-100 text-xs text-slate-500">
      {{ $availableCount }} Tersedia &bull; {{ $maintenanceCount }} Maintenance
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <span class="text-xs font-semibold uppercase text-slate-400">Shift Kasir Aktif</span>
    <div class="text-lg font-extrabold text-slate-900 mt-2">{{ $activeShift?->user?->name ?? 'Belum Dibuka' }}</div>
    <div class="mt-3 pt-2.5 border-t border-slate-100 text-xs text-slate-500">
      Modal Awal: Rp {{ number_format($activeShift?->starting_cash ?? 0, 0, ',', '.') }}
    </div>
  </div>
</div>

<!-- Grafik Tren Pendapatan 7 Hari & Distribusi Kategori -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <h3 class="font-bold text-slate-900 text-sm mb-1">Tren Pendapatan Sewa PS vs F&amp;B (7 Hari Terakhir)</h3>
    <p class="text-xs text-slate-500 mb-4">Perbandingan omzet main di tempat dan penjualan makanan/minuman</p>
    <div class="relative h-64">
      <canvas id="revenueChart"></canvas>
    </div>
  </div>
  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <h3 class="font-bold text-slate-900 text-sm mb-1">Distribusi Unit per Kategori</h3>
    <p class="text-xs text-slate-500 mb-4">Jumlah bilik PlayStation berdasarkan kategori</p>
    <div class="relative h-64">
      <canvas id="categoryChart"></canvas>
    </div>
  </div>
</div>

<!-- Live Grid Bilik PS -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Monitoring Status Bilik PlayStation (Real-Time TIMESTAMPTZ)</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($consoles as $unit)
      @php $session = $unit->activeSession; @endphp
      <div class="rounded-xl p-4 border {{ $unit->status === 'available' ? 'border-emerald-200 bg-emerald-50/30' : ($unit->status === 'maintenance' ? 'border-slate-300 bg-slate-100' : 'border-blue-200 bg-blue-50/30') }}">
        <div class="flex items-center justify-between">
          <span class="font-bold text-slate-900 text-sm">{{ $unit->code }}</span>
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $unit->status === 'available' ? 'bg-emerald-100 text-emerald-700' : ($unit->status === 'maintenance' ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-700') }}">
            {{ strtoupper($unit->status) }}
          </span>
        </div>
        <div class="text-xs text-slate-500 mt-0.5">{{ $unit->name }} ({{ $unit->category->name }})</div>

        @if($unit->status === 'occupied' && $session)
          <div class="text-2xl font-extrabold text-blue-700 font-mono my-2 live-timer"
               data-mode="{{ $session->billing_mode }}"
               data-start="{{ $session->start_time->toIso8601String() }}"
               data-end="{{ $session->planned_end_time?->toIso8601String() }}">
            00:00:00
          </div>
          <div class="text-xs text-slate-600 flex justify-between pt-2 border-t border-slate-200">
            <span>{{ $session->customer_name ?? 'Umum' }}</span>
            <strong>Rp {{ number_format($session->grand_total, 0, ',', '.') }}</strong>
          </div>
        @else
          <div class="text-xl font-bold text-slate-300 font-mono my-2">--:--:--</div>
          <div class="text-xs text-slate-500 flex justify-between pt-2 border-t border-slate-200">
            <span>{{ $unit->status === 'maintenance' ? 'Perbaikan' : 'Siap Pakai' }}</span>
            <strong>Rp {{ number_format($unit->category->hourly_rate, 0, ',', '.') }}/jam</strong>
          </div>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endsection

@push('scripts')
<script>
  function updateTimers() {
    const now = Date.now();
    document.querySelectorAll('.live-timer').forEach(el => {
      const mode = el.dataset.mode;
      const start = new Date(el.dataset.start).getTime();
      const end = el.dataset.end ? new Date(el.dataset.end).getTime() : null;
      let diffSec = 0;
      if (mode === 'package' && end) {
        diffSec = Math.max(0, Math.floor((end - now) / 1000));
      } else {
        diffSec = Math.max(0, Math.floor((now - start) / 1000));
      }
      const hrs = String(Math.floor(diffSec / 3600)).padStart(2, '0');
      const mins = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
      const secs = String(diffSec % 60).padStart(2, '0');
      el.textContent = `${hrs}:${mins}:${secs}`;
    });
  }
  setInterval(updateTimers, 1000);
  updateTimers();

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: @json($chartLabels),
      datasets: [
        { label: 'Sewa PS (Rp)', data: @json($chartRental), backgroundColor: '#2563EB', borderRadius: 6 },
        { label: 'F&B (Rp)', data: @json($chartFnb), backgroundColor: '#10B981', borderRadius: 6 }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });

  new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
      labels: @json($categoryUsage->pluck('name')),
      datasets: [{
        data: @json($categoryUsage->pluck('consoles_count')),
        backgroundColor: ['#2563EB', '#4F46E5', '#0EA5E9', '#10B981', '#F59E0B']
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
</script>
@endpush
