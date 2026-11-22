# SAPA — Sistem Asisten Pelanggan dan Akuisisi

Aplikasi web untuk Bimbingan Belajar Asentra: beberapa nomor WhatsApp dipusatkan
dalam satu kotak masuk, dilengkapi (pada sprint berikutnya) jawaban otomatis
berbasis AI, CRM calon siswa, dan dasbor pemantauan.
Kebutuhan lengkap ada di dokumen SRS dan rencana sprint tim.

**Teknologi:** Laravel 12 (PHP 8.2+), Blade + Alpine.js + Tailwind CSS 4 (Vite), SQLite, antrean berbasis database.

## Menjalankan di komputer lokal

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # jika belum ada

php artisan migrate --seed              # skema + data demo
npm run build                           # atau `npm run dev` saat mengubah tampilan
```

Lalu jalankan tiga proses (atau cukup `composer dev`):

```bash
php artisan serve          # aplikasi di http://127.0.0.1:8000
php artisan queue:work     # mengirim pesan keluar dari antrean
php artisan schedule:work  # menjalankan antrean:proses tiap menit (opsional)
```

> **Queue worker wajib berjalan** agar balasan agen benar-benar dikirim.
> Tanpa itu pesan menumpuk di tabel `antrean_pesan` dengan keputusan "Kirim".

### Akun demo (password semua: `password123`)

| Email | Role | Halaman awal |
|---|---|---|
| `admin@asentra.sch.id` | Administrator | Dasbor |
| `supervisor@asentra.sch.id` | Supervisor | Dasbor |
| `dewi@asentra.sch.id` | Agen/CS | Kotak Masuk |
| `nonaktif@asentra.sch.id` | Agen/CS (nonaktif) | ditolak saat login |

Seeder hanya untuk pengembangan. Jangan dijalankan di server yang berisi data nyata.

## Gateway WhatsApp

SAPA berbicara dengan WhatsApp lewat antarmuka `App\Services\WhatsApp\WhatsAppGateway`.
Driver diatur dengan `WHATSAPP_DRIVER` di `.env`.

| Driver | Keterangan |
|---|---|
| `fake` (bawaan) | Simulasi. Pesan keluar hanya dicatat di `storage/logs/laravel.log`. Pada halaman Nomor WhatsApp ada tombol "Simulasikan pindai (mode uji)". Teks `[gagal]` pada pesan memicu jalur gagal kirim. |

Driver sungguhan belum ada; tambahkan di `App\Providers\AppServiceProvider`.
Kode QR tampil sebagai kotak pengganti sampai paket `bacon/bacon-qr-code` dipasang
(`composer require bacon/bacon-qr-code`).

### Webhook (dipanggil oleh gateway)

Semua permintaan wajib memakai header `X-Gateway-Token` = `WHATSAPP_WEBHOOK_TOKEN`.

| Endpoint | Fungsi |
|---|---|
| `POST /api/webhook/whatsapp/pesan` | Pesan pelanggan masuk: `nomor`, `dari`, `isi`, opsional `nama`, `id_pesan` |
| `POST /api/webhook/whatsapp/sesi` | Status sesi: `nomor`, `status` (`tersambung` / `terputus` / `gagal`), opsional `alasan` |

Contoh:

```bash
curl -X POST http://127.0.0.1:8000/api/webhook/whatsapp/pesan \
  -H "X-Gateway-Token: $WHATSAPP_WEBHOOK_TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"nomor":"6281200000001","dari":"081277770001","nama":"Budi","isi":"Halo"}'
```

Respons: `201` tersimpan, `200` duplikat (`id_pesan` sama), `401` token salah,
`404` nomor tidak dikenal, `409` nomor tidak tersambung atau nonaktif.

## Hak akses

| Role | Akses |
|---|---|
| Agen/CS | Dasbor, Kotak Masuk, Prospek, Kontak, Basis Pengetahuan; hanya nomor WhatsApp yang diberi akses |
| Supervisor | Semua di atas + Nomor WhatsApp (kelola data), Pengaturan AI, Antrean & Risiko, Log Audit |
| Administrator | Semua, termasuk Tim & Akses serta penautan/pemutusan sesi WhatsApp |

Hak per nomor: `Tidak ada` · `Lihat saja` · `Balas chat` · `Kelola`.
Login ditahan 5 menit setelah 5 kali gagal.

## Pengujian

```bash
php artisan test
```

| Berkas tes | Cakupan |
|---|---|
| `LoginTest` | Login, kunci 5× gagal, akun nonaktif, logout, log audit (TC01) |
| `KotakMasukTest` | Percakapan per hak akses, balas, ubah status (TC02, TC07) |
| `WebhookWhatsappTest` | Pesan masuk, duplikat, token, sesi |
| `KirimPesanKeluarTest` | Pengiriman, percobaan ulang, pusat risiko |
| `NomorWhatsappSesiTest` | Penautan QR, kedaluwarsa, putus (TC11) |
| `HalamanTest` | Semua halaman terbuka sesuai role |

## Struktur penting

```
app/Services/WhatsApp/   gateway, sesi nomor, pesan masuk, QR
app/Jobs/                KirimPesanKeluar
app/Http/Controllers/    controller web; Api/ untuk webhook
routes/web.php           halaman    routes/api.php  webhook
```
