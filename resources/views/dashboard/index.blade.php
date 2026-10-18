@extends('layouts.app')

@section('title', 'Dashboard Kasir')

@section('content')
    @include('dashboard._header')

    @if (session('status'))
        <div class="bg-green-600 px-5 py-2 text-sm text-white flex items-center justify-between">
            <span>{{ session('status') }}</span>
            @if (session('print_receipt_url'))
                <a href="{{ session('print_receipt_url') }}" target="_blank" class="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-green-800 hover:bg-green-50 shadow-xs">🖨️ Cetak Struk</a>
            @endif
        </div>
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
            'products' => $products ?? [],
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

                <template x-if="modal && modal.type === 'void'">
                    <form method="POST" :action="'{{ url('/sessions') }}/' + modal.session_id + '/void'" class="space-y-3">
                        @csrf
                        <h3 class="text-lg font-bold text-red-700">Batalkan sesi: <span x-text="modal.console.name"></span></h3>
                        <p class="rounded-lg bg-red-50 p-2 text-xs text-red-800">
                            Meja akan dikosongkan, stok F&amp;B pada sesi ini dikembalikan, dan pembatalan tercatat di void log admin.
                            Sesi yang sudah dibayar tidak bisa dibatalkan dari sini.
                        </p>

                        <div>
                            <label class="text-sm font-medium">Alasan pembatalan <span class="text-red-600">*</span></label>
                            <input type="text" name="reason" required minlength="3" maxlength="200"
                                   placeholder="Contoh: pelanggan batal main / salah input meja"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm">Kembali</button>
                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Batalkan Sesi</button>
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

                <!-- MODAL CHECKOUT / PEMBAYARAN SESI -->
                <template x-if="modal && modal.type === 'checkout'">
                    <form method="POST" :action="'{{ url('/sessions') }}/' + modal.session_id + '/checkout'" class="space-y-4">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <h3 class="text-lg font-bold text-slate-900">Pembayaran: <span x-text="modal.console.name"></span></h3>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">Checkout</span>
                        </div>

                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-600">Biaya Sewa PS</span>
                                <span class="font-semibold text-slate-900" x-text="formatRupiah(modal.rental || 0)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Pesanan F&amp;B</span>
                                <span class="font-semibold text-slate-900" x-text="formatRupiah(modal.fnb || 0)"></span>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-1.5 text-sm font-bold text-slate-900">
                                <span>Total Tagihan</span>
                                <span class="text-base text-emerald-700 font-extrabold" x-text="formatRupiah(modal.grand_total)"></span>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-700">Metode Pembayaran</label>
                            <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                                <button type="button"
                                        @click="checkoutMethod = 'cash'; checkoutPaid = Math.max(checkoutPaid || 0, modal.grand_total)"
                                        :class="checkoutMethod === 'cash' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 text-slate-700'"
                                        class="rounded-lg py-2 transition">
                                    💵 Tunai (Cash)
                                </button>
                                <button type="button"
                                        @click="checkoutMethod = 'qris'; checkoutPaid = modal.grand_total"
                                        :class="checkoutMethod === 'qris' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 text-slate-700'"
                                        class="rounded-lg py-2 transition">
                                    📱 QRIS
                                </button>
                            </div>
                            <input type="hidden" name="payment_method" :value="checkoutMethod">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-700">Nominal Uang Diterima (Rp)</label>
                            <input type="number"
                                   name="paid_amount"
                                   x-model.number="checkoutPaid"
                                   :readonly="checkoutMethod === 'qris'"
                                   min="0"
                                   step="1000"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        </div>

                        <div x-show="checkoutMethod === 'cash'" class="space-y-1.5 text-xs">
                            <div class="grid grid-cols-3 gap-1.5">
                                <button type="button" @click="checkoutPaid = modal.grand_total" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Uang Pas</button>
                                <button type="button" @click="checkoutPaid = 20000" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">20.000</button>
                                <button type="button" @click="checkoutPaid = 50000" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">50.000</button>
                                <button type="button" @click="checkoutPaid = 100000" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">100.000</button>
                                <button type="button" @click="checkoutPaid = Math.ceil(modal.grand_total / 10000) * 10000" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">+10 Ribu</button>
                                <button type="button" @click="checkoutPaid = Math.ceil(modal.grand_total / 50000) * 50000" class="rounded-lg border border-slate-200 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">+50 Ribu</button>
                            </div>

                            <div class="flex justify-between items-center rounded-lg bg-emerald-100/70 px-3 py-2 mt-2 font-bold text-emerald-950">
                                <span>Kembalian:</span>
                                <span class="text-sm" x-text="formatRupiah(Math.max(0, (checkoutPaid || 0) - modal.grand_total))"></span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit"
                                    :disabled="checkoutMethod === 'cash' && (checkoutPaid || 0) < modal.grand_total"
                                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition">
                                Bayar &amp; Selesaikan Sesi
                            </button>
                        </div>
                    </form>
                </template>

                <!-- MODAL TAMBAH F&B KE SESI -->
                <template x-if="modal && modal.type === 'fnb'">
                    <form method="POST" :action="'{{ url('/sessions') }}/' + modal.session_id + '/fnb'" class="space-y-4">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <h3 class="text-lg font-bold text-slate-900">Tambah F&amp;B: <span x-text="modal.console.name"></span></h3>
                            <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-800">Pesanan</span>
                        </div>

                        <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                            <template x-for="p in products" :key="p.id">
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-2.5 text-xs shadow-2xs">
                                    <div>
                                        <div class="font-bold text-slate-900" x-text="p.name"></div>
                                        <div class="text-slate-500 text-[11px]" x-text="formatRupiah(p.sell_price) + ' • Stok: ' + p.stock"></div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" @click="fnbModalQty[p.id] = Math.max(0, (fnbModalQty[p.id] || 0) - 1)" class="w-6 h-6 rounded bg-slate-100 font-bold hover:bg-slate-200">-</button>
                                        <span class="w-6 text-center font-bold text-slate-900" x-text="fnbModalQty[p.id] || 0"></span>
                                        <button type="button" @click="fnbModalQty[p.id] = Math.min(p.stock, (fnbModalQty[p.id] || 0) + 1)" :disabled="(fnbModalQty[p.id] || 0) >= p.stock" class="w-6 h-6 rounded bg-slate-100 font-bold hover:bg-slate-200 disabled:opacity-40">+</button>
                                        <template x-if="(fnbModalQty[p.id] || 0) > 0">
                                            <input type="hidden" :name="'items[' + p.id + '][product_id]'" :value="p.id">
                                        </template>
                                        <template x-if="(fnbModalQty[p.id] || 0) > 0">
                                            <input type="hidden" :name="'items[' + p.id + '][qty]'" :value="fnbModalQty[p.id]">
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-between items-center rounded-lg bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900">
                            <span>Total Tambahan:</span>
                            <span class="text-emerald-700" x-text="formatRupiah(Object.keys(fnbModalQty).reduce((acc, id) => {
                                const prod = products.find(p => p.id == id);
                                return acc + (prod ? prod.sell_price * fnbModalQty[id] : 0);
                            }, 0))"></span>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modal = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit"
                                    :disabled="Object.values(fnbModalQty).reduce((a, b) => a + b, 0) === 0"
                                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition">
                                Tambah ke Meja
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
@endsection