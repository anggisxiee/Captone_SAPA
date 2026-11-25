# Skema basis data vs kamus data SRS

Acuan: **Tabel 3.19 Kamus Data** pada SRS. Kesesuaian dijaga oleh dua tes:

- `tests/Feature/SkemaSrsTest.php`: memuat seluruh kamus data SRS dan memastikan setiap tabel dan kolom ada,
  ditambah perilaku yang bergantung padanya (penangan, belum_dibaca, waktu balas pertama, log audit, BR03).
- `tests/Feature/MigrasiSkemaSrsTest.php`: menjalankan jalur peningkatan dari skema lama dengan data nyata
  dan memastikan tidak ada data yang hilang.

Jalankan: `php artisan test --filter=Srs`.

## Status per tabel

| Tabel SRS | Status | Catatan |
|---|---|---|
| `users` | Sesuai | |
| `user_whatsapp_access` | Sesuai | `hak_akses` bernilai `Tidak ada`, `Lihat saja`, `Balas chat`, `Kelola` |
| `nomor_whatsapp` | Sesuai + tambahan | `nama` diganti `label`; ditambah `mode_ai` (bawaan `draf`) dan `skor_kesehatan` (bawaan 100). `nama` tetap bekerja sebagai alias di model |
| `pengaturan_ai` | Sesuai + tambahan | Ditambah `nomor_whatsapp_id` (kosong = berlaku umum), `batas_per_menit`, `batas_per_jam` |
| `kontak` | Sesuai | `nama` boleh kosong; ditambah `kota`, `sumber`, `label`. Tampilan memakai nomor telepon bila nama kosong |
| `percakapan` | Sesuai + tambahan | `belum_dibalas` (boolean) diganti `belum_dibaca` (jumlah); ditambah `agen_id`, `penangan`, `waktu_balas_pertama` |
| `pesan_whatsapp` | Sesuai + tambahan | `pengirim` diganti `arah` + `jenis_pengirim`; ditambah `status` dan `meta_ai`. `kontak_nama`, `kontak_telepon`, `nomor_whatsapp_id` dibuang (dapat ditelusuri lewat percakapan) |
| `antrean_pesan` | Sesuai + tambahan | `penerima` kini berisi **nomor tujuan** (sebelumnya nama); ditambah `pesan_whatsapp_id` |
| `riwayat_antrean` | Sesuai | |
| `insiden_risiko` | Sesuai + tambahan | `tingkat_risiko` juga mengenal `Kritis` |
| `log_audit` | Sesuai + tambahan | `objek` menjadi `entitas` (nama tabel); ditambah `entitas_id`; `keterangan` masuk ke `detail` (JSON) |
| `prospek` | **Sebagian (sengaja)** | Ditambah `kontak_id`, `rincian_skor`, `tindak_lanjut_berikut`. Kolom lama `nama`, `nomor_telepon`, `jenjang`, `sumber`, `catatan` masih ada karena halaman Prospek membacanya; dibuang saat CRM ditulis ulang di Sprint 2 |
| `aktivitas_prospek` | Sesuai (tabel baru) | Belum dipakai UI (Sprint 2). `user_id` boleh kosong agar riwayat tetap ada bila pengguna dihapus |
| `artikel_pengetahuan` | Sesuai (tabel baru) | Belum dipakai UI. Indeks pencarian FTS5 dibuat di Sprint 2 |
| `metrik_harian` | Sesuai (tabel baru) | Diisi pada Sprint 3 (dasbor). Unik per nomor dan tanggal |

## Kolom dan nilai di luar SRS (usul dimasukkan ke SRS)

Kode memerlukan ini dan SRS belum menyebutnya:

- `nomor_whatsapp`: `status_sesi` (`Belum tertaut`, `Menunggu QR`, `Tersambung`, `Terputus`, `Gagal tertaut`),
  `qr_token`, `qr_kedaluwarsa_pada`, `terakhir_aktif_pada`. `status` tetap `Aktif`/`Nonaktif`.
- `pengaturan_ai`: `base_url` (penyedia kompatibel OpenAI), `eskalasi_pelanggan_marah`, `variasi_kalimat`.
- `pesan_whatsapp`: `wa_message_id` (id dari gateway; unik per percakapan sehingga pesan masuk tidak tersimpan ganda).
- `percakapan`: `pesan_terakhir_pada`.
- `antrean_pesan`: `pengirim` (label nomor), `maks_percobaan`. `keputusan` bernilai `Kirim`, `Ditunda`, `Ditahan`,
  `Terkirim`, `Gagal` (SRS hanya menyebut tiga yang pertama; dua terakhir adalah hasil pengiriman).
- `log_audit`: `alamat_ip`.

Nilai yang dipakai kode untuk kolom "status data":

| Kolom | Nilai |
|---|---|
| `percakapan.status` | `Baru`, `Ditangani AI`, `Ditangani Agen`, `Selesai` |
| `percakapan.penangan` | `AI`, `agen` (selalu mengikuti `status`; `Selesai` tidak mengubahnya) |
| `pesan_whatsapp.arah` | `masuk`, `keluar` |
| `pesan_whatsapp.jenis_pengirim` | `pelanggan`, `ai`, `agen` |
| `pesan_whatsapp.status` | `diterima` (masuk); `menunggu`, `terkirim`, `gagal` (keluar) |

## Perubahan perilaku yang menyertai

- **BR03**: Supervisor kini **membaca semua nomor** di Kotak Masuk. Sebelumnya dibatasi per nomor seperti Agen/CS.
  Membalas tetap memerlukan hak `Balas chat` atau `Kelola` pada nomor itu; Administrator memiliki semua.

## Menerapkan pada basis data yang sudah berisi data

```bash
cp database/database.sqlite database/database.sqlite.bak   # cadangan dulu
php artisan migrate
php artisan queue:restart                                  # worker memuat kode baru
```

Migrasi `2026_11_23_*` memindahkan data lama (nomor, percakapan, pesan, antrean, log audit, prospek) ke bentuk baru.
Hasil hitungan lama dipertahankan artinya, misalnya percakapan berstatus Selesai tetap `belum_dibaca = 0`.

`migrate:rollback` hanya aman untuk migrasi yang memiliki `down()`. Migrasi `pesan_whatsapp` membangun ulang tabel
dan **tidak dapat dibalik otomatis**; pulihkan dari cadangan bila perlu.
