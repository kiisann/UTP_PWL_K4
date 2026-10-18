<aside
    class="w-[30%] min-w-[340px] overflow-y-auto border-l border-slate-200 bg-white p-4 shadow-sm"
    x-data="{
        tab: 'console', // 'console' | 'walkin'
        products: @js($products ?? []),
        fnbQty: {},
        walkInQty: {},
        walkInCustomer: '',
        walkInMethod: 'cash',
        walkInPaid: 0,
        consoleMethod: 'cash',
        consolePaid: 0,
        showAddFnb: false,

        formatRupiah(n) {
            return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        },

        get walkInItems() {
            return this.products
                .filter(p => (Number(this.walkInQty[p.id]) || 0) > 0)
                .map(p => ({
                    product_id: p.id,
                    name: p.name,
                    price: p.sell_price,
                    qty: Number(this.walkInQty[p.id]),
                    subtotal: Number(this.walkInQty[p.id]) * p.sell_price
                }));
        },

        get walkInTotal() {
            return this.walkInItems.reduce((acc, it) => acc + it.subtotal, 0);
        },

        get walkInChange() {
            if (this.walkInMethod !== 'cash') return 0;
            return Math.max(0, (Number(this.walkInPaid) || 0) - this.walkInTotal);
        },

        setWalkInQuickCash(amount) {
            this.walkInMethod = 'cash';
            this.walkInPaid = amount;
        },

        get fnbItemsToAdd() {
            return this.products
                .filter(p => (Number(this.fnbQty[p.id]) || 0) > 0)
                .map(p => ({
                    product_id: p.id,
                    name: p.name,
                    price: p.sell_price,
                    qty: Number(this.fnbQty[p.id]),
                    subtotal: Number(this.fnbQty[p.id]) * p.sell_price
                }));
        },

        get fnbToAddTotal() {
            return this.fnbItemsToAdd.reduce((acc, it) => acc + it.subtotal, 0);
        },

        setConsoleQuickCash(amount) {
            this.consoleMethod = 'cash';
            this.consolePaid = amount;
        },

        get consoleChange() {
            if (!this.selected || !this.selected.session || this.consoleMethod !== 'cash') return 0;
            return Math.max(0, (Number(this.consolePaid) || 0) - Number(this.selected.session.grand_total));
        }
    }"
    x-init="
        $watch('selected', (val) => {
            if (val && val.session) {
                consolePaid = val.session.grand_total;
                showAddFnb = false;
                fnbQty = {};
            }
        });
        $watch('walkInTotal', (val) => {
            if (walkInMethod === 'qris' || walkInPaid < val) {
                walkInPaid = val;
            }
        });
    "
>
    <!-- Switcher Tab: Mode Konsol vs Walk-in F&B -->
    <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-semibold">
        <button
            type="button"
            @click="tab = 'console'"
            :class="tab === 'console' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            class="flex-1 rounded-lg py-2 transition"
        >
            🎮 Detail Meja
        </button>
        <button
            type="button"
            @click="tab = 'walkin'"
            :class="tab === 'walkin' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            class="flex-1 rounded-lg py-2 transition"
        >
            🍿 Walk-in F&amp;B
        </button>
    </div>

    <!-- TAB 1: MODE KONSOL / MEJA TERPILIH -->
    <div x-show="tab === 'console'" class="mt-4 space-y-4">
        <template x-if="!selected">
            <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                <div class="text-3xl mb-2">🕹️</div>
                <div class="font-semibold text-slate-700">Belum ada meja dipilih</div>
                <p class="mt-1 text-xs text-slate-400">Klik salah satu kartu meja di dashboard untuk melihat rincian tagihan atau pembayaran.</p>
            </div>
        </template>

        <template x-if="selected">
            <div class="space-y-4">
                <!-- Info Header Meja -->
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" x-text="selected.category_name"></span>
                            <h3 class="text-base font-bold text-slate-900" x-text="selected.name"></h3>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="{
                                'bg-green-100 text-green-800': selected.status === 'available',
                                'bg-blue-100 text-blue-800': selected.status === 'occupied',
                                'bg-slate-200 text-slate-700': selected.status === 'maintenance'
                            }"
                            x-text="selected.status === 'available' ? 'Tersedia' : (selected.status === 'occupied' ? 'Sedang Main' : 'Maintenance')"
                        ></span>
                    </div>
                </div>

                <!-- Jika Meja Kosong -->
                <template x-if="!selected.session">
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-5 text-center">
                        <p class="text-sm text-slate-600">Tidak ada sesi aktif di meja ini.</p>
                        <template x-if="selected.status === 'available'">
                            <button
                                type="button"
                                @click="modal = { type: 'start', console: { id: selected.id, name: selected.name, category_id: selected.category_id } }"
                                class="mt-3 w-full rounded-xl bg-green-600 py-2.5 text-xs font-bold text-white hover:bg-green-700 shadow-sm transition"
                            >
                                Mulai Main di Meja Ini
                            </button>
                        </template>
                    </div>
                </template>

                <!-- Jika Ada Sesi Berjalan / Menunggu Bayar -->
                <template x-if="selected.session">
                    <div class="space-y-3">
                        <!-- Metadata Sesi -->
                        <div class="rounded-xl border border-slate-200 p-3.5 text-xs space-y-1.5 bg-white shadow-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">No. Invoice</span>
                                <span class="font-mono font-bold text-slate-800" x-text="selected.session.invoice_number"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Pelanggan</span>
                                <span class="font-semibold text-slate-800" x-text="selected.session.customer_name || 'Umum'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Mulai</span>
                                <span class="font-medium text-slate-800" x-text="selected.session.start_time"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Status Waktu</span>
                                <span
                                    class="font-bold"
                                    :class="selected.session.stopped ? 'text-indigo-600' : 'text-blue-600'"
                                    x-text="selected.session.stopped ? 'Selesai (Menunggu Bayar)' : 'Sedang Berjalan'"
                                ></span>
                            </div>
                        </div>

                        <!-- Rincian Biaya -->
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3.5 text-xs space-y-2">
                            <div class="font-bold text-slate-800 text-sm border-b border-indigo-100 pb-1.5">Rincian Tagihan</div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Sewa PlayStation</span>
                                <span class="font-semibold text-slate-900" x-text="formatRupiah(selected.session.rental_amount)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Pesanan F&amp;B</span>
                                <span class="font-semibold text-slate-900" x-text="formatRupiah(selected.session.fnb_amount)"></span>
                            </div>
                            <template x-if="selected.session.discount_amount > 0">
                                <div class="flex justify-between text-rose-600">
                                    <span>Diskon</span>
                                    <span class="font-semibold" x-text="'- ' + formatRupiah(selected.session.discount_amount)"></span>
                                </div>
                            </template>
                            <div class="flex justify-between border-t border-indigo-200/60 pt-2 text-sm font-bold text-indigo-950">
                                <span>Grand Total</span>
                                <span class="text-base text-indigo-700" x-text="formatRupiah(selected.session.grand_total)"></span>
                            </div>
                        </div>

                        <!-- Daftar Item F&B Terpesan -->
                        <div class="rounded-xl border border-slate-200 p-3.5 text-xs bg-white">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-slate-800">Daftar F&amp;B di Meja</span>
                                <button
                                    type="button"
                                    @click="showAddFnb = !showAddFnb"
                                    class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                >
                                    <span x-text="showAddFnb ? '✕ Tutup Form' : '+ Tambah F&B'"></span>
                                </button>
                            </div>

                            <template x-if="!selected.session.fnb_items || selected.session.fnb_items.length === 0">
                                <p class="text-slate-400 italic">Belum ada pesanan F&amp;B pada sesi ini.</p>
                            </template>

                            <template x-if="selected.session.fnb_items && selected.session.fnb_items.length > 0">
                                <div class="divide-y divide-slate-100 space-y-1">
                                    <template x-for="(item, idx) in selected.session.fnb_items" :key="idx">
                                        <div class="flex justify-between pt-1">
                                            <span class="text-slate-700" x-text="item.name + ' (' + item.qty + 'x)'"></span>
                                            <span class="font-medium text-slate-900" x-text="formatRupiah(item.subtotal)"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Form Tambah F&B ke Sesi -->
                            <div x-show="showAddFnb" x-cloak class="mt-3 border-t border-slate-200 pt-3">
                                <form method="POST" :action="'{{ url('/sessions') }}/' + selected.session.id + '/fnb'" class="space-y-2">
                                    @csrf
                                    <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1">
                                        <template x-for="p in products" :key="p.id">
                                            <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-2 text-xs">
                                                <div>
                                                    <div class="font-semibold text-slate-800" x-text="p.name"></div>
                                                    <div class="text-[11px] text-slate-500" x-text="formatRupiah(p.sell_price) + ' (stok: ' + p.stock + ')'"></div>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" @click="fnbQty[p.id] = Math.max(0, (fnbQty[p.id] || 0) - 1)" class="w-6 h-6 rounded bg-slate-200 font-bold hover:bg-slate-300">-</button>
                                                    <span class="w-6 text-center font-bold" x-text="fnbQty[p.id] || 0"></span>
                                                    <button type="button" @click="fnbQty[p.id] = Math.min(p.stock, (fnbQty[p.id] || 0) + 1)" :disabled="(fnbQty[p.id] || 0) >= p.stock" class="w-6 h-6 rounded bg-slate-200 font-bold hover:bg-slate-300 disabled:opacity-40">+</button>
                                                    <template x-if="(fnbQty[p.id] || 0) > 0">
                                                        <input type="hidden" :name="'items[' + p.id + '][product_id]'" :value="p.id">
                                                    </template>
                                                    <template x-if="(fnbQty[p.id] || 0) > 0">
                                                        <input type="hidden" :name="'items[' + p.id + '][qty]'" :value="fnbQty[p.id]">
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="fnbToAddTotal > 0">
                                        <div class="flex justify-between items-center pt-2 font-bold text-xs text-emerald-800">
                                            <span>Tambahan F&amp;B:</span>
                                            <span x-text="formatRupiah(fnbToAddTotal)"></span>
                                        </div>
                                    </template>

                                    <button
                                        type="submit"
                                        :disabled="fnbToAddTotal === 0"
                                        class="w-full mt-2 rounded-xl bg-emerald-600 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition"
                                    >
                                        Simpan Pesanan F&amp;B
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- BAGIAN PEMBAYARAN CHECKOUT KONSOL -->
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                            <h4 class="font-bold text-slate-900 text-sm mb-2.5">💳 Pembayaran Sesi</h4>

                            <form method="POST" :action="'{{ url('/sessions') }}/' + selected.session.id + '/checkout'" class="space-y-3">
                                @csrf

                                <!-- Pilihan Metode Bayar -->
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <button
                                        type="button"
                                        @click="consoleMethod = 'cash'; consolePaid = Math.max(consolePaid, selected.session.grand_total)"
                                        :class="consoleMethod === 'cash' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-300 text-slate-700'"
                                        class="rounded-lg py-2 transition"
                                    >
                                        💵 Tunai (Cash)
                                    </button>
                                    <button
                                        type="button"
                                        @click="consoleMethod = 'qris'; consolePaid = selected.session.grand_total"
                                        :class="consoleMethod === 'qris' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-300 text-slate-700'"
                                        class="rounded-lg py-2 transition"
                                    >
                                        📱 QRIS
                                    </button>
                                </div>
                                <input type="hidden" name="payment_method" :value="consoleMethod">

                                <!-- Nominal Bayar -->
                                <div>
                                    <label class="text-xs font-medium text-slate-700">Nominal Uang Diterima (Rp)</label>
                                    <input
                                        type="number"
                                        name="paid_amount"
                                        x-model.number="consolePaid"
                                        :readonly="consoleMethod === 'qris'"
                                        min="0"
                                        step="1000"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    >
                                </div>

                                <!-- Tombol Uang Cepat (Hanya saat Tunai) -->
                                <div x-show="consoleMethod === 'cash'" class="space-y-1.5">
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Uang Cepat</div>
                                    <div class="grid grid-cols-3 gap-1.5 text-xs">
                                        <button type="button" @click="setConsoleQuickCash(selected.session.grand_total)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Uang Pas</button>
                                        <button type="button" @click="setConsoleQuickCash(20000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">20.000</button>
                                        <button type="button" @click="setConsoleQuickCash(50000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">50.000</button>
                                        <button type="button" @click="setConsoleQuickCash(100000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">100.000</button>
                                        <button type="button" @click="setConsoleQuickCash(Math.ceil(selected.session.grand_total / 10000) * 10000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">+10 Ribu</button>
                                        <button type="button" @click="setConsoleQuickCash(Math.ceil(selected.session.grand_total / 50000) * 50000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">+50 Ribu</button>
                                    </div>

                                    <!-- Kembalian -->
                                    <div class="flex justify-between items-center rounded-lg bg-emerald-100/70 px-3 py-2 mt-2 text-xs font-bold text-emerald-950">
                                        <span>Kembalian:</span>
                                        <span class="text-sm" x-text="formatRupiah(consoleChange)"></span>
                                    </div>
                                </div>

                                <!-- Tombol Bayar -->
                                <button
                                    type="submit"
                                    :disabled="consoleMethod === 'cash' && consolePaid < selected.session.grand_total"
                                    class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50 shadow-sm transition"
                                >
                                    Bayar Sekarang &amp; Selesaikan Sesi
                                </button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- TAB 2: MODE WALK-IN F&B (BELI TANPA MAIN PS) -->
    <div x-show="tab === 'walkin'" x-cloak class="mt-4 space-y-4">
        <div class="rounded-xl bg-emerald-50/70 border border-emerald-200 p-3.5">
            <h3 class="text-sm font-bold text-emerald-900">🍿 Pesanan F&amp;B Walk-in</h3>
            <p class="text-xs text-emerald-700 mt-0.5">Penjualan snack &amp; minuman langsung di kasir tanpa sesi rental PS.</p>
        </div>

        <form method="POST" action="{{ route('fnb-orders.walk-in') }}" class="space-y-4">
            @csrf

            <div>
                <label class="text-xs font-medium text-slate-700">Nama Pelanggan (opsional)</label>
                <input
                    type="text"
                    name="customer_name"
                    x-model="walkInCustomer"
                    placeholder="Contoh: Budi"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium"
                >
            </div>

            <!-- Pilih Produk F&B -->
            <div>
                <label class="text-xs font-semibold text-slate-800">Pilih Produk</label>
                <div class="mt-1.5 max-h-56 overflow-y-auto space-y-2 pr-1">
                    <template x-for="p in products" :key="p.id">
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-2.5 shadow-2xs text-xs">
                            <div>
                                <div class="font-bold text-slate-900" x-text="p.name"></div>
                                <div class="text-slate-500 text-[11px]" x-text="formatRupiah(p.sell_price) + ' • Stok ' + p.stock"></div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="walkInQty[p.id] = Math.max(0, (walkInQty[p.id] || 0) - 1)" class="w-6 h-6 rounded bg-slate-100 font-bold hover:bg-slate-200">-</button>
                                <span class="w-6 text-center font-bold text-slate-900" x-text="walkInQty[p.id] || 0"></span>
                                <button type="button" @click="walkInQty[p.id] = Math.min(p.stock, (walkInQty[p.id] || 0) + 1)" :disabled="(walkInQty[p.id] || 0) >= p.stock" class="w-6 h-6 rounded bg-slate-100 font-bold hover:bg-slate-200 disabled:opacity-40">+</button>
                                <template x-if="(walkInQty[p.id] || 0) > 0">
                                    <input type="hidden" :name="'items[' + p.id + '][product_id]'" :value="p.id">
                                </template>
                                <template x-if="(walkInQty[p.id] || 0) > 0">
                                    <input type="hidden" :name="'items[' + p.id + '][qty]'" :value="walkInQty[p.id]">
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Ringkasan Total Walk-in -->
            <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50 text-xs space-y-1.5">
                <div class="flex justify-between font-bold text-sm text-slate-900">
                    <span>Total Pesanan</span>
                    <span class="text-emerald-700" x-text="formatRupiah(walkInTotal)"></span>
                </div>
            </div>

            <!-- Pembayaran Walk-in -->
            <div x-show="walkInTotal > 0" class="space-y-3 pt-1">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button
                        type="button"
                        @click="walkInMethod = 'cash'; walkInPaid = Math.max(walkInPaid, walkInTotal)"
                        :class="walkInMethod === 'cash' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-300 text-slate-700'"
                        class="rounded-lg py-2 transition"
                    >
                        💵 Tunai
                    </button>
                    <button
                        type="button"
                        @click="walkInMethod = 'qris'; walkInPaid = walkInTotal"
                        :class="walkInMethod === 'qris' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-300 text-slate-700'"
                        class="rounded-lg py-2 transition"
                    >
                        📱 QRIS
                    </button>
                </div>
                <input type="hidden" name="payment_method" :value="walkInMethod">

                <div>
                    <label class="text-xs font-medium text-slate-700">Uang Diterima (Rp)</label>
                    <input
                        type="number"
                        name="paid_amount"
                        x-model.number="walkInPaid"
                        :readonly="walkInMethod === 'qris'"
                        min="0"
                        step="1000"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    >
                </div>

                <div x-show="walkInMethod === 'cash'" class="space-y-1.5">
                    <div class="grid grid-cols-3 gap-1.5 text-xs">
                        <button type="button" @click="setWalkInQuickCash(walkInTotal)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Uang Pas</button>
                        <button type="button" @click="setWalkInQuickCash(20000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">20.000</button>
                        <button type="button" @click="setWalkInQuickCash(50000)" class="rounded-lg border border-slate-300 bg-white py-1.5 font-semibold text-slate-700 hover:bg-slate-50">50.000</button>
                    </div>

                    <div class="flex justify-between items-center rounded-lg bg-emerald-100/70 px-3 py-2 mt-2 text-xs font-bold text-emerald-950">
                        <span>Kembalian:</span>
                        <span class="text-sm" x-text="formatRupiah(walkInChange)"></span>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="walkInTotal === 0 || (walkInMethod === 'cash' && walkInPaid < walkInTotal)"
                    class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50 shadow-sm transition"
                >
                    Bayar &amp; Cetak Struk Walk-in
                </button>
            </div>
        </form>
    </div>
</aside>
