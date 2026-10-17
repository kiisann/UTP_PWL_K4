@extends('layouts.admin')

@section('title', 'Inventori & Menu F&B — PlayZone POS')
@section('header_title', 'Manajemen Inventori & Menu Makanan/Minuman (F&B)')
@section('header_subtitle', 'Kelola harga modal (HPP), harga jual, dan stok produk F&B di kasir')

@section('content')
<!-- Form Tambah Menu F&B Baru -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Tambah Menu F&amp;B Baru</h3>
  <form action="{{ route('admin.products.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
    @csrf
    <input type="text" name="sku" required placeholder="SKU (mis: FNB-009)" class="border border-slate-200 rounded-xl px-3 py-2 font-mono">
    <input type="text" name="name" required placeholder="Nama Menu" class="border border-slate-200 rounded-xl px-3 py-2">
    <select name="category" required class="border border-slate-200 rounded-xl px-3 py-2">
      <option value="makanan">Makanan</option>
      <option value="minuman">Minuman</option>
      <option value="camilan">Camilan</option>
    </select>
    <input type="number" name="cost_price" required placeholder="HPP (Rp)" class="border border-slate-200 rounded-xl px-3 py-2">
    <input type="number" name="sell_price" required placeholder="Harga Jual (Rp)" class="border border-slate-200 rounded-xl px-3 py-2">
    <div class="flex gap-2">
      <input type="number" name="stock" required value="20" placeholder="Stok" class="border border-slate-200 rounded-xl px-3 py-2 w-20">
      <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-xs flex-1">Simpan</button>
    </div>
  </form>
</div>

<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-slate-900 text-base mb-4">Katalog Produk &amp; Stok F&amp;B</h3>
  <table class="w-full text-left border-collapse text-sm">
    <thead>
      <tr class="border-b border-slate-200 text-xs uppercase text-slate-400">
        <th class="py-3 px-4">SKU</th>
        <th class="py-3 px-4">Nama Produk</th>
        <th class="py-3 px-4">Kategori</th>
        <th class="py-3 px-4">HPP</th>
        <th class="py-3 px-4">Harga Jual</th>
        <th class="py-3 px-4">Stok</th>
        <th class="py-3 px-4 text-right">Tambah Stok (Restock)</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @foreach($products as $prod)
      <tr>
        <td class="py-3 px-4 font-mono text-xs">{{ $prod->sku }}</td>
        <td class="py-3 px-4 font-semibold">{{ $prod->name }}</td>
        <td class="py-3 px-4 uppercase text-xs">{{ $prod->category }}</td>
        <td class="py-3 px-4">Rp {{ number_format($prod->cost_price, 0, ',', '.') }}</td>
        <td class="py-3 px-4 font-bold">Rp {{ number_format($prod->sell_price, 0, ',', '.') }}</td>
        <td class="py-3 px-4">
          <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $prod->stock <= 10 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700' }}">
            {{ $prod->stock }} pcs
          </span>
        </td>
        <td class="py-3 px-4 text-right">
          <form action="{{ route('admin.products.restock', $prod) }}" method="POST" class="inline-flex items-center gap-1.5">
            @csrf
            <input type="number" name="added_stock" value="10" min="1" class="w-16 border border-slate-200 rounded-lg px-2 py-1 text-xs">
            <button type="submit" class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">+ Restock</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
