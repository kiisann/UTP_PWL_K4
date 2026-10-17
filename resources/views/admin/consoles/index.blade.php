@extends('layouts.admin')

@section('title', 'Master Konsol & Tarif — PlayZone POS')
@section('header_title', 'Master Kategori Konsol & Daftar Meja PS')
@section('header_subtitle', 'Kelola tarif dasar per jam, aturan pembulatan Open Bill, dan status operasional tiap bilik')

@section('content')
<!-- Form Tambah Kategori Konsol -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Tambah Kategori Konsol Baru</h3>
  <form action="{{ route('admin.consoles.categories.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-sm">
    @csrf
    <input type="text" name="name" required placeholder="Nama Kategori (mis: PS5 VIP)" class="border border-slate-200 rounded-xl px-3 py-2">
    <input type="number" name="hourly_rate" required placeholder="Tarif / Jam (Rp)" class="border border-slate-200 rounded-xl px-3 py-2">
    <input type="number" name="min_charge_minutes" required value="30" placeholder="Min. Charge (Menit)" class="border border-slate-200 rounded-xl px-3 py-2">
    <input type="number" name="rounding_minutes" required value="15" placeholder="Pembulatan (Menit)" class="border border-slate-200 rounded-xl px-3 py-2">
    <div class="flex gap-2">
      <input type="text" name="description" placeholder="Fasilitas" class="border border-slate-200 rounded-xl px-3 py-2 flex-1">
      <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-xs">Simpan</button>
    </div>
  </form>
</div>

<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Daftar Kategori Konsol</h3>
  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400">
        <th class="py-3 px-4">Kategori</th>
        <th class="py-3 px-4">Tarif / Jam</th>
        <th class="py-3 px-4">Min. Charge</th>
        <th class="py-3 px-4">Pembulatan</th>
        <th class="py-3 px-4">Fasilitas</th>
        <th class="py-3 px-4">Jumlah Unit</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @foreach($categories as $cat)
      <tr>
        <td class="py-3 px-4 font-semibold">{{ $cat->name }}</td>
        <td class="py-3 px-4 font-bold text-blue-600">Rp {{ number_format($cat->hourly_rate, 0, ',', '.') }}</td>
        <td class="py-3 px-4">{{ $cat->min_charge_minutes }} Menit</td>
        <td class="py-3 px-4">Tiap {{ $cat->rounding_minutes }} Menit</td>
        <td class="py-3 px-4 text-xs text-slate-500">{{ $cat->description ?? '-' }}</td>
        <td class="py-3 px-4">{{ $cat->consoles_count }} Meja</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<!-- Form Tambah Meja / Unit PS -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-bold text-slate-900 text-base">Daftar Unit / Bilik Meja PS</h3>
    <form action="{{ route('admin.consoles.units.store') }}" method="POST" class="flex flex-wrap items-center gap-2 text-xs">
      @csrf
      <input type="text" name="code" required placeholder="Kode (MEJA-09)" class="border border-slate-200 rounded-xl px-3 py-2 font-mono">
      <input type="text" name="name" required placeholder="Nama Tampilan" class="border border-slate-200 rounded-xl px-3 py-2">
      <select name="category_id" required class="border border-slate-200 rounded-xl px-3 py-2">
        @foreach($categories as $cat)
          <option value="{{ $cat->id }}">{{ $cat->name }}</option>
        @endforeach
      </select>
      <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white font-semibold">+ Tambah Unit</button>
    </form>
  </div>

  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400">
        <th class="py-3 px-4">Kode Meja</th>
        <th class="py-3 px-4">Nama Tampilan</th>
        <th class="py-3 px-4">Kategori</th>
        <th class="py-3 px-4">Status</th>
        <th class="py-3 px-4 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @foreach($consoles as $unit)
      <tr>
        <td class="py-3 px-4 font-mono font-bold">{{ $unit->code }}</td>
        <td class="py-3 px-4">{{ $unit->name }}</td>
        <td class="py-3 px-4">{{ $unit->category->name }}</td>
        <td class="py-3 px-4 uppercase text-xs font-semibold">{{ $unit->status }}</td>
        <td class="py-3 px-4 text-right">
          <form action="{{ route('admin.consoles.units.toggle', $unit) }}" method="POST" class="inline">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-100">
              {{ $unit->status === 'maintenance' ? 'Aktifkan Unit' : 'Set Maintenance' }}
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
