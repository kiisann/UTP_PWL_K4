@extends('layouts.app')

@section('title', 'Dashboard Kasir')

@section('content')
    @include('dashboard._header')

    @if (session('status'))
        <div class="bg-green-600 px-5 py-2 text-sm text-white">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-red-600 px-5 py-2 text-sm text-white">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="bg-red-600 px-5 py-2 text-sm text-white">{{ $errors->first() }}</div>
    @endif

    <div
        x-data="dashboard(@js([
            'packages' => $packages,
            'available' => $availableConsoles,
            'signature' => $signature,
            'liveUrl' => route('api.consoles.live'),
        ]))"
        class="flex min-h-0 flex-1"
    >
        <main class="w-[70%] overflow-y-auto p-4">
            <div class="mb-3 flex items-center justify-between gap-3 text-sm">
                <span
                    x-show="$store.alarm.on && $store.alarm.count > 0 && !$store.alarm.ready"
                    x-cloak
                    class="font-semibold text-red-600"
                >
                    Ada meja yang waktunya habis. Klik di mana saja untuk mengaktifkan suara alarm.
                </span>
                <span x-show="!($store.alarm.on && $store.alarm.count > 0 && !$store.alarm.ready)" class="text-slate-400">
                    Warna kartu: biru = paket, kuning = sisa 10 menit, merah = habis, oranye = Open Bill
                </span>

                <button type="button" @click="$store.alarm.toggle()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1 font-medium hover:bg-slate-50">
                    <span x-text="$store.alarm.on ? 'Suara alarm: ON' : 'Suara alarm: OFF'"></span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @forelse ($consoles as $console)
                    @include('dashboard._console-card', ['console' => $console])
                @empty
                    <p class="col-span-full text-center text-slate-500">
                        Belum ada data meja. Jalankan: php artisan migrate:fresh --seed
                    </p>
                @endforelse
            </div>
        </main>

        @include('dashboard._billing-panel')

        <div
            x-show="modal" x-cloak
            @keydown.escape.window="modal = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div @click.outside="modal = null" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">

                <template x-if="modal && modal.type === 'start'">
                    <form method="POST" action="{{ route('sessions.start') }}" class="space-y-3">
                        @csrf
                        <h3 class="text-lg font-bold">Mulai Main: <span x-text="modal.console.name"></span></h3>
                        <input type="hidden" name="console_id" :value="modal.console.id">

                        <div>
                            <label class="text-sm font-medium">Nama pelanggan</label>
                            <input type="text" name="customer_name" maxlength="100" placeholder="Opsional"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div>
                            <label class="text-sm font-medium">Mode</label>
                            <select name="billing_mode" x-model="mode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="package">Paket Waktu (hitung mundur)</option>
                                <option value="open">Open Bill (hitung maju)</option>
                            </select>
                        </div>

                        <div x-show="mode === 'package'">
                            <label class="text-sm font-medium">Paket</label>
                            <select name="package_id" :disabled="mode !== 'package'" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <template x-for="p in (packages[modal.console.category_id] || [])" :key="p.id">
                                    <option :value="p.id" x-text="p.name + ' - Rp ' + Number(p.price).toLocaleString('id-ID')"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Batal</button>
                            <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">Mulai</button>
                        </div>
                    </form>
                </template>

                <template x-if="modal && modal.type === 'extend'">
                    <form method="POST" :action="'{{ url('/sessions') }}/' + modal.session_id + '/extend'" class="space-y-3">
                        @csrf
                        <h3 class="text-lg font-bold">Tambah Waktu: <span x-text="modal.console.name"></span></h3>

                        <div>
                            <label class="text-sm font-medium">Paket tambahan</label>
                            <select name="package_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">- atau isi menit manual di bawah -</option>
                                <template x-for="p in (packages[modal.console.category_id] || [])" :key="p.id">
                                    <option :value="p.id" x-text="p.name + ' - Rp ' + Number(p.price).toLocaleString('id-ID')"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Menit manual (dihitung dari tarif per jam)</label>
                            <input type="number" name="minutes" min="1" max="600" placeholder="Contoh: 15"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Batal</button>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Tambah</button>
                        </div>
                    </form>
                </template>

                <template x-if="modal && modal.type === 'move'">
                    <form method="POST" :action="'{{ url('/sessions') }}/' + modal.session_id + '/move'" class="space-y-3">
                        @csrf
                        <h3 class="text-lg font-bold">Pindah dari <span x-text="modal.console.name"></span></h3>

                        <div>
                            <label class="text-sm font-medium">Meja tujuan (yang kosong)</label>
                            <select name="console_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <template x-for="c in available.filter(c => c.id !== modal.console.id)" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                            <p x-show="available.length === 0" class="mt-1 text-xs text-red-600">Tidak ada meja kosong.</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="mark_old_maintenance" value="1">
                            Tandai meja lama sebagai Maintenance (rusak)
                        </label>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Batal</button>
                            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Pindahkan</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
@endsection