@extends('layouts.admin')

@section('title', 'Paket Harga Main — PlayZone POS')
@section('header_title', 'Manajemen Paket Harga Main (Fixed Package)')
@section('header_subtitle', 'Atur daftar paket durasi tetap untuk masing-masing kategori konsol PlayStation')

@section('content')
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Tambah Paket Harga Baru</h3>
  <form action="{{ route('admin.packages.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
    @csrf
    <select name="category_id" required class="border border-slate-200 rounded-xl px-3 py-2">
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
      @endforeach
    </select>
    <input type="text" name="name" required placeholder="Nama Paket (Misal: Paket 3 Jam)" class="border border-slate-200 rounded-xl px-3 py-2">
    <input type="number" name="duration_minutes" required placeholder="Durasi (Menit, mis: 180)" class="border border-slate-200 rounded-xl px-3 py-2">
    <div class="flex gap-2">
      <input type="number" name="price" required placeholder="Harga (Rp)" class="border border-slate-200 rounded-xl px-3 py-2 flex-1">
      <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-xs">Simpan</button>
    </div>
  </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  @foreach($packages as $pkg)
  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between">
        <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-blue-700 text-xs font-semibold">{{ $pkg->category->name }}</span>
        <span class="text-xs font-semibold {{ $pkg->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
          {{ $pkg->is_active ? '● Aktif' : '○ Nonaktif' }}
        </span>
      </div>
      <h4 class="font-bold text-slate-900 text-base mt-2.5">{{ $pkg->name }}</h4>
      <div class="text-2xl font-extrabold text-blue-600 mt-1">Rp {{ number_format($pkg->price, 0, ',', '.') }}</div>
      <p class="text-xs text-slate-500 mt-1">Durasi: <strong>{{ $pkg->duration_minutes }} Menit ({{ number_format($pkg->duration_minutes / 60, 1) }} Jam)</strong></p>
    </div>
    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
      <form action="{{ route('admin.packages.toggle', $pkg) }}" method="POST">
        @csrf @method('PATCH')
        <button type="submit" class="text-xs font-semibold text-amber-600">
          {{ $pkg->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
        </button>
      </form>
      <form action="{{ route('admin.packages.destroy', $pkg) }}" method="POST">
        @csrf @method('DELETE')
        <button type="submit" class="text-xs font-medium text-rose-600">Hapus</button>
      </form>
    </div>
  </div>
  @endforeach
</div>
@endsection
