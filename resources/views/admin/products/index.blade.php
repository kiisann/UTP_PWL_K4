@extends('layouts.admin')

@section('title', 'Inventori & Menu F&B')
@section('header_title', 'Manajemen Inventori & Menu Makanan/Minuman (F&B)')
@section('header_subtitle', 'Kelola harga modal (HPP), harga jual, dan stok produk F&B di kasir')

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


<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">


  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
    <div>
      <h3 class="font-bold text-slate-900 text-base">
        Katalog Produk &amp; Stok F&amp;B
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">Stok berkurang otomatis saat dipesan ke bilik PS maupun pembelian langsung di kasir</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Search Bar  -->
      <form method="GET" action="{{ route('admin.products.index') }}" class="flex items-center gap-2">
        <div class="relative">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Cari nama menu atau SKU..."
            class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition w-52">
        </div>
        <!-- Filter Kategori  -->
        <select name="category" onchange="this.form.submit()"
          class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-white">
          <option value="all" {{ request('category', 'all') === 'all' ? 'selected' : '' }}>Semua Kategori</option>
          <option value="makanan"  {{ request('category') === 'makanan'  ? 'selected' : '' }}>Makanan</option>
          <option value="minuman"  {{ request('category') === 'minuman'  ? 'selected' : '' }}>Minuman</option>
          <option value="camilan"  {{ request('category') === 'camilan'  ? 'selected' : '' }}>Camilan</option>
        </select>
      </form>
      <!-- Tombol Tambah  -->
      <button type="button" onclick="openModal('modalTambahFnB')"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-sm shadow-md shadow-indigo-200 transition-all duration-150 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Menu F&amp;B
      </button>
    </div>
  </div>

  <!-- Tabel Produk -->
  <table class="w-full text-left text-sm">
    <colgroup>
      <col style="width:9%">
      <col style="width:22%">
      <col style="width:9%">
      <col style="width:13%">
      <col style="width:11%">
      <col style="width:11%">
      <col style="width:14%">
      <col style="width:11%">
    </colgroup>
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400 tracking-wide">
        <th class="py-3 pl-5 pr-2">SKU</th>
        <th class="py-3 px-3">Nama Produk</th>
        <th class="py-3 px-3">Kategori</th>
        <th class="py-3 px-3">Harga Modal (HPP)</th>
        <th class="py-3 px-3">Harga Jual</th>
        <th class="py-3 px-3">Margin</th>
        <th class="py-3 px-3">Stok Tersedia</th>
        <th class="py-3 pl-3 pr-5 text-right">Manajemen Stok</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @forelse($products as $prod)
      @php $margin = $prod->sell_price - $prod->cost_price; @endphp
      <tr class="hover:bg-slate-50 transition-colors">
        <td class="py-4 pl-5 pr-2 font-mono text-xs text-slate-400 font-medium whitespace-nowrap">{{ $prod->sku }}</td>
        <td class="py-4 px-3 font-semibold text-slate-800">{{ $prod->name }}</td>
        <td class="py-4 px-3">
          @php
            $catLabel = ['makanan' => 'Makanan', 'minuman' => 'Minuman', 'camilan' => 'Camilan'][$prod->category] ?? ucfirst($prod->category);
          @endphp
          <span class="text-slate-600 text-xs">{{ $catLabel }}</span>
        </td>
        <td class="py-4 px-3 text-slate-500 text-xs whitespace-nowrap">Rp {{ number_format($prod->cost_price, 0, ',', '.') }}</td>
        <td class="py-4 px-3 font-bold text-slate-800 whitespace-nowrap">Rp {{ number_format($prod->sell_price, 0, ',', '.') }}</td>
        <td class="py-4 px-3 whitespace-nowrap">
          <span class="text-emerald-600 font-semibold text-xs">+Rp {{ number_format($margin, 0, ',', '.') }}</span>
        </td>
        <td class="py-4 px-3">
          @if($prod->stock <= 10)
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 whitespace-nowrap">
              {{ $prod->stock }} pcs <span class="font-normal">(Stok Tipis)</span>
            </span>
          @else
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 whitespace-nowrap">
              {{ $prod->stock }} pcs
            </span>
          @endif
        </td>
        <td class="py-4 pl-3 pr-5 text-right">
          <button type="button"
            onclick="openRestockModal({{ $prod->id }}, '{{ addslashes($prod->name) }}', {{ $prod->stock }}, '{{ route('admin.products.restock', $prod) }}')"
            class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition-colors whitespace-nowrap">
            + Restock
          </button>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="8" class="py-12 text-center text-slate-400 text-sm">
          Belum ada produk F&amp;B. Klik <strong>Tambah Menu F&amp;B</strong> untuk menambahkan.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>



