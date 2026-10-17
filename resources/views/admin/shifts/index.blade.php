@extends('layouts.admin')

@section('title', 'Kasir & Rekonsiliasi Shift — PlayZone POS')
@section('header_title', 'Manajemen Akun Kasir & Rekonsiliasi Shift')
@section('header_subtitle', 'Kelola hak akses operator dan pantau kecocokan uang tunai fisik di laci kasir')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-5">
    <div>
      <h3 class="font-bold text-slate-900 text-base mb-3">Tambah Akun Operator / Admin</h3>
      <form action="{{ route('admin.shifts.users.store') }}" method="POST" class="space-y-2.5 text-xs">
        @csrf
        <input type="text" name="name" required placeholder="Nama Lengkap" class="w-full border border-slate-200 rounded-xl px-3 py-2">
        <input type="text" name="username" required placeholder="Username Login" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-mono">
        <input type="password" name="password" required placeholder="Password (min. 6 karakter)" class="w-full border border-slate-200 rounded-xl px-3 py-2">
        <div class="flex gap-2">
          <select name="role" class="border border-slate-200 rounded-xl px-3 py-2 flex-1">
            <option value="cashier">Cashier (Operator Kasir)</option>
            <option value="admin">Admin / Owner</option>
          </select>
          <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold">+ Buat Akun</button>
        </div>
      </form>
    </div>

    <div>
      <h3 class="font-bold text-slate-900 text-base mb-3">Daftar Akun Pengguna (users)</h3>
      <div class="space-y-2.5">
        @foreach($users as $u)
        <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between">
          <div>
            <div class="font-semibold text-sm">{{ $u->name }}</div>
            <div class="text-xs text-slate-500 font-mono">{{ '@' . $u->username }} &bull; {{ strtoupper($u->role) }}</div>
          </div>
          <form action="{{ route('admin.shifts.users.toggle', $u) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
              {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </form>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
    <h3 class="font-bold text-slate-900 text-base mb-4">Riwayat Rekonsiliasi Shift (cashier_shifts)</h3>
    <table class="w-full text-left border-collapse text-sm">
      <thead>
        <tr class="border-b border-slate-200 text-xs uppercase text-slate-400">
          <th class="py-3 px-3">Waktu Buka</th>
          <th class="py-3 px-3">Operator</th>
          <th class="py-3 px-3">Modal Awal</th>
          <th class="py-3 px-3">Kas Sistem</th>
          <th class="py-3 px-3">Fisik Laci</th>
          <th class="py-3 px-3">QRIS</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @foreach($shifts as $s)
        <tr>
          <td class="py-3 px-3">{{ $s->opened_at->format('d M Y H:i') }}</td>
          <td class="py-3 px-3">{{ $s->user->name ?? '-' }}</td>
          <td class="py-3 px-3">Rp {{ number_format($s->starting_cash, 0, ',', '.') }}</td>
          <td class="py-3 px-3 font-semibold">Rp {{ number_format($s->expected_cash, 0, ',', '.') }}</td>
          <td class="py-3 px-3">{{ $s->actual_cash !== null ? 'Rp ' . number_format($s->actual_cash, 0, ',', '.') : 'Sedang Berjalan' }}</td>
          <td class="py-3 px-3 text-blue-600 font-semibold">Rp {{ number_format($s->qris_total, 0, ',', '.') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
