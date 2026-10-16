<aside 
    x-data="{
        billingData: null,
        loading: false,
        paymentMethod: 'cash',
        amountPaid: 0,

        fetchBilling() {
            if (!selected || !selected.id) {
                this.billingData = null;
                return;
            }
            this.loading = true;
            fetch('/rental-session/' + selected.id + '/billing')
                .then(res => res.json())
                .then(data => {
                    this.billingData = data;
                    this.loading = false;
                })
                .catch(() => { this.loading = false; });
        },

        get grandTotal() {
            return this.billingData?.billing?.grand_total || 0;
        },
        get changeAmount() {
            return (parseFloat(this.amountPaid) || 0) - this.grandTotal;
        },

        setExactAmount() {
            this.amountPaid = this.grandTotal;
        },
        addAmount(val) {
            this.amountPaid = (parseFloat(this.amountPaid) || 0) + val;
        },
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }
    }"
    x-init="
        $watch('selected', () => {
            amountPaid = 0;
            fetchBilling();
        })
    "
    class="w-[30%] overflow-y-auto border-l border-slate-200 bg-white p-4 flex flex-col justify-between"
>
    <div>
        <h2 class="text-lg font-bold text-slate-800 border-b pb-2">Detail Tagihan</h2>

        <template x-if="!selected">
            <div class="mt-8 text-center text-slate-400">
                <p class="text-sm">Klik salah satu kartu meja untuk melihat tagihannya.</p>
            </div>
        </template>

        <template x-if="selected && loading">
            <div class="mt-8 text-center text-slate-400">
                <p class="text-sm">Memuat data tagihan...</p>
            </div>
        </template>

        <template x-if="selected && !loading && billingData">
            <div class="mt-4 space-y-4">
                <div class="rounded-lg bg-indigo-50 p-3 border border-indigo-100 flex justify-between items-center">
                    <div>
                        <div class="text-xs font-semibold text-indigo-500 uppercase tracking-wider">Meja Dipilih</div>
                        <div class="font-bold text-indigo-900 text-base" x-text="selected.name"></div>
                    </div>
                    <span 
                        class="text-xs px-2 py-1 rounded font-medium"
                        :class="billingData.session.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                        x-text="billingData.session.payment_status === 'paid' ? 'Lunas' : 'Belum Bayar'"
                    ></span>
                </div>

                <div class="space-y-3 text-sm border-b pb-4">
                    <div class="flex justify-between text-slate-600">
                        <span>Biaya Sewa Meja</span>
                        <span class="font-semibold text-slate-800" x-text="formatRupiah(billingData.billing.rental_total)"></span>
                    </div>

                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Daftar Pesanan F&B</div>
                        <template x-if="billingData.billing.fnb_items && billingData.billing.fnb_items.length > 0">
                            <ul class="space-y-1.5 pl-1">
                                <template x-for="item in billingData.billing.fnb_items" :key="item.id">
                                    <li class="flex justify-between text-xs text-slate-600">
                                        <span>
                                            <span x-text="item.qty + 'x'"></span>
                                            <span x-text="item.name"></span>
                                        </span>
                                        <span class="font-medium" x-text="formatRupiah(item.subtotal)"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <template x-if="!billingData.billing.fnb_items || billingData.billing.fnb_items.length === 0">
                            <p class="text-xs text-slate-400 italic">Belum ada pesanan F&B</p>
                        </template>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>Subtotal Rental</span>
                        <span x-text="formatRupiah(billingData.billing.rental_total)"></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>Subtotal F&B</span>
                        <span x-text="formatRupiah(billingData.billing.fnb_total)"></span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t">
                        <span>Total Tagihan</span>
                        <span class="text-indigo-600" x-text="formatRupiah(billingData.billing.grand_total)"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <template x-if="selected && billingData && billingData.session.payment_status !== 'paid'">
        <form 
            :action="'/rental-session/' + selected.id + '/checkout'" 
            method="POST" 
            class="mt-6 pt-4 border-t border-slate-200 space-y-3"
        >
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Metode Pembayaran</label>
                <select 
                    name="payment_method" 
                    x-model="paymentMethod"
                    class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
                    <option value="cash">Tunai (Cash)</option>
                    <option value="qris">QRIS</option>
                </select>
            </div>

            <div x-show="paymentMethod === 'cash'">
                <label class="block text-xs font-bold text-slate-600 mb-1">Jumlah Bayar</label>
                <input 
                    type="number" 
                    name="amount_paid" 
                    x-model.number="amountPaid"
                    placeholder="0"
                    class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >

                <div class="grid grid-cols-4 gap-1.5 mt-2">
                    <button type="button" @click="setExactAmount()" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-[11px] font-semibold py-1.5 rounded transition">Uang Pas</button>
                    <button type="button" @click="addAmount(20000)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-[11px] font-semibold py-1.5 rounded transition">+20rb</button>
                    <button type="button" @click="addAmount(50000)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-[11px] font-semibold py-1.5 rounded transition">+50rb</button>
                    <button type="button" @click="addAmount(100000)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-[11px] font-semibold py-1.5 rounded transition">+100rb</button>
                </div>

                <div class="flex justify-between items-center text-xs font-semibold mt-3 p-2 bg-slate-50 rounded border border-slate-200">
                    <span class="text-slate-500">Kembalian:</span>
                    <span 
                        :class="changeAmount >= 0 ? 'text-emerald-600 font-bold' : 'text-rose-500 font-bold'"
                        x-text="changeAmount >= 0 ? formatRupiah(changeAmount) : 'Kurang ' + formatRupiah(Math.abs(changeAmount))"
                    ></span>
                </div>
            </div>

            <button 
                type="submit" 
                :disabled="paymentMethod === 'cash' && changeAmount < 0"
                class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold py-2.5 rounded-lg text-sm transition shadow-sm mt-2"
            >
                Stop & Bayar Selesai
            </button>
        </form>
    </template>
</aside>