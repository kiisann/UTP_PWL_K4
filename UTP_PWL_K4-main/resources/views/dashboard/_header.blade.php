{{-- BAGIAN SULTAN: indikator shift, ringkasan meja, tombol walk-in & tutup shift --}}
<header class="flex items-center justify-between gap-4 bg-slate-900 px-5 py-3 text-white">
    <div>
        <div class="text-lg font-bold">Rental PS</div>
        <div class="text-xs text-slate-300">
            @if ($activeShift)
                Shift aktif: {{ $activeShift->user->name }}, sejak {{ $activeShift->opened_at->format('H:i') }}
            @else
                Belum ada shift aktif
            @endif
        </div>
    </div>

    <div class="text-sm">
        <span class="font-semibold text-green-400">{{ $summary['available'] }} Tersedia</span>
        <span class="text-slate-500"> | </span>
        <span class="font-semibold text-blue-300">{{ $summary['occupied'] }} Bermain</span>
        <span class="text-slate-500"> | </span>
        <span class="font-semibold text-slate-300">{{ $summary['maintenance'] }} Maintenance</span>
    </div>

    <div class="flex items-center gap-3">
        {{-- Tombol di bawah masih placeholder (belum ada aksinya) --}}
        <button type="button" disabled class="rounded-lg bg-slate-700 px-3 py-2 text-sm opacity-60">
            Pesanan F&amp;B Langsung
        </button>
        <button type="button" disabled class="rounded-lg bg-red-600 px-3 py-2 text-sm opacity-60">
            Tutup Shift
        </button>

        {{-- Jam digital (Alpine.js): kalau angkanya jalan, berarti Alpine aktif --}}
        <div x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)" class="text-right">
            <div class="text-xl font-semibold tabular-nums" x-text="now.toLocaleTimeString('id-ID', { hour12: false })"></div>
            <div class="text-xs text-slate-400" x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></div>
        </div>
    </div>
</header>
