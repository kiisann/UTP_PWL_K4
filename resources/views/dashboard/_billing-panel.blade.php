{{-- BAGIAN NIKO: rincian tagihan, daftar F&B, metode bayar, tombol uang cepat.
     Variabel Alpine "selected" ({ id, name }) datang dari index.blade.php --}}
<aside class="w-[30%] overflow-y-auto border-l border-slate-200 bg-white p-4">
    <h2 class="text-lg font-bold">Detail Tagihan</h2>

    <template x-if="!selected">
        <p class="mt-4 text-sm text-slate-500">Klik salah satu kartu meja untuk melihat tagihannya.</p>
    </template>

    <template x-if="selected">
        <div class="mt-4 space-y-3">
            <div class="rounded-lg bg-indigo-50 p-3">
                <div class="text-xs text-indigo-500">Meja dipilih</div>
                <div class="font-semibold" x-text="selected.name"></div>
            </div>
            <p class="text-sm text-slate-500">
                Rincian sewa, pesanan F&amp;B, metode bayar (Tunai / QRIS), dan tombol uang cepat
                (Uang Pas, 20rb, 50rb, 100rb) dibuat di sini.
            </p>
        </div>
    </template>
</aside>
