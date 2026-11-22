# SAPA WA Gateway

Layanan Node.js kecil yang menghubungkan SAPA ke WhatsApp memakai
[Baileys](https://github.com/WhiskeySockets/Baileys) (WhatsApp Web, bukan WhatsApp Business API).
Laravel memanggilnya lewat HTTP lokal, dan gateway meneruskan pesan masuk serta
perubahan status sesi ke webhook Laravel.

```
Ponsel pelanggan <-> WhatsApp <-> [wa-gateway (Baileys)] <-- HTTP lokal + token --> [Laravel]
```

> **Gateway ini tidak boleh terbuka ke internet.** Ia hanya mendengarkan `127.0.0.1`
> dan semua permintaan wajib memakai token rahasia.

## Menjalankan

Butuh Node.js **>= 20.19**.

```bash
cd wa-gateway
npm install
cp .env.example .env     # lalu isi GATEWAY_TOKEN
npm start
```

Di sisi Laravel (`.env` di root proyek):

```
WHATSAPP_DRIVER=baileys
WHATSAPP_WEBHOOK_TOKEN=<sama persis dengan GATEWAY_TOKEN>
WHATSAPP_GATEWAY_URL=http://127.0.0.1:3001
```

Buat token acak: `node -e "console.log(require('crypto').randomBytes(24).toString('hex'))"`.
Laravel dan gateway harus berjalan bersamaan (serta `php artisan queue:work` agar balasan terkirim).

## Pengaturan (`.env`)

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `GATEWAY_TOKEN` | wajib | Token bersama, minimal 12 karakter, sama dengan `WHATSAPP_WEBHOOK_TOKEN` Laravel |
| `HOST` / `PORT` | `127.0.0.1` / `3001` | Biarkan localhost |
| `LARAVEL_WEBHOOK_URL` | `http://127.0.0.1:8000/api/webhook/whatsapp` | Alamat webhook Laravel |
| `AUTH_DIR` | `./auth` | Tempat kredensial sesi (lihat peringatan di bawah) |
| `QR_TIMEOUT_MS` | `20000` | Lama menunggu QR pertama dari WhatsApp |
| `LOG_LEVEL` | `info` | |

## API (dipakai Laravel)

Semua rute memakai header `X-Gateway-Token`.

| Rute | Fungsi |
|---|---|
| `GET /health` | Cek hidup + jumlah sesi |
| `POST /sesi` `{nomor}` | Mulai sesi. Mengembalikan `{status:"qr", qr}` atau `{status:"tersambung"}` |
| `GET /sesi/:nomor` | Status sesi |
| `DELETE /sesi/:nomor` | Logout, hapus kredensial (tidak memanggil balik webhook) |
| `POST /sesi/:nomor/pesan` `{telepon, isi}` | Kirim pesan teks. Mengembalikan `{id_pesan}` |

Kode galat: `401` token salah, `409` nomor tidak tersambung, `422` data tidak valid atau
nomor tujuan tidak terdaftar di WhatsApp, `502` gagal di sisi WhatsApp, `504` QR tidak datang.

Yang diteruskan ke Laravel (`/api/webhook/whatsapp/pesan` dan `/sesi`): pesan pelanggan masuk,
kode QR yang diputar WhatsApp (sekitar tiap 20 detik), serta status `tersambung`, `terputus`, dan `gagal`.
Pengiriman webhook dicoba ulang bila Laravel sedang tidak bisa dijangkau.

## Cara kerja penautan

1. Administrator menekan **Tautkan** di halaman Nomor WhatsApp. Laravel memanggil `POST /sesi`.
2. Gateway membuka koneksi WhatsApp Web dan mengembalikan QR pertama.
3. QR pertama berlaku **60 detik** (sama dengan masa berlaku di SAPA); setelah itu WhatsApp memutar QR tiap ~20 detik
   dan gateway meneruskannya ke Laravel, yang mengabaikannya bila masa berlaku SAPA sudah habis.
   Menekan **Buat ulang kode** memulai sesi baru sehingga QR kembali berumur penuh.
4. Setelah QR dipindai, gateway memeriksa **nomor ponsel yang dipindai sama dengan nomor yang
   didaftarkan** di SAPA. Bila berbeda, sesi ditolak, di-logout, dan dicatat di Pusat Risiko
   (supaya kartu "Admisi" tidak terhubung ke ponsel orang lain).
5. Bila cocok, gateway melapor `tersambung` dan pesan mulai mengalir.

Sesi yang sudah tertaut dipulihkan otomatis saat gateway dinyalakan ulang, tanpa pindai QR lagi.

## ⚠️ Folder `auth/`

Isinya kredensial sesi WhatsApp. **Siapa pun yang memegang folder ini bisa menjadi nomor tersebut.**
- Sudah di-`.gitignore`; jangan pernah di-commit, dikirim lewat chat, atau dibagikan.
- Harus **permanen**: jangan dihapus saat deploy, atau semua nomor harus dipindai ulang.
- Batasi izinnya (`chmod 700 auth`) dan ikutkan ke backup yang aman.

## Versi Baileys

Dipasang tepat di **`7.0.0-rc14`** (tanpa `^`). Ini *release candidate*; jalur stabil lama (`6.7.24`)
tidak memakai LID. Seluruh kode Baileys ada di satu berkas, `src/baileys-socket.js`, sehingga
mengganti atau memperbarui versi cukup menyentuh berkas itu. Jangan menaikkan versi tanpa mencoba
ulang penautan, pesan masuk, dan kirim dengan nomor uji.

Catatan v7: pengirim bisa berupa `@lid` (identitas anonim), bukan nomor telepon. Gateway memakai
`remoteJidAlt` dan pemetaan LID bawaan Baileys; pesan yang nomornya tidak bisa dipastikan
**diabaikan dan dicatat di log** (tanpa isi pesan), bukan ditebak.

## Batasan saat ini

- Hanya pesan teks yang dikirim. Pesan masuk non-teks diteruskan sebagai label (`[Gambar]`, `[Dokumen] nama.pdf`, dsb.).
- Pesan grup, status, siaran, dan kanal diabaikan.
- Belum ada pembatasan laju/jeda kirim (mesin anti-ban Sprint 3). Ini jalur **tidak resmi**: nomor bisa dibatasi
  atau diblokir WhatsApp. Pakai nomor uji, bukan nomor mitra, saat mencoba.
- Isi pesan tidak pernah ditulis ke log.

## Menjalankan terus-menerus (VPS)

```bash
npm install -g pm2
pm2 start npm --name sapa-wa-gateway -- start
pm2 save && pm2 startup
```

## Pengujian

```bash
npm test
```

Tes memakai pelari bawaan Node dan **tidak membutuhkan Baileys terpasang** (koneksi dipalsukan).
Yang diuji: validasi nomor/JID (termasuk LID), ekstraksi pesan, klien webhook beserta percobaan ulang,
manajer sesi (QR, nomor tidak cocok, sambung ulang, logout, pemulihan), dan server HTTP (token, validasi).
Perilaku Baileys asli hanya bisa dibuktikan dengan memindai QR memakai nomor WhatsApp uji.

## Pemecahan masalah

| Gejala | Kemungkinan penyebab |
|---|---|
| Laravel: "Gateway WhatsApp tidak dapat dihubungi" | Gateway belum jalan, atau `WHATSAPP_GATEWAY_URL` salah |
| Webhook dari gateway 401 | `GATEWAY_TOKEN` dan `WHATSAPP_WEBHOOK_TOKEN` tidak sama |
| Pesan masuk tidak muncul di Kotak Masuk | Cek log gateway: nomor tidak tersambung di Laravel (409), atau pengirim `@lid` yang tidak bisa dipetakan |
| QR tidak muncul | WhatsApp tidak merespons dalam `QR_TIMEOUT_MS`; cek koneksi internet server |
| Minta pindai QR lagi setiap restart | Folder `AUTH_DIR` tidak permanen |