<div id="modalTambahFnB" class="fixed inset-0 z-50 hidden items-center justify-center p-4">

  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalTambahFnB')"></div>

  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h4 class="font-bold text-slate-900 text-base">Tambah Menu F&amp;B Baru</h4>
        <p class="text-xs text-slate-400 mt-0.5">Isi detail produk makanan atau minuman</p>
      </div>
      <button onclick="closeModal('modalTambahFnB')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form action="{{ route('admin.products.store') }}" method="POST" class="px-6 py-5 space-y-4">
      @csrf

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">SKU <span class="text-red-500">*</span></label>
          <input type="text" name="sku" required placeholder="mis: FNB-009"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition uppercase"
            style="text-transform:uppercase">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori <span class="text-red-500">*</span></label>
          <select name="category" required
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-white">
            <option value="" disabled selected>— Pilih —</option>
            <option value="makanan">Makanan</option>
            <option value="minuman">Minuman</option>
            <option value="camilan">Camilan</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Menu <span class="text-red-500">*</span></label>
        <input type="text" name="name" required placeholder="mis: Es Teh Manis Jumbo"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Harga Modal / HPP (Rp) <span class="text-red-500">*</span></label>
          <input type="number" name="cost_price" required placeholder="mis: 1500" min="0"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            oninput="hitungMargin()">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Harga Jual (Rp) <span class="text-red-500">*</span></label>
          <input type="number" name="sell_price" required placeholder="mis: 4000" min="0"
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            oninput="hitungMargin()">
        </div>
      </div>

      <div id="previewMargin" class="hidden px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-100 text-xs text-emerald-700 font-medium"></div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Stok Awal (pcs) <span class="text-red-500">*</span></label>
        <input type="number" name="stock" required value="20" min="0"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>

      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalTambahFnB')"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
          Batal
        </button>
        <button type="submit"
          class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-200">
          Simpan Menu
        </button>
      </div>
    </form>
  </div>
</div>



<div id="modalRestock" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalRestock')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h4 id="restockModalTitle" class="font-bold text-slate-900 text-sm leading-snug">Tambah Stok (Restock)</h4>
      <button onclick="closeModal('modalRestock')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formRestock" action="" method="POST" class="px-6 py-5 space-y-4">
      @csrf

      <div class="px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm">
        Stok saat ini: <strong id="restockCurrentStock" class="text-slate-900">— pcs</strong>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Jumlah Stok Masuk (+)</label>
        <input type="number" id="restockInput" name="added_stock" value="20" min="1" required
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-slate-800">
      </div>

      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalRestock')"
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

function hitungMargin() {
  const form     = document.getElementById('modalTambahFnB').querySelector('form');
  const hpp      = parseFloat(form.querySelector('[name=cost_price]').value) || 0;
  const jual     = parseFloat(form.querySelector('[name=sell_price]').value) || 0;
  const preview  = document.getElementById('previewMargin');
  const margin   = jual - hpp;
  if (hpp > 0 && jual > 0) {
    preview.classList.remove('hidden');
    const pct = hpp > 0 ? ((margin / hpp) * 100).toFixed(1) : 0;
    preview.textContent = margin >= 0
      ? `✓ Margin: +Rp ${margin.toLocaleString('id-ID')} (${pct}%)`
      : `⚠ Harga jual lebih rendah dari HPP! Rugi Rp ${Math.abs(margin).toLocaleString('id-ID')}`;
    preview.className = margin >= 0
      ? 'px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-100 text-xs text-emerald-700 font-medium'
      : 'px-3 py-2 rounded-xl bg-red-50 border border-red-100 text-xs text-red-700 font-medium';
  } else {
    preview.classList.add('hidden');
  }
}

function openRestockModal(id, name, stock, actionUrl) {
  document.getElementById('restockModalTitle').textContent = 'Tambah Stok (Restock): ' + name;
  document.getElementById('restockCurrentStock').textContent = stock + ' pcs';
  document.getElementById('restockInput').value = 20;
  document.getElementById('formRestock').action = actionUrl;
  openModal('modalRestock');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('modalTambahFnB');
    closeModal('modalRestock');
  }
});
</script>
@endsection
