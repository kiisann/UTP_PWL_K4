@extends('layouts.admin')

@section('title', 'Master Konsol & Tarif')
@section('header_title', 'Master Kategori Konsol & Daftar Meja PS')
@section('header_subtitle', 'Kelola tarif dasar per jam, aturan pembulatan Open Bill, dan status operasional tiap bilik')

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
  <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
      <h3 class="font-bold text-slate-900 text-base">
        Kategori Konsol &amp; Parameter Tarif
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">Atur tarif dasar per jam, batas durasi minimum Open Bill, dan blok pembulatan menit</p>
    </div>

    <button type="button" onclick="openModal('modalTambahKategori')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-sm shadow-md shadow-indigo-200 transition-all duration-150">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Tambah Kategori Konsol
    </button>
  </div>

  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400 tracking-wide">
        <th class="py-3 px-4">Nama Kategori</th>
        <th class="py-3 px-4">Tarif / Jam</th>
        <th class="py-3 px-4">Min. Charge (Open Bill)</th>
        <th class="py-3 px-4">Pembulatan Menit</th>
        <th class="py-3 px-4">Fasilitas / Keterangan</th>
        <th class="py-3 px-4 text-center">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @forelse($categories as $cat)
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="py-3 px-4 font-semibold text-slate-800">{{ $cat->name }}</td>
        <td class="py-3 px-4 font-bold text-indigo-600">Rp {{ number_format($cat->hourly_rate, 0, ',', '.') }}</td>
        <td class="py-3 px-4 text-slate-600">{{ $cat->min_charge_minutes }} Menit</td>
        <td class="py-3 px-4 text-slate-600">Tiap {{ $cat->rounding_minutes }} Menit</td>
        <td class="py-3 px-4 text-xs text-slate-500">{{ $cat->description ?? '-' }}</td>
        <td class="py-3 px-4">
          <div class="flex items-center justify-center gap-1.5">

            <button type="button"
              onclick="openEditModal({{ $cat->id }}, '{{ addslashes($cat->name) }}', {{ $cat->hourly_rate }}, {{ $cat->min_charge_minutes }}, {{ $cat->rounding_minutes }}, '{{ addslashes($cat->description ?? '') }}')"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:border-indigo-300 text-slate-600 hover:text-indigo-600 text-xs font-medium transition-all duration-150 shadow-sm">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Edit Tarif
            </button>

            <button type="button"
              onclick="openDeleteModal({{ $cat->id }}, '{{ addslashes($cat->name) }}')"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-red-50 hover:border-red-300 text-slate-600 hover:text-red-600 text-xs font-medium transition-all duration-150 shadow-sm">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              Hapus
            </button>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" class="py-10 text-center text-slate-400 text-sm">Belum ada kategori konsol. Tambahkan kategori baru.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>


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
      <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-700 transition-colors">+ Tambah Unit</button>
    </form>
  </div>

  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400 tracking-wide">
        <th class="py-3 px-4">Kode Meja</th>
        <th class="py-3 px-4">Nama Tampilan</th>
        <th class="py-3 px-4">Kategori</th>
        <th class="py-3 px-4">Status</th>
        <th class="py-3 px-4 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @foreach($consoles as $unit)
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="py-3 px-4 font-mono font-bold">{{ $unit->code }}</td>
        <td class="py-3 px-4">{{ $unit->name }}</td>
        <td class="py-3 px-4">{{ $unit->category->name }}</td>
        <td class="py-3 px-4 uppercase text-xs font-semibold
          @if($unit->status === 'available') text-emerald-600
          @elseif($unit->status === 'occupied') text-orange-500
          @else text-red-500 @endif">
          {{ $unit->status }}
        </td>
        <td class="py-3 px-4 text-right">
          <form action="{{ route('admin.consoles.units.toggle', $unit) }}" method="POST" class="inline">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-100 transition-colors">
              {{ $unit->status === 'maintenance' ? 'Aktifkan Unit' : 'Set Maintenance' }}
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>



