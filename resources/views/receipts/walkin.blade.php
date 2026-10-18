@php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $methodLabel = ['cash' => 'TUNAI', 'qris' => 'QRIS'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk Walk-in {{ $order->order_number }}</title>
    <style>
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
        <a href="{{ route('fnb-orders.receipt', [$order, 'width' => 58]) }}" class="{{ $width === 58 ? 'on' : '' }}">Kertas 58mm</a>
        <a href="{{ route('fnb-orders.receipt', [$order, 'width' => 80]) }}" class="{{ $width === 80 ? 'on' : '' }}">Kertas 80mm</a>
        <a href="{{ route('dashboard') }}">Kembali ke kasir</a>
    </div>

    <main class="paper">
        <div class="c">
            <div class="b big">{{ $store['name'] }}</div>
            <div>{{ $store['address'] }}</div>
            @if (! empty($store['phone']))
                <div>WA/Telp: {{ $store['phone'] }}</div>
            @endif
        </div>

        <div class="stamp">STRUK PEMBELIAN F&amp;B</div>
        @if ($order->order_status === 'paid')
            <div class="stamp">LUNAS</div>
        @endif
        <hr class="sep">

        <div class="row"><span>No. Nota</span><span>{{ $order->order_number }}</span></div>
        <div class="row"><span>Waktu</span><span>{{ $printedAt->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Kasir</span><span>{{ $order->shift->user->name ?? '-' }}</span></div>
        <div class="row"><span>Pelanggan</span><span>{{ $order->customer_name ?: 'Umum' }}</span></div>

        <hr class="sep">
        <div class="b">RINCIAN PESANAN</div>
        @foreach ($order->items as $item)
            <div class="item-name">{{ $item->product->name ?? 'Produk' }}</div>
            <div class="row">
                <span>&nbsp;&nbsp;{{ $item->qty }} x {{ $rp($item->unit_price) }}</span>
                <span>{{ $rp($item->subtotal) }}</span>
            </div>
        @endforeach

        <hr class="sep">
        <div class="row b big"><span>TOTAL</span><span>Rp {{ $rp($order->total_amount) }}</span></div>

        @if ($payment)
            <hr class="sep">
            <div class="row">
                <span>Metode Bayar</span>
                <span>{{ $methodLabel[$payment->payment_method] ?? strtoupper($payment->payment_method) }}</span>
            </div>
            <div class="row"><span>Dibayar</span><span>Rp {{ $rp($payment->paid_amount) }}</span></div>
            @if ($payment->payment_method === 'cash')
                <div class="row"><span>Kembalian</span><span>Rp {{ $rp($payment->change_amount) }}</span></div>
            @endif
        @endif

        <hr class="sep">
        <div class="c" style="margin-top: 8px;">
            <div>{{ $store['receipt_footer'] }}</div>
        </div>
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
