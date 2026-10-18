@php
    $session = $console->activeSession;
    $stopped = $session && $session->actual_end_time;
    $isPackage = $session && $session->billing_mode === \App\Models\RentalSession::MODE_PACKAGE;

    if ($console->status === \App\Models\Console::MAINTENANCE) {
        $label = 'Maintenance';
        $card  = 'border-slate-300 bg-slate-200 text-slate-500';
        $badge = 'bg-slate-500 text-white';
    } else {
        $label = 'Tersedia';
        $card  = 'border-green-300 bg-green-50 text-green-900';
        $badge = 'bg-green-600 text-white';
    }

    $rupiah = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    $consolePayload = ['id' => $console->id, 'name' => $console->name, 'category_id' => $console->category_id];

    $timerCfg = $session ? [
        'id' => $console->id,
        'mode' => $session->billing_mode,
        'start' => $session->start_time->getTimestamp() * 1000,
        'plannedEnd' => $session->planned_end_time ? $session->planned_end_time->getTimestamp() * 1000 : null,
        'actualEnd' => $session->actual_end_time ? $session->actual_end_time->getTimestamp() * 1000 : null,
        'serverNow' => $serverNow,
        'rate' => (float) $console->category->hourly_rate,
        'minCharge' => (int) $console->category->min_charge_minutes,
        'rounding' => (int) $console->category->rounding_minutes,
        'rental' => (float) $session->rental_amount,
        'fnb' => (float) $session->fnb_amount,
        'discount' => (float) $session->discount_amount,
    ] : null;
    $selectedPayload = [
        'id' => $console->id,
        'code' => $console->code,
        'name' => $console->name,
        'status' => $console->status,
        'category_name' => $console->category->name,
        'hourly_rate' => (float) $console->category->hourly_rate,
        'session' => $session ? [
            'id' => $session->id,
            'invoice_number' => $session->invoice_number,
            'customer_name' => $session->customer_name,
            'billing_mode' => $session->billing_mode,
            'start_time' => $session->start_time->format('H:i'),
            'rental_amount' => (float) $session->rental_amount,
            'fnb_amount' => (float) $session->fnb_amount,
            'discount_amount' => (float) $session->discount_amount,
            'grand_total' => (float) $session->grand_total,
            'stopped' => (bool) $stopped,
            'fnb_items' => $session->fnbOrders->filter(fn ($o) => $o->order_status !== 'void')->flatMap(fn ($o) => $o->items)->map(fn ($it) => [
                'name' => $it->product->name ?? 'F&B',
                'qty' => (int) $it->qty,
                'unit_price' => (float) $it->unit_price,
                'subtotal' => (float) $it->subtotal,
            ])->values()->all(),
        ] : null,
    ];
@endphp

<div
    @if ($session)
        x-data="sessionTimer(@js($timerCfg))" x-cloak
        :class="{
            'border-blue-300 bg-blue-50 text-blue-900': state === 'package',
            'border-yellow-400 bg-yellow-100 text-yellow-900': state === 'warning',
            'border-red-400 bg-red-100 text-red-900 animate-pulse': state === 'expired',
            'border-orange-300 bg-orange-50 text-orange-900': state === 'open',
            'border-indigo-300 bg-indigo-50 text-indigo-900': state === 'stopped',
            'ring-4 ring-indigo-400': selected && selected.id === {{ $console->id }},
        }"
        class="cursor-pointer rounded-2xl border-2 p-4 shadow-sm transition"
    @else
        :class="selected && selected.id === {{ $console->id }} ? 'ring-4 ring-indigo-400' : ''"
        class="cursor-pointer rounded-2xl border-2 p-4 shadow-sm transition {{ $card }}"
    @endif
    @click="selected = @js($selectedPayload)"
