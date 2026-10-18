<aside
    class="w-[30%] overflow-y-auto border-l border-slate-200 bg-white p-4"
    x-data="{
        products: [],
        quantities: {},
        customerName: '',
        activeSessionId: null,
        walkInMode: false,
        loadingProducts: false,
        submitting: false,
        paying: false,
        message: '',
        error: false,
        lastOrderId: null,
        lastOrderTotal: 0,
        paymentMethod: 'cash',
        paidAmount: 0,

        async loadProducts() {
            this.loadingProducts = true;

            try {
                const response = await fetch(@js(route('fnb-products.index')), {
                    headers: { Accept: 'application/json' }
                });

                if (!response.ok) throw new Error('Gagal memuat produk.');

                this.products = await response.json();

                this.products.forEach(product => {
                    if (this.quantities[product.id] === undefined) {
                        this.quantities[product.id] = 0;
                    }
                });
            } catch (e) {
                this.error = true;
                this.message = e.message;
            } finally {
                this.loadingProducts = false;
            }
        },

        async loadSession() {
            this.activeSessionId = null;
            this.customerName = '';

            if (!this.selected || this.walkInMode) return;

            try {
                const response = await fetch(
                    `/console-active-session/${this.selected.id}`,
                    { headers: { Accept: 'application/json' } }
                );

                if (!response.ok) throw new Error('Gagal memuat sesi konsol.');

                const data = await response.json();

                if (data.session) {
                    this.activeSessionId = data.session.id;
                    this.customerName = data.session.customer_name || '';
                }
            } catch (e) {
                this.error = true;
                this.message = e.message;
            }
        },

        formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(Number(value) || 0);
        },

        get orderItems() {
            return this.products
                .filter(p => Number(this.quantities[p.id]) > 0)
                .map(p => ({
                    product_id: p.id,
                    qty: Number(this.quantities[p.id])
                }));
        },

        get orderTotal() {
            return this.products.reduce((total, product) => {
                return total +
                    (Number(this.quantities[product.id]) || 0) *
                    Number(product.sell_price);
            }, 0);
        },

        get changeAmount() {
            if (this.paymentMethod !== 'cash') return 0;

            return Math.max(
                0,
                Number(this.paidAmount || 0) - Number(this.lastOrderTotal || 0)
            );
        },

        setQuickCash(amount) {
            this.paymentMethod = 'cash';
            this.paidAmount = amount;
        },

        async submitOrder() {
            this.message = '';
            this.error = false;

            if (this.walkInMode && this.lastOrderId) {
                this.error = true;
                this.message = 'Selesaikan pembayaran pesanan Walk-in sebelumnya dahulu.';
                return;
            }

            if (!this.orderItems.length) {
                this.error = true;
                this.message = 'Pilih minimal satu produk.';
                return;
            }

            for (const item of this.orderItems) {
                const product = this.products.find(
                    p => Number(p.id) === Number(item.product_id)
                );

                if (!product || item.qty > Number(product.stock)) {
                    this.error = true;
                    this.message =
                        `Stok ${product?.name || 'produk'} tidak mencukupi.`;
                    return;
                }
            }

            this.submitting = true;

            try {
                const response = await fetch(@js(route('fnb-orders.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token())
                    },
                    body: JSON.stringify({
                        rental_session_id: this.walkInMode
                            ? null
                            : this.activeSessionId,
                        customer_name: this.customerName || null,
                        items: this.orderItems
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    const details = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : data.message;

                    throw new Error(details || 'Pesanan gagal disimpan.');
                }

                const order = data.data || data.order || data;

                if (this.walkInMode) {
                    if (!order.id) {
                        throw new Error(
                            'Pesanan tersimpan, tetapi ID pesanan tidak ditemukan pada respons server.'
                        );
                    }

                    this.lastOrderId = order.id;
                    this.lastOrderTotal = Number(
                        order.total_amount ?? this.orderTotal
                    );
                    this.paymentMethod = 'cash';
                    this.paidAmount = this.lastOrderTotal;
                    this.message = 'Pesanan Walk-in berhasil dibuat. Lanjutkan pembayaran.';
                } else {
                    this.message = data.message || 'Pesanan F&B berhasil ditambahkan ke sesi rental.';
                }

                this.quantities = {};
                this.products.forEach(p => {
                    this.quantities[p.id] = 0;
                });

                await this.loadProducts();
                await this.loadSession();
            } catch (e) {
                this.error = true;
                this.message = e.message;
            } finally {
                this.submitting = false;
            }
        },

        async checkoutWalkIn() {
            this.message = '';
            this.error = false;

            if (!this.lastOrderId) {
                this.error = true;
                this.message = 'Belum ada pesanan Walk-in yang perlu dibayar.';
                return;
            }

            const paid = Number(this.paidAmount);

            if (!Number.isFinite(paid) || paid < 0) {
                this.error = true;
                this.message = 'Masukkan nominal pembayaran yang valid.';
                return;
            }

            if (this.paymentMethod === 'cash' && paid < this.lastOrderTotal) {
                this.error = true;
                this.message = 'Uang yang dibayarkan masih kurang.';
                return;
            }

            if (this.paymentMethod === 'qris' && paid !== Number(this.lastOrderTotal)) {
                this.error = true;
                this.message = 'Nominal QRIS harus sama dengan total tagihan.';
                return;
            }

            this.paying = true;

            try {
                const response = await fetch(
                    `/fnb-orders/${this.lastOrderId}/checkout`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token())
                        },
                        body: JSON.stringify({
                            payment_method: this.paymentMethod,
                            paid_amount: paid
                        })
                    }
                );

                const data = await response.json();

                if (!response.ok) {
                    const details = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : data.message;

                    throw new Error(details || 'Pembayaran gagal diproses.');
                }

                const paidOrderTotal = this.lastOrderTotal;
                const paidChange = Number(data.data?.change_amount ?? this.changeAmount);

                this.message =
                    `Pembayaran berhasil. Total: ${this.formatRupiah(paidOrderTotal)}. Kembalian: ${this.formatRupiah(paidChange)}.`;

                this.lastOrderId = null;
                this.lastOrderTotal = 0;
                this.paidAmount = 0;
                this.paymentMethod = 'cash';

                await this.loadProducts();
            } catch (e) {
                this.error = true;
                this.message = e.message;
            } finally {
                this.paying = false;
            }
        }
    }"
    x-init="loadProducts(); $watch('selected', () => loadSession())"
