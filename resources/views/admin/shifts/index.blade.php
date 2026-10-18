@extends('layouts.admin')

@section('title', 'Kasir & Rekonsiliasi Shift — PlayZone POS')
@section('header_title', 'Manajemen Akun Kasir & Rekonsiliasi Shift')
@section('header_subtitle', 'Kelola hak akses operator dan pantau kecocokan uang tunai fisik di laci kasir')

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">


  <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col gap-4">


    <div class="flex items-center justify-between">
      <div>
        <h3 class="font-bold text-slate-900 text-base">Akun Operator &amp; Admin</h3>
        <p class="text-xs text-slate-400 mt-0.5">Tabel <span class="font-mono text-slate-500">users</span> (RBAC Middleware)</p>
      </div>

      <button type="button" onclick="openModal('modalTambahAkun')"
        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-semibold text-xs shadow-md shadow-indigo-200 transition-all duration-150 whitespace-nowrap">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        + Akun
      </button>
    </div>


    <div class="space-y-2.5">
      @forelse($users as $u)
      <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between hover:bg-slate-50 transition-colors">
        <div>
          <div class="font-semibold text-sm text-slate-800">{{ $u->name }}</div>
          <div class="text-xs text-slate-400 font-mono mt-0.5">
            {{ '@' . $u->username }}
            <span class="mx-1 text-slate-300">•</span>
            <span class="font-semibold {{ $u->role === 'admin' ? 'text-indigo-600' : 'text-slate-500' }}">{{ strtoupper($u->role) }}</span>
          </div>
        </div>
        <form action="{{ route('admin.shifts.users.toggle', $u) }}" method="POST">
          @csrf @method('PATCH')
          <button type="submit"
            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors {{ $u->is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
            {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
          </button>
        </form>
      </div>
      @empty
      <div class="py-6 text-center text-slate-400 text-xs">Belum ada akun pengguna.</div>
      @endforelse
    </div>
  </div>


  <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">


    <div class="px-5 py-4 border-b border-slate-100">
      <h3 class="font-bold text-slate-900 text-base">
        Riwayat Rekonsiliasi Laci Kasir
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">Membandingkan uang tunai seharusnya di sistem <span class="font-mono">(expected_cash)</span> dengan uang fisik aktual saat tutup shift</p>
    </div>


    <table class="w-full text-left text-sm">
      <colgroup>
        <col style="width:22%">
        <col style="width:14%">
        <col style="width:12%">
        <col style="width:13%">
        <col style="width:13%">
        <col style="width:12%">
        <col style="width:14%">
      </colgroup>
      <thead>
        <tr class="border-b border-slate-200 text-xs uppercase text-slate-400 tracking-wide">
          <th class="py-3 pl-5 pr-3">Waktu Shift</th>
          <th class="py-3 px-3">Operator</th>
          <th class="py-3 px-3">Modal Awal</th>
          <th class="py-3 px-3">Kas Sistem</th>
          <th class="py-3 px-3">Fisik Laci</th>
          <th class="py-3 px-3">Total QRIS</th>
          <th class="py-3 pl-3 pr-5">Status Rekonsiliasi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse($shifts as $s)
        @php
          $isOpen   = $s->actual_cash === null;
          $selisih  = $isOpen ? null : ($s->actual_cash - $s->expected_cash);
          $sesuai   = !$isOpen && $selisih === 0;
          $lebih    = !$isOpen && $selisih > 0;
        @endphp
        <tr class="hover:bg-slate-50 transition-colors">

          <td class="py-4 pl-5 pr-3 text-xs text-slate-600 whitespace-nowrap">
            {{ $s->opened_at->format('d M Y (H:i') }} –
            {{ $isOpen ? 'berjalan)' : $s->closed_at->format('H:i)') }}
          </td>

          <td class="py-4 px-3 font-medium text-slate-700 text-xs">{{ $s->user->name ?? '-' }}</td>

          <td class="py-4 px-3 text-slate-500 text-xs whitespace-nowrap">
            Rp {{ number_format($s->starting_cash, 0, ',', '.') }}
          </td>

          <td class="py-4 px-3 font-semibold text-slate-800 text-xs whitespace-nowrap">
            Rp {{ number_format($s->expected_cash, 0, ',', '.') }}
          </td>

          <td class="py-4 px-3 text-slate-600 text-xs whitespace-nowrap">
            {{ $isOpen ? '–' : 'Rp ' . number_format($s->actual_cash, 0, ',', '.') }}
          </td>

          <td class="py-4 px-3 text-indigo-600 font-semibold text-xs whitespace-nowrap">
            Rp {{ number_format($s->qris_total, 0, ',', '.') }}
          </td>

          <td class="py-4 pl-3 pr-5">
            @if($isOpen)
              <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-100 text-sky-700 whitespace-nowrap">
                Shift Sedang Aktif
              </span>
            @elseif($sesuai)
              <span class="inline-flex flex-col items-start px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 whitespace-nowrap">
                Sesuai
                <span class="font-normal text-emerald-600">(Balance)</span>
              </span>
            @elseif($lebih)
              <span class="inline-flex flex-col items-start px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 whitespace-nowrap">
                Lebih Rp {{ number_format($selisih, 0, ',', '.') }}
              </span>
            @else
              <span class="inline-flex flex-col items-start px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-50 text-red-600 whitespace-nowrap">
                Selisih Rp
                <span>{{ number_format($selisih, 0, ',', '.') }}</span>
              </span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-10 text-center text-slate-400 text-sm">Belum ada riwayat shift.</td>
        </tr>
        @endforelse
      </tbody>
    </table>


    @if($shifts->hasPages())
    <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-500">
      {{ $shifts->links() }}
    </div>
    @endif
  </div>

</div>



<div id="modalTambahAkun" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeModal('modalTambahAkun')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 overflow-hidden animate-modal-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div>
        <h4 class="font-bold text-slate-900 text-base">Tambah Akun Baru</h4>
        <p class="text-xs text-slate-400 mt-0.5">Buat akun operator kasir atau administrator</p>
      </div>
      <button onclick="closeModal('modalTambahAkun')" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form action="{{ route('admin.shifts.users.store') }}" method="POST" class="px-6 py-5 space-y-4">
      @csrf

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
        <input type="text" name="name" required placeholder="mis: Rizky Pratama"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Username Login <span class="text-red-500">*</span></label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-mono">@</span>
          <input type="text" name="username" required placeholder="kasir_rizky"
            class="w-full border border-slate-200 rounded-xl pl-7 pr-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password <span class="text-red-500">*</span></label>
        <input type="password" name="password" required placeholder="Minimal 6 karakter"
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Role <span class="text-red-500">*</span></label>
        <select name="role" required
          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-white">
          <option value="cashier">Cashier (Operator Kasir)</option>
          <option value="admin">Admin / Owner</option>
        </select>
      </div>

      <div class="flex gap-2.5 pt-1">
        <button type="button" onclick="closeModal('modalTambahAkun')"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-sm hover:bg-slate-50 transition-colors">
          Batal
        </button>
        <button type="submit"
          class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-200">
          Buat Akun
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
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal('modalTambahAkun');
});
</script>
@endsection