<div id="modalTambahKategori" class="fixed inset-0 z-50 hidden items-center justify-center p-4">

  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalTambahKategori')"></div>

  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h4 class="font-bold text-slate-900 text-base">Tambah Kategori Konsol</h4>
        <p class="text-xs text-slate-400 mt-0.5">Isi detail kategori dan parameter tarif</p>
      </div>
      <button onclick="closeModal('modalTambahKategori')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form action="{{ route('admin.consoles.categories.store') }}" method="POST" class="px-6 py-5 space-y-4">
      @csrf
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
        <input type="text" name="name" required placeholder="mis: PS5 VIP Room"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tarif / Jam (Rp) <span class="text-red-500">*</span></label>
          <input type="number" name="hourly_rate" required placeholder="10000"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Min. Charge (Menit) <span class="text-red-500">*</span></label>
          <input type="number" name="min_charge_minutes" required value="30"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pembulatan (Menit) <span class="text-red-500">*</span></label>
          <input type="number" name="rounding_minutes" required value="15"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Fasilitas / Keterangan</label>
        <input type="text" name="description" placeholder="mis: Smart TV 43 Inch FHD, 2 DualShock 4, AC Central"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>
      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalTambahKategori')"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
          Batal
        </button>
        <button type="submit"
          class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-200">
          Simpan Kategori
        </button>
      </div>
    </form>
  </div>
</div>



<div id="modalEditKategori" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalEditKategori')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h4 class="font-bold text-slate-900 text-base">Edit Tarif Kategori</h4>
        <p id="editModalSubtitle" class="text-xs text-slate-400 mt-0.5">Memperbarui data kategori</p>
      </div>
      <button onclick="closeModal('modalEditKategori')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formEditKategori" action="" method="POST" class="px-6 py-5 space-y-4">
      @csrf @method('PUT')
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
        <input type="text" id="editName" name="name" required
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tarif / Jam (Rp) <span class="text-red-500">*</span></label>
          <input type="number" id="editHourlyRate" name="hourly_rate" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Min. Charge (Menit) <span class="text-red-500">*</span></label>
          <input type="number" id="editMinCharge" name="min_charge_minutes" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pembulatan (Menit) <span class="text-red-500">*</span></label>
          <input type="number" id="editRounding" name="rounding_minutes" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Fasilitas / Keterangan</label>
        <input type="text" id="editDescription" name="description"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>
      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalEditKategori')"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
          Batal
        </button>
        <button type="submit"
          class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-200">
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>



<div id="modalHapusKategori" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalHapusKategori')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 overflow-hidden animate-modal-in">
    <div class="px-6 pt-6 pb-2 text-center">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h4 class="font-bold text-slate-900 text-base mb-1">Hapus Kategori?</h4>
      <p class="text-sm text-slate-500">Kategori <strong id="deleteKategoriName" class="text-slate-800"></strong> akan dihapus secara permanen. Pastikan tidak ada unit yang terdaftar.</p>
    </div>
    <form id="formHapusKategori" action="" method="POST" class="px-6 py-4 flex gap-2.5">
      @csrf @method('DELETE')
      <button type="button" onclick="closeModal('modalHapusKategori')"
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

function openEditModal(id, name, hourlyRate, minCharge, rounding, description) {
  const baseUrl = '{{ url("admin/consoles/categories") }}';
  document.getElementById('formEditKategori').action = baseUrl + '/' + id;
  document.getElementById('editModalSubtitle').textContent = 'Memperbarui: ' + name;
  document.getElementById('editName').value = name;
  document.getElementById('editHourlyRate').value = hourlyRate;
  document.getElementById('editMinCharge').value = minCharge;
  document.getElementById('editRounding').value = rounding;
  document.getElementById('editDescription').value = description;
  openModal('modalEditKategori');
}

function openDeleteModal(id, name) {
  const baseUrl = '{{ url("admin/consoles/categories") }}';
  document.getElementById('formHapusKategori').action = baseUrl + '/' + id;
  document.getElementById('deleteKategoriName').textContent = name;
  openModal('modalHapusKategori');
}


document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['modalTambahKategori', 'modalEditKategori', 'modalHapusKategori'].forEach(closeModal);
  }
});
</script>
@endsection