>
    <h2 class="text-lg font-bold">Detail Tagihan</h2>

    <div class="mt-3 flex gap-2">
        <button
            type="button"
            @click="
                if (lastOrderId) {
                    error = true;
                    message = 'Selesaikan pembayaran Walk-in sebelumnya dahulu.';
                } else {
                    walkInMode = true;
                    activeSessionId = null;
                    customerName = '';
                    message = '';
                    error = false;
                }
            "
            :class="walkInMode ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700'"
            class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold"
        >
            Walk-in F&B
        </button>

        <button
            type="button"
            @click="
                if (lastOrderId) {
                    error = true;
                    message = 'Selesaikan pembayaran Walk-in sebelumnya dahulu.';
                } else {
                    walkInMode = false;
                    loadSession();
                    message = '';
                    error = false;
                }
            "
            :class="!walkInMode ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700'"
            class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold"
        >
            Mode Konsol
        </button>
    </div>

    <template x-if="!selected && !walkInMode">
        <p class="mt-4 text-sm text-slate-500">
            Klik salah satu kartu konsol untuk melihat transaksi.
        </p>
    </template>

    <template x-if="selected || walkInMode">
        <div class="mt-4 space-y-4">
            <div class="rounded-lg bg-indigo-50 p-3">
                <div
                    class="text-xs text-indigo-500"
                    x-text="walkInMode ? 'Pesanan Walk-in' : 'Konsol dipilih'"
                ></div>

                <div
                    class="font-semibold"
                    x-text="walkInMode ? 'Tanpa sesi rental' : selected.name"
                ></div>

                <div
                    class="mt-1 text-xs text-slate-600"
                    x-text="walkInMode ? 'Walk-in / tanpa sesi rental' : (activeSessionId ? 'Sesi rental aktif' : 'Belum ada sesi rental aktif')"
                ></div>
            </div>

            <section>
                <h3 class="font-semibold">Pesanan F&amp;B</h3>

                <label class="mb-1 mt-3 block text-sm font-medium">
                    Nama pelanggan
                </label>

                <input
                    type="text"
                    x-model="customerName"
                    maxlength="100"
                    placeholder="Nama pelanggan"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >

                <p
                    x-show="loadingProducts"
                    class="mt-3 text-sm text-slate-500"
                >
                    Memuat produk...
                </p>

                <div class="mt-3 space-y-3">
                    <template x-for="product in products" :key="product.id">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div
                                        class="text-sm font-semibold"
                                        x-text="product.name"
                                    ></div>

                                    <div
                                        class="text-sm text-slate-600"
                                        x-text="formatRupiah(product.sell_price)"
                                    ></div>

                                    <div
                                        class="text-xs text-slate-500"
                                        x-text="'Stok: ' + product.stock"
                                    ></div>
                                </div>

                                <input
                                    type="number"
                                    min="0"
                                    :max="product.stock"
                                    x-model.number="quantities[product.id]"
                                    :disabled="Number(product.stock) < 1 || (walkInMode && !!lastOrderId)"
                                    class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm"
                                    aria-label="Jumlah produk"
                                >
                            </div>
                        </div>
                    </template>
                </div>

                <p
                    x-show="!loadingProducts && products.length === 0"
                    class="mt-3 text-sm text-slate-500"
                >
                    Belum ada produk aktif.
                </p>

                <div class="mt-4 flex justify-between border-t pt-3 text-sm font-bold">
                    <span>Total pesanan</span>
                    <span x-text="formatRupiah(orderTotal)"></span>
                </div>

                <button
                    type="button"
                    @click="submitOrder()"
                    :disabled="submitting || loadingProducts || orderItems.length === 0 || (walkInMode && !!lastOrderId)"
                    class="mt-3 w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    <span x-text="submitting ? 'Menyimpan...' : (walkInMode && lastOrderId ? 'Selesaikan pembayaran dahulu' : 'Tambah Pesanan F&B')"></span>
                </button>
            </section>

            <section class="border-t pt-3">
                <h3 class="font-semibold">Pembayaran</h3>

                <template x-if="walkInMode && lastOrderId">
                    <div class="mt-3 space-y-3">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-sm text-slate-500">ID pesanan</div>
                            <div class="font-semibold" x-text="'#' + lastOrderId"></div>

                            <div class="mt-2 text-sm text-slate-500">Total tagihan</div>
                            <div
                                class="text-lg font-bold text-indigo-700"
                                x-text="formatRupiah(lastOrderTotal)"
                            ></div>
                        </div>

                        <label class="block text-sm font-medium">
                            Metode pembayaran
                        </label>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="
                                    paymentMethod = 'cash';
                                    paidAmount = Math.max(Number(paidAmount) || 0, lastOrderTotal);
                                "
                                :class="paymentMethod === 'cash' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-700'"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold"
                            >
                                Tunai
                            </button>

                            <button
                                type="button"
                                @click="paymentMethod = 'qris'; paidAmount = lastOrderTotal"
                                :class="paymentMethod === 'qris' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 bg-white text-slate-700'"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold"
                            >
                                QRIS
                            </button>
                        </div>

                        <label class="block text-sm font-medium">
                            Nominal dibayar (Rp)
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="1"
                            x-model.number="paidAmount"
                            :readonly="paymentMethod === 'qris'"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm read-only:bg-slate-100"
                        >

                        <div x-show="paymentMethod === 'cash'" class="space-y-2">
                            <p class="text-xs font-medium text-slate-500">
                                Uang cepat
                            </p>

                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="setQuickCash(lastOrderTotal)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    Uang pas
                                </button>

                                <button
                                    type="button"
                                    @click="setQuickCash(Math.ceil(lastOrderTotal / 5000) * 5000 + 5000)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    + Rp5.000
                                </button>

                                <button
                                    type="button"
                                    @click="setQuickCash(Math.ceil(lastOrderTotal / 10000) * 10000 + 10000)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    + Rp10.000
                                </button>

                                <button
                                    type="button"
                                    @click="setQuickCash(Math.ceil(lastOrderTotal / 20000) * 20000 + 20000)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    + Rp20.000
                                </button>

                                <button
                                    type="button"
                                    @click="setQuickCash(Math.ceil(lastOrderTotal / 50000) * 50000 + 50000)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    + Rp50.000
                                </button>

                                <button
                                    type="button"
                                    @click="setQuickCash(Math.ceil(lastOrderTotal / 100000) * 100000 + 100000)"
                                    class="rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold"
                                >
                                    + Rp100.000
                                </button>
                            </div>

                            <div class="flex justify-between rounded-lg bg-emerald-50 p-3 text-sm">
                                <span class="font-medium text-emerald-800">Kembalian</span>
                                <span
                                    class="font-bold text-emerald-800"
                                    x-text="formatRupiah(changeAmount)"
                                ></span>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="checkoutWalkIn()"
                            :disabled="paying || (paymentMethod === 'cash' && Number(paidAmount) < lastOrderTotal)"
                            class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            <span x-text="paying ? 'Memproses pembayaran...' : 'Bayar Sekarang'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="!walkInMode">
                    <p class="mt-2 text-sm text-slate-500">
                        Pembayaran rental menggunakan checkout sesi yang sudah
                        dihentikan. Total tagihan dan status Stop perlu tersedia
                        dari alur sesi rental sebelum tombol checkout rental
                        dapat diaktifkan di panel ini.
                    </p>
                </template>

                <template x-if="walkInMode && !lastOrderId">
                    <p class="mt-2 text-sm text-slate-500">
                        Tambahkan pesanan Walk-in terlebih dahulu untuk
                        melanjutkan pembayaran.
                    </p>
                </template>
            </section>

            <p
                x-show="message"
                x-text="message"
                :class="error ? 'text-red-600' : 'text-green-600'"
                class="text-sm"
                role="status"
            ></p>
        </div>
    </template>
</aside>
