<?php

/*
 * Identitas toko yang dicetak di header/footer struk (Bab 6.2 dokumen).
 * Ubah lewat file .env, tidak perlu mengedit kode.
 */
return [
    'name' => env('RENTAL_NAME', 'Rental PlayStation'),
    'address' => env('RENTAL_ADDRESS', 'Alamat belum diatur'),
    'phone' => env('RENTAL_PHONE', ''),
    'receipt_footer' => env('RENTAL_RECEIPT_FOOTER', 'Terima kasih, selamat bermain!'),

    // Lebar kertas thermal default: 58 atau 80 (mm). Bisa diganti per cetak lewat ?width=80
    'receipt_width' => (int) env('RENTAL_RECEIPT_WIDTH', 58),
];
