@extends('layouts.admin')

@section('title', 'Laporan & Audit Void — PlayZone POS')
@section('header_title', 'Laporan Pendapatan & Audit Pembatalan (Void Log)')
@section('header_subtitle', 'Rekapitulasi transaksi selesai dan pengawasan pembatalan sesi bermain')

@section('content')
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-bold text-slate-900 text-base">Rekap Nota Sesi Bermain &amp; F&amp;B</h3>
    <a href="{{ route('admin.reports.export') }}" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold">
      Ekspor CSV Laporan
    </a>
  </div>
  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400">
        <th class="py-3 px-4">No. Invoice</th>
        <th class="py-3 px-4">Meja PS</th>
        <th class="py-3 px-4">Pelanggan</th>
        <th class="py-3 px-4">Mode &amp; Durasi</th>
        <th class="py-3 px-4">Sewa PS</th>
        <th class="py-3 px-4">F&amp;B</th>
        <th class="py-3 px-4">Grand Total</th>
        <th class="py-3 px-4">Struk</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @forelse($completedSessions as $s)
      <tr>
        <td class="py-3 px-4 font-mono text-xs font-bold text-blue-600">{{ $s->invoice_number }}</td>
        <td class="py-3 px-4 font-semibold">{{ $s->console->code ?? '-' }}</td>
        <td class="py-3 px-4">{{ $s->customer_name ?? 'Umum' }}</td>
        <td class="py-3 px-4">{{ strtoupper($s->billing_mode) }} ({{ $s->total_minutes }} Menit)</td>
        <td class="py-3 px-4">Rp {{ number_format($s->rental_amount, 0, ',', '.') }}</td>
        <td class="py-3 px-4">Rp {{ number_format($s->fnb_amount, 0, ',', '.') }}</td>
        <td class="py-3 px-4 font-bold">Rp {{ number_format($s->grand_total, 0, ',', '.') }}</td>
        <td class="py-3 px-4">
          <a href="{{ route('sessions.receipt', [$s, 'print' => 1]) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:underline">Cetak ulang</a>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="8" class="py-6 text-center text-slate-400 text-xs">Belum ada transaksi selesai yang dibayar.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="bg-white rounded-2xl p-5 border border-rose-200 shadow-sm">
  <h3 class="font-bold text-rose-700 text-base mb-4">Audit Log Pembatalan Sesi / Void Transaksi</h3>
  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-rose-100 text-xs uppercase text-rose-500">
        <th class="py-2.5 px-4">No. Invoice</th>
        <th class="py-2.5 px-4">Waktu Batal</th>
        <th class="py-2.5 px-4">Meja PS</th>
        <th class="py-2.5 px-4">Kasir</th>
        <th class="py-2.5 px-4">Alasan Pembatalan</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @forelse($voidLogs as $v)
      <tr>
        <td class="py-3 px-4 font-mono text-xs font-bold text-rose-600">{{ $v->invoice_number }}</td>
        <td class="py-3 px-4 text-xs">{{ $v->updated_at->format('d M Y H:i') }}</td>
        <td class="py-3 px-4 font-semibold">{{ $v->console->code ?? '-' }}</td>
        <td class="py-3 px-4">{{ $v->user->name ?? '-' }}</td>
        <td class="py-3 px-4 italic text-xs">{{ $v->notes ?? '-' }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="5" class="py-6 text-center text-slate-400 text-xs">Tidak ada riwayat pembatalan sesi (void).</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