>
    <div class="flex items-start justify-between gap-2">
        <div>
            <div class="font-bold">{{ $console->name }}</div>
            <div class="text-xs opacity-70">{{ $console->category->name }}</div>
        </div>

        @if ($session)
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="{
                    'bg-blue-600 text-white': state === 'package',
                    'bg-yellow-500 text-white': state === 'warning',
                    'bg-red-600 text-white': state === 'expired',
                    'bg-orange-500 text-white': state === 'open',
                    'bg-indigo-600 text-white': state === 'stopped',
                }"
                x-text="label"
            ></span>
        @else
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $label }}</span>
        @endif
    </div>

    @if ($session)
        <div class="mt-3 text-sm font-medium">{{ $session->customer_name ?? 'Tanpa nama' }}</div>

        <div class="mt-2 text-center text-4xl font-bold tabular-nums" x-text="display">--:--:--</div>
        <div class="mb-1 text-center text-xs font-semibold" x-text="caption"></div>

        <div class="text-center text-xs opacity-70">
            Mulai {{ $session->start_time->format('H:i') }}
            @if ($stopped)
                , selesai {{ $session->actual_end_time->format('H:i') }} ({{ $session->total_minutes }} menit ditagih)
            @elseif ($session->planned_end_time)
                , selesai {{ $session->planned_end_time->format('H:i') }}
            @endif
        </div>

        <div class="mt-3 text-sm">
            @if ($stopped)
                Total <span class="font-bold">{{ $rupiah($session->grand_total) }}</span>
            @elseif ($session->billing_mode === \App\Models\RentalSession::MODE_OPEN)
                Sewa <span class="font-bold" x-text="rupiah(estimatedRental)"></span>
                + F&amp;B {{ $rupiah($session->fnb_amount) }}
                <div class="text-xs opacity-70">Total sementara <span class="font-semibold" x-text="rupiah(estimatedTotal)"></span> (final saat Stop)</div>
            @else
                Sewa {{ $rupiah($session->rental_amount) }} + F&amp;B {{ $rupiah($session->fnb_amount) }}
            @endif
        </div>

        @if ($stopped)
            <div class="mt-3 text-xs">
                <button type="button"
                        @click.stop="modal = { type: 'checkout', session_id: {{ $session->id }}, console: @js($consolePayload), grand_total: {{ $session->grand_total }}, rental: {{ $session->rental_amount }}, fnb: {{ $session->fnb_amount }} }"
                        class="w-full rounded-lg bg-emerald-600 py-2.5 font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                    Bayar ({{ $rupiah($session->grand_total) }})
                </button>
                <a href="{{ route('sessions.receipt', $session) }}" target="_blank" @click.stop
                   class="mt-2 block text-center underline opacity-70 hover:opacity-100">Pratinjau struk</a>
            </div>
        <button type="button"
                @click.stop="modal = { type: 'void', session_id: {{ $session->id }}, console: @js($consolePayload) }"
                class="mt-2 w-full text-center text-xs underline opacity-70 hover:opacity-100">Batalkan sesi (void)</button>
        @else
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <button type="button"
                        @click.stop="modal = { type: 'fnb', session_id: {{ $session->id }}, console: @js($consolePayload) }"
                        class="rounded-lg bg-white border border-slate-200 py-2 font-semibold text-slate-800 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 transition">+ F&amp;B</button>

                @if ($isPackage)
                    <button type="button"
                            @click.stop="modal = { type: 'extend', session_id: {{ $session->id }}, console: @js($consolePayload) }"
                            class="rounded-lg bg-white py-2 font-semibold hover:bg-blue-100">+ Waktu</button>
                @else
                    <button type="button" disabled class="rounded-lg bg-white/70 py-2 font-semibold opacity-60">+ Waktu</button>
                @endif

                <button type="button"
                        @click.stop="modal = { type: 'move', session_id: {{ $session->id }}, console: @js($consolePayload) }"
                        class="rounded-lg bg-white py-2 font-semibold hover:bg-amber-100">Pindah</button>

                <form method="POST" action="{{ route('sessions.stop', $session) }}"
                      @click.stop onsubmit="return confirm('Hentikan sesi {{ $console->name }}?')">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-red-600 py-2 font-semibold text-white hover:bg-red-700">Stop</button>
                </form>
            </div>
        <button type="button"
                @click.stop="modal = { type: 'void', session_id: {{ $session->id }}, console: @js($consolePayload) }"
                class="mt-2 w-full text-center text-xs underline opacity-70 hover:opacity-100">Batalkan sesi (void)</button>
        @endif
    @elseif ($console->status === \App\Models\Console::MAINTENANCE)
        <div class="mt-8 text-center text-sm">Sedang tidak bisa dipakai</div>
    @else
        <div class="mt-5 text-center text-sm font-medium">{{ $rupiah($console->category->hourly_rate) }} / jam</div>
        <button type="button"
                @click.stop="mode = 'package'; modal = { type: 'start', console: @js($consolePayload) }"
                class="mt-3 w-full rounded-lg bg-green-600 py-2 text-sm font-semibold text-white hover:bg-green-700">
            Mulai Main
        </button>
    @endif
</div>