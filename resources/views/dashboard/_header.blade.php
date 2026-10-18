
@php
    
    $cashiers = auth()->check()
        ? collect()
        : \App\Models\User::where('role', 'cashier')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
@endphp

<header
    x-data="shiftBar(@js(['summaryUrl' => route('shifts.summary'), 'autoOpen' => ! $activeShift]))"
    class="flex items-center justify-between gap-4 bg-slate-900 px-5 py-3 text-white"
>
    <div>
        <div class="text-lg font-bold">Rental PS</div>
        <div class="text-xs text-slate-300">
            @if ($activeShift)
                Shift aktif: {{ $activeShift->user->name }}, sejak {{ $activeShift->opened_at->format('H:i') }}
                (modal Rp {{ number_format($activeShift->starting_cash, 0, ',', '.') }})
            @else
                <span class="font-semibold text-amber-300">Belum ada shift aktif</span>
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
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition">
            Panel Admin
        </a>

        
        <button type="button" disabled class="rounded-lg bg-slate-700 px-3 py-2 text-sm opacity-60">
            Pesanan F&amp;B Langsung
        </button>

        @if ($activeShift)
            <button type="button" @click.stop="openClose()"
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold hover:bg-red-700">
                Tutup Shift
            </button>
        @else
            <button type="button" @click.stop="modal = 'open'"
                    class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-amber-400">
                Buka Shift
            </button>
        @endif

        
        <div x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)" class="text-right">
            <div class="text-xl font-semibold tabular-nums" x-text="now.toLocaleTimeString('id-ID', { hour12: false })"></div>
            <div class="text-xs text-slate-400" x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></div>
        </div>
    </div>


    <div
        x-show="modal === 'open'" x-cloak
        @keydown.escape.window="modal = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 text-slate-800"
    >
        <div @click.outside="modal = null" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <form method="POST" action="{{ route('shifts.open') }}" class="space-y-3">
                @csrf
                <h3 class="text-lg font-bold">Buka Shift</h3>

                @if ($cashiers->isNotEmpty())
                    <div>
                        <label class="text-sm font-medium">Operator <span class="text-xs text-slate-400">(sementara, sampai login jadi)</span></label>
                        <select name="operator_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach ($cashiers as $cashier)
                                <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium">Modal awal di laci (uang kembalian)</label>
                    <input type="number" name="starting_cash" min="0" step="1" required x-model="startingCash"
                           placeholder="Contoh: 100000"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    <p class="mt-1 text-xs text-slate-500" x-show="startingCash !== ''" x-text="rupiah(startingCash)"></p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Nanti</button>
                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-amber-400">Buka Shift</button>
                </div>
            </form>
        </div>
    </div>

    
    <div
        x-show="modal === 'close'" x-cloak
        @keydown.escape.window="modal = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 text-slate-800"
    >
        <div @click.outside="modal = null" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <form method="POST" action="{{ route('shifts.close') }}" class="space-y-3"
                  onsubmit="return confirm('Tutup shift sekarang? Shift yang sudah ditutup tidak bisa dibuka lagi.')">
                @csrf
                <h3 class="text-lg font-bold">Tutup Shift</h3>

                <p x-show="loading" class="text-sm text-slate-500">Memuat ringkasan...</p>
                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                <template x-if="summary">
                    <div class="space-y-3">
                        <dl class="space-y-1 rounded-lg bg-slate-50 p-3 text-sm">
                            <div class="flex justify-between"><dt>Operator</dt><dd x-text="summary.operator"></dd></div>
                            <div class="flex justify-between"><dt>Dibuka</dt><dd x-text="summary.opened_at"></dd></div>
                            <div class="flex justify-between"><dt>Modal awal</dt><dd x-text="rupiah(summary.starting_cash)"></dd></div>
                            <div class="flex justify-between"><dt>Penjualan tunai</dt><dd x-text="rupiah(summary.cash_sales)"></dd></div>
                            <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold">
                                <dt>Kas seharusnya di laci</dt><dd x-text="rupiah(summary.expected_cash)"></dd>
                            </div>
                            <div class="flex justify-between text-blue-600">
                                <dt>Pendapatan QRIS (tidak masuk laci)</dt><dd x-text="rupiah(summary.qris_total)"></dd>
                            </div>
                        </dl>

                        <p x-show="summary.running_sessions > 0" class="rounded-lg bg-amber-50 p-2 text-xs text-amber-800">
                            Masih ada <span x-text="summary.running_sessions"></span> sesi berjalan atau belum dibayar.
                            Pembayarannya akan tercatat di shift berikutnya.
                        </p>

                        <div>
                            <label class="text-sm font-medium">Uang tunai fisik di laci (hitung manual)</label>
                            <input type="number" name="actual_cash" min="0" step="1" required x-model="actual"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <p x-show="actual !== ''" class="text-sm font-semibold"
                           :class="balanced ? 'text-green-600' : 'text-red-600'" x-text="differenceText"></p>
                    </div>
                </template>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Batal</button>
                    <button type="submit" :disabled="!summary || actual === ''"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                        Tutup Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>
