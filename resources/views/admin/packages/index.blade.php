@extends('layouts.admin')

@section('title', 'Paket Harga Main — PlayZone POS')
@section('header_title', 'Manajemen Paket Harga Main (Fixed Package)')
@section('header_subtitle', 'Atur daftar paket durasi tetap untuk masing-masing kategori konsol PlayStation')

@section('content')


@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
  <svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
  {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm font-medium">
  <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
  {{ session('error') }}
</div>
@endif


<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h3 class="font-bold text-slate-900 text-base">
        Daftar Paket Waktu Bermain
        <span class="ml-1 text-xs font-mono text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-md">pricing_packages</span>
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">Paket durasi tetap (Countdown) untuk memberikan harga lebih hemat dibanding tarif per jam reguler</p>
    </div>

    <button type="button" onclick="openModal('modalTambahPaket')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-sm shadow-md shadow-indigo-200 transition-all duration-150">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Buat Paket Harga Baru
    </button>
  </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  @forelse($packages as $pkg)
  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
    <div>
      <div class="flex items-center justify-between">
        <span class="px-2.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-xs font-semibold">{{ $pkg->category->name }}</span>
        <span class="text-xs font-semibold {{ $pkg->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
          {{ $pkg->is_active ? '● Aktif' : '○ Nonaktif' }}
        </span>
      </div>
      <h4 class="font-bold text-slate-900 text-base mt-2.5">{{ $pkg->name }}</h4>
      <div class="text-2xl font-extrabold text-indigo-600 mt-1">Rp {{ number_format($pkg->price, 0, ',', '.') }}</div>
      <p class="text-xs text-slate-500 mt-1">
        Durasi: <strong>{{ $pkg->duration_minutes }} Menit ({{ number_format($pkg->duration_minutes / 60, 1) }} Jam)</strong>
        @php
          $saving = ($pkg->duration_minutes / 60 * $pkg->category->hourly_rate) - $pkg->price;
        @endphp
        @if($saving > 0)
          • Hemat Rp {{ number_format($saving, 0, ',', '.') }}
        @endif
      </p>
    </div>
    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">

      <form action="{{ route('admin.packages.toggle', $pkg) }}" method="POST" class="inline">
        @csrf @method('PATCH')
        <button type="submit" class="text-xs font-semibold {{ $pkg->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }} transition-colors">
          {{ $pkg->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
        </button>
      </form>

      <button type="button"
        onclick="openDeleteModal({{ $pkg->id }}, '{{ addslashes($pkg->name) }}')"
        class="text-xs font-medium text-rose-600 hover:text-rose-700 transition-colors">
        Hapus
      </button>
    </div>
  </div>
  @empty
  <div class="col-span-3 py-16 text-center text-slate-400 text-sm bg-white rounded-2xl border border-slate-200 shadow-sm">
    Belum ada paket harga. Klik <strong>Buat Paket Harga Baru</strong> untuk menambahkan.
  </div>
  @endforelse
</div>



<div id="modalTambahPaket" class="fixed inset-0 z-50 hidden items-center justify-center p-4">

  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalTambahPaket')"></div>

  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h4 class="font-bold text-slate-900 text-base">Buat Paket Harga Baru</h4>
        <p class="text-xs text-slate-400 mt-0.5">Isi detail paket durasi tetap untuk kategori konsol</p>
      </div>
      <button onclick="closeModal('modalTambahPaket')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form action="{{ route('admin.packages.store') }}" method="POST" class="px-6 py-5 space-y-4">
      @csrf

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori Konsol <span class="text-red-500">*</span></label>
        <select name="category_id" required
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-white">
          <option value="" disabled selected>— Pilih Kategori —</option>
          @foreach($categories as $cat)
            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Paket <span class="text-red-500">*</span></label>
        <input type="text" name="name" required placeholder="mis: Paket 3 Jam PS5 Digital"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Durasi (Menit) <span class="text-red-500">*</span></label>
          <input type="number" name="duration_minutes" required placeholder="mis: 180" min="15"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Harga Paket (Rp) <span class="text-red-500">*</span></label>
          <input type="number" name="price" required placeholder="mis: 48000" min="0"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
      </div>

      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalTambahPaket')"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
          Batal
        </button>
        <button type="submit"
          class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-200">
          Simpan Paket
        </button>
      </div>
    </form>
  </div>
</div>



<div id="modalHapusPaket" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalHapusPaket')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 overflow-hidden animate-modal-in">
    <div class="px-6 pt-6 pb-2 text-center">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h4 class="font-bold text-slate-900 text-base mb-1">Hapus Paket Harga?</h4>
      <p class="text-sm text-slate-500">Paket <strong id="deletePaketName" class="text-slate-800"></strong> akan dihapus secara permanen dan tidak dapat dikembalikan.</p>
    </div>
    <form id="formHapusPaket" action="" method="POST" class="px-6 py-4 flex gap-2.5">
      @csrf @method('DELETE')
      <button type="button" onclick="closeModal('modalHapusPaket')"
        class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
        Batal
      </button>
      <button type="submit"
        class="flex-1 px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-sm transition-colors shadow-md shadow-red-200">
        Ya, Hapus
      </button>
    </form>
  </div>
</div>

<style>
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(8px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal-in { animation: modalIn 0.18s ease-out both; }
</style>

<script>
function openModal(id) {
  const modal = document.getElementById(id);
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const modal = document.getElementById(id);
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.style.overflow = '';
}

function openDeleteModal(id, name) {
  const baseUrl = '{{ url("admin/packages") }}';
  document.getElementById('formHapusPaket').action = baseUrl + '/' + id;
  document.getElementById('deletePaketName').textContent = name;
  openModal('modalHapusPaket');
}


document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['modalTambahPaket', 'modalHapusPaket'].forEach(closeModal);
  }
});
</script>
@endsection
