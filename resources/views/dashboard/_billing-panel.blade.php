
<aside
    class="w-[30%] overflow-y-auto border-l border-slate-200 bg-white p-4"
    x-data="{
        products: [],
        quantities: {},
        customerName: '',
        activeSessionId: null,
        loadingProducts: false,
        submitting: false,
        message: '',
        error: false,

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

            if (!this.selected) return;

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

        async submitOrder() {
            this.message = '';
            this.error = false;

            if (!this.orderItems.length) {
                this.error = true;
                this.message = 'Pilih minimal satu produk.';
                return;
            }

            for (const item of this.orderItems) {
                const product = this.products.find(
                    p => p.id === item.product_id
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
                        rental_session_id: this.activeSessionId,
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

                this.message = data.message || 'Pesanan berhasil disimpan.';

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
        }
    }"
    x-init="loadProducts(); $watch('selected', () => loadSession())"
>
    <h2 class="text-lg font-bold">Detail Tagihan</h2>

    <template x-if="!selected">
        <p class="mt-4 text-sm text-slate-500">
            Klik salah satu kartu konsol untuk melihat transaksi.
        </p>
    </template>

    <template x-if="selected">
        <div class="mt-4 space-y-4">
            <div class="rounded-lg bg-indigo-50 p-3">
                <div class="text-xs text-indigo-500">Konsol dipilih</div>
                <div class="font-semibold" x-text="selected.name"></div>
                <div class="mt-1 text-xs text-slate-600"
                     x-text="activeSessionId ? 'Sesi rental aktif' : 'Walk-in / tanpa sesi rental'"></div>
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

                <p x-show="loadingProducts" class="mt-3 text-sm text-slate-500">
                    Memuat produk...
                </p>

                <div class="mt-3 space-y-3">
                    <template x-for="product in products" :key="product.id">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-semibold"
                                         x-text="product.name"></div>
                                    <div class="text-sm text-slate-600"
                                         x-text="formatRupiah(product.sell_price)"></div>
                                    <div class="text-xs text-slate-500"
                                         x-text="'Stok: ' + product.stock"></div>
                                </div>

                                <input
                                    type="number"
                                    min="0"
                                    :max="product.stock"
                                    x-model.number="quantities[product.id]"
                                    :disabled="Number(product.stock) < 1"
                                    class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-center text-sm"
                                    aria-label="Jumlah produk"
                                >
                            </div>
                        </div>
                    </template>
                </div>

                <p x-show="!loadingProducts && products.length === 0"
                   class="mt-3 text-sm text-slate-500">
                    Belum ada produk aktif.
                </p>

                <div class="mt-4 flex justify-between border-t pt-3 text-sm font-bold">
                    <span>Total pesanan</span>
                    <span x-text="formatRupiah(orderTotal)"></span>
                </div>

                <button
                    type="button"
                    @click="submitOrder()"
                    :disabled="submitting || loadingProducts || orderItems.length === 0"
                    class="mt-3 w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    <span x-text="submitting ? 'Menyimpan...' : 'Tambah Pesanan F&B'"></span>
                </button>

                <p
                    x-show="message"
                    x-text="message"
                    :class="error ? 'text-red-600' : 'text-green-600'"
                    class="mt-2 text-sm"
                    role="status"
                ></p>
            </section>

            <section class="border-t pt-3">
                <h3 class="font-semibold">Pembayaran</h3>
                <p class="mt-2 text-sm text-slate-500">
                    Pembayaran akan disambungkan setelah alur Stop dan endpoint
                    tagihan terintegrasi.
                </p>
            </section>
        </div>
    </template>
</aside>
