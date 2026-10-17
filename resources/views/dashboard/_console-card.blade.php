{{-- Kartu satu meja PS. Variabel masuk: $console --}}
@php
    $session = $console->activeSession;

    if ($console->status === \App\Models\Console::MAINTENANCE) {
        $label = 'Maintenance';
        $card  = 'border-slate-300 bg-slate-200 text-slate-500';
        $badge = 'bg-slate-500 text-white';
    } elseif ($session && $session->billing_mode === \App\Models\RentalSession::MODE_PACKAGE) {
        $label = 'Main Paket';
        $card  = 'border-blue-300 bg-blue-50 text-blue-900';
        $badge = 'bg-blue-600 text-white';
    } elseif ($session) {
        $label = 'Open Bill';
        $card  = 'border-orange-300 bg-orange-50 text-orange-900';
        $badge = 'bg-orange-500 text-white';
    } else {
        $label = 'Tersedia';
        $card  = 'border-green-300 bg-green-50 text-green-900';
        $badge = 'bg-green-600 text-white';
    }

    $rupiah = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

<div
    @click="selected = @js(['id' => $console->id, 'name' => $console->name])"
    :class="selected && selected.id === {{ $console->id }} ? 'ring-4 ring-indigo-400' : ''"
    class="cursor-pointer rounded-2xl border-2 p-4 shadow-sm transition {{ $card }}"
>
    <div class="flex items-start justify-between gap-2">
        <div>
            <div class="font-bold">{{ $console->name }}</div>
            <div class="text-xs opacity-70">{{ $console->category->name }}</div>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $label }}</span>
    </div>

    @if ($session)
        <div class="mt-3 text-sm font-medium">{{ $session->customer_name ?? 'Tanpa nama' }}</div>

        {{-- Timer asli (countdown / stopwatch) dipasang di langkah berikutnya --}}
        <div class="my-2 text-center text-4xl font-bold tabular-nums">--:--:--</div>
        <div class="text-center text-xs opacity-70">
            Mulai {{ $session->start_time->format('H:i') }}
            @if ($session->planned_end_time)
                , selesai {{ $session->planned_end_time->format('H:i') }}
            @endif
        </div>

        <div class="mt-3 text-sm">
            Sewa {{ $rupiah($session->rental_amount) }} + F&amp;B {{ $rupiah($session->fnb_amount) }}
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
            <button type="button" disabled class="rounded-lg bg-white/70 py-2 font-semibold opacity-60">+ F&amp;B</button>
            <button type="button" disabled class="rounded-lg bg-white/70 py-2 font-semibold opacity-60">+ Waktu</button>
            <button type="button" disabled class="rounded-lg bg-red-600 py-2 font-semibold text-white opacity-60">Stop / Bayar</button>
        </div>
    @elseif ($console->status === \App\Models\Console::MAINTENANCE)
        <div class="mt-8 text-center text-sm">Sedang tidak bisa dipakai</div>
    @else
        <div class="mt-5 text-center text-sm font-medium">{{ $rupiah($console->category->hourly_rate) }} / jam</div>
        <button type="button" disabled class="mt-3 w-full rounded-lg bg-green-600 py-2 text-sm font-semibold text-white opacity-60">
            Mulai Main
        </button>
    @endif
</div>
