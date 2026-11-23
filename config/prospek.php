<?php

/*
| Skor prioritas prospek (BR06). Setiap komponen bernilai 0..1 lalu dikalikan bobotnya.
| Skor = jumlah (bobot x nilai) dibagi jumlah bobot, dikali 100, sehingga tetap 0..100
| walau bobot diubah. Setelah mengubah bobot jalankan: php artisan prospek:hitung-ulang
|
| Bobot awal usulan tim; dapat disesuaikan dengan hasil evaluasi konversi.
*/
return [
    'bobot' => [
        'kelengkapan' => 25,   // data calon siswa terisi (nama, jenjang, kelas, mapel, sumber)
        'urgensi' => 25,       // pelanggan menyebut ujian, tryout, atau waktu yang mendesak
        'minat' => 20,         // menanyakan pendaftaran, biaya, atau jadwal
        'responsivitas' => 15, // jumlah pesan pelanggan dan balasan atas pesan kita
        'riwayat' => 15,       // tahap prospek dan aktivitas tindak lanjut
    ],

    // Batas warna skor di papan prospek.
    'ambang_tinggi' => 70,
    'ambang_sedang' => 40,
];
