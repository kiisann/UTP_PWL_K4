@php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $methodLabel = ['cash' => 'TUNAI', 'qris' => 'QRIS'];
    $console = $s->console;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $s->invoice_number }}</title>
    <style>
        /* Lebar kertas thermal. Kalau tepi struk terpotong di printer Anda, besarkan --pad. */
        :root { --paper: {{ $width }}mm; --pad: 4mm; --fs: {{ $width === 58 ? '11px' : '12px' }}; }

        @page { size: {{ $width }}mm auto; margin: 0; }

        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: "Courier New", ui-monospace, monospace; font-size: var(--fs); line-height: 1.35; }

        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 12px; font-family: system-ui, sans-serif; font-size: 14px; }
        .toolbar a, .toolbar button { border: 1px solid #94a3b8; background: #fff; color: #0f172a; padding: 6px 12px; border-radius: 6px; text-decoration: none; cursor: pointer; font: inherit; }
        .toolbar .on { background: #0f172a; color: #fff; border-color: #0f172a; }

        .paper { width: var(--paper); margin: 0 auto 24px; padding: var(--pad); background: #fff; }

        .c { text-align: center; }
        .b { font-weight: bold; }
        .big { font-size: 1.25em; }
        .sep { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row > span:last-child { text-align: right; white-space: nowrap; }
        .item-name { word-break: break-word; }
        .stamp { border: 2px solid #000; text-align: center; font-weight: bold; padding: 3px 0; margin: 6px 0; letter-spacing: .5px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { margin: 0; width: var(--paper); }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="{{ route('sessions.receipt', [$s, 'width' => 58]) }}" class="{{ $width === 58 ? 'on' : '' }}">Kertas 58mm</a>
        <a href="{{ route('sessions.receipt', [$s, 'width' => 80]) }}" class="{{ $width === 80 ? 'on' : '' }}">Kertas 80mm</a>
        <a href="{{ route('dashboard') }}">Kembali ke kasir</a>
    </div>

    <main class="paper">
        {{-- Header toko --}}
        <div class="c">
            <div class="b big">{{ $store['name'] }}</div>
            <div>{{ $store['address'] }}</div>
            @if (! empty($store['phone']))
                <div>WA/Telp: {{ $store['phone'] }}</div>
            @endif
        </div>

        @if ($isVoid)
            <div class="stamp">*** DIBATALKAN (VOID) ***</div>
        @elseif (! $isPaid)
            <div class="stamp">PRATINJAU - BELUM LUNAS</div>
        @endif

        <hr class="sep">

        {{-- Metadata transaksi --}}
        <div class="row"><span>Nota</span><span>{{ $s->invoice_number }}</span></div>
        <div class="row"><span>Dicetak</span><span>{{ $printedAt->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $s->user->name ?? '-' }}</span></div>
        <div class="row"><span>Meja</span><span>{{ $console->name ?? '-' }}</span></div>
        <div class="row"><span>Pelanggan</span><span>{{ $s->customer_name ?: 'Umum' }}</span></div>

        <hr class="sep">

        {{-- Rincian sewa PS --}}
        <div class="b">SEWA PS</div>
        <div class="row">
            <span>Mode</span>
            <span>{{ $isPackage ? ($s->package->name ?? 'Paket') : 'Open Bill' }}</span>
        </div>
        <div class="row"><span>Jam</span><span>{{ $s->start_time->format('H:i') }} - {{ $endLabel ?? '-' }}</span></div>
        @if ($isPackage)
            <div class="row"><span>Durasi</span><span>{{ $billedMinutes }} menit</span></div>
        @else
            <div class="row"><span>Durasi main</span><span>{{ $actualMinutes }} menit</span></div>
            <div class="row"><span>Ditagih</span><span>{{ $billedMinutes }} menit</span></div>
        @endif
        <div class="row b"><span>Subtotal Sewa</span><span>{{ $rp($rental) }}</span></div>

        {{-- Rincian F&B --}}
        @if ($fnbItems->isNotEmpty())
            <hr class="sep">
            <div class="b">PESANAN F&amp;B</div>
            @foreach ($fnbItems as $item)
                <div class="item-name">{{ $item['name'] }}</div>
                <div class="row">
                    <span>&nbsp;&nbsp;{{ $item['qty'] }} x {{ $rp($item['unit_price']) }}</span>
                    <span>{{ $rp($item['subtotal']) }}</span>
                </div>
            @endforeach
            <div class="row b"><span>Subtotal F&amp;B</span><span>{{ $rp($fnb) }}</span></div>
        @endif

        <hr class="sep">

        {{-- Ringkasan pembayaran --}}
        <div class="row"><span>Subtotal Sewa PS</span><span>{{ $rp($rental) }}</span></div>
        <div class="row"><span>Subtotal F&amp;B</span><span>{{ $rp($fnb) }}</span></div>
        @if ($discount > 0)
            <div class="row"><span>Diskon</span><span>-{{ $rp($discount) }}</span></div>
        @endif
        <div class="row b big"><span>TOTAL</span><span>Rp {{ $rp($grandTotal) }}</span></div>

        @if ($isPaid && $payment)
            <hr class="sep">
            <div class="row"><span>Metode</span><span>{{ $methodLabel[$payment->payment_method] ?? strtoupper($payment->payment_method) }}</span></div>
            <div class="row"><span>Bayar</span><span>Rp {{ $rp($payment->paid_amount) }}</span></div>
            <div class="row"><span>Kembali</span><span>Rp {{ $rp($payment->change_amount) }}</span></div>
            <div class="row"><span>Waktu bayar</span><span>{{ $payment->paid_at?->format('d/m/Y H:i') }}</span></div>
        @endif

        <hr class="sep">
        <div class="c">{{ $store['receipt_footer'] }}</div>
    </main>

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
    @endif
</body>
</html>
