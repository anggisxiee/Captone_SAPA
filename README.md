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

Lalu jalankan tiga proses (atau cukup `composer dev`). Bila memakai gateway Baileys
(WhatsApp sungguhan), jalankan juga gateway-nya, lihat bagian "Gateway WhatsApp" di bawah:

```bash
php artisan serve          # aplikasi di http://127.0.0.1:8000
php artisan queue:work     # mengirim pesan keluar dari antrean
php artisan schedule:work  # menjalankan antrean:proses tiap menit (opsional)
```

> **Queue worker wajib berjalan** agar balasan agen benar-benar dikirim.
> Tanpa itu pesan menumpuk di tabel `antrean_pesan` dengan keputusan "Kirim".

> Sudah punya database dari versi sebelumnya? Cadangkan lalu jalankan `php artisan migrate` (bukan `migrate:fresh`).
> Rincian perubahan skema dan kesesuaiannya dengan SRS ada di [`docs/skema-vs-srs.md`](docs/skema-vs-srs.md).

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
| `fake` (bawaan) | Simulasi, tanpa WhatsApp sungguhan. Pesan keluar hanya dicatat di `storage/logs/laravel.log`. Halaman Nomor WhatsApp menampilkan tombol "Simulasikan pindai (mode uji)". Teks `[gagal]` pada pesan memicu jalur gagal kirim. |
| `baileys` | WhatsApp sungguhan lewat layanan **`wa-gateway/`** (Node.js + Baileys) di repo ini. Lihat [`wa-gateway/README.md`](wa-gateway/README.md). |

Menjalankan dengan driver `baileys`:

```bash
# 1) gateway
cd wa-gateway && npm install && cp .env.example .env     # isi GATEWAY_TOKEN
npm start

# 2) Laravel (.env)
WHATSAPP_DRIVER=baileys
WHATSAPP_WEBHOOK_TOKEN=<sama dengan GATEWAY_TOKEN>
WHATSAPP_GATEWAY_URL=http://127.0.0.1:3001
```

Jalur ini **tidak resmi** (WhatsApp Web), jadi nomor bisa dibatasi WhatsApp. Gunakan nomor uji, bukan nomor mitra.

### Webhook (dipanggil oleh gateway)

Semua permintaan wajib memakai header `X-Gateway-Token` = `WHATSAPP_WEBHOOK_TOKEN`.

| Endpoint | Fungsi |
|---|---|
| `POST /api/webhook/whatsapp/pesan` | Pesan pelanggan masuk: `nomor`, `dari`, `isi`, opsional `nama`, `id_pesan` |
| `POST /api/webhook/whatsapp/sesi` | Status sesi: `nomor`, `status` (`tersambung` / `terputus` / `gagal` / `qr`), opsional `alasan`; `qr` wajib bila status `qr` |

Contoh:

```bash
curl -X POST http://127.0.0.1:8000/api/webhook/whatsapp/pesan \
  -H "X-Gateway-Token: $WHATSAPP_WEBHOOK_TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"nomor":"6281200000001","dari":"081277770001","nama":"Budi","isi":"Halo"}'
```

Respons: `201` tersimpan, `200` duplikat (`id_pesan` sama), `401` token salah,
`404` nomor tidak dikenal, `409` nomor tidak tersambung atau nonaktif.

## AI (SumoPod / kompatibel OpenAI)

SAPA memakai penyedia AI lewat antarmuka `App\Services\Ai\AiClient`. Driver diatur dengan `AI_DRIVER`:

| Driver | Keterangan |
|---|---|
| `fake` (bawaan) | Simulasi tanpa penyedia. Dipakai tes dan pengembangan tanpa kunci. |
| `openai` | Penyedia kompatibel OpenAI (`POST {AI_BASE_URL}/chat/completions`). Bawaan: SumoPod `https://ai.sumopod.com/v1`, model `gpt-4o-mini`. |

```bash
# di .env
AI_DRIVER=openai
AI_API_KEY=sk-...            # buat di dashboard SumoPod, beri batas anggaran

php artisan ai:uji           # uji koneksi + kunci + tampilkan saldo (kunci disamarkan)
php artisan ai:uji "Halo"    # sekalian kirim satu pesan uji
```

Kunci yang disimpan di halaman **Pengaturan AI** (terenkripsi di basis data) mengalahkan `.env` **secara utuh**
(alamat dan model ikut dari halaman itu), supaya kunci satu penyedia tidak pernah terkirim ke alamat penyedia lain.
Bila penyedia gagal atau melewati batas waktu, pemanggil beralih ke mode aturan bawaan.

### Mesin jawaban dan eskalasi

`App\Services\Ai\MesinJawaban` memutuskan satu pesan pelanggan, dari langkah yang paling murah ke paling mahal:

1. kata kunci eskalasi (aturan, gratis) -> eskalasi
2. nada marah (aturan, gratis) -> eskalasi
3. artikel relevan dengan FTS5 (gratis); tanpa artikel -> eskalasi (BR07), **AI tidak dipanggil**
4. jawaban dari artikel oleh penyedia AI, disertai sumber; bila penyedia gagal atau melewati batas waktu -> **mode aturan bawaan** (artikel dikutip apa adanya)
5. keyakinan di bawah ambang (Pengaturan AI) -> eskalasi

Keyakinan = 0,5 x skor relevansi artikel + 0,5 x nilai yang dinilai model sendiri (rumus awal, dikalibrasi dengan 50 pertanyaan uji).
Pesan pelanggan hanya masuk sebagai peran `user`, tidak pernah ke instruksi sistem, dan kode sumber karangan model dibuang.

```bash
php artisan ai:jawab "berapa biaya bimbel SMP kelas 8?"   # jalankan mesin pada satu pesan, tanpa mengirim apa pun
```

Halaman **Pengaturan AI** memuat konsol "Uji Keputusan" yang memakai mesin yang sama. Kunci baru diuji ke penyedia sebelum
disimpan (ditolak bila penyedia menolak), nilai kunci tidak pernah dicatat di log audit, mengganti alamat penyedia
mewajibkan memasukkan ulang kunci, dan alamat internal atau `http` polos ditolak di luar pengembangan.

Belum otomatis: mesin ini belum dipasang pada alur pesan masuk WhatsApp (tahap berikutnya).

### Kontak dan prospek otomatis (F03, BR04)

Setiap pesan pelanggan baru (`PesanMasuk::simpan`) memicu `PembaruanProspek`:

- pengirim baru otomatis menjadi kontak dan prospek tahap `baru` (sumber `WhatsApp`); kontak lama tanpa prospek juga dibuatkan;
- `EkstraksiPesan` membaca maksud (biaya, jadwal, pendaftaran, program, fasilitas, keluhan), nama, jenjang, kelas, dan mata pelajaran dengan **aturan** (tanpa AI), jadi gratis dan isi pesan tidak keluar ke penyedia AI;
- hanya kolom yang masih **kosong** yang diisi, sehingga ubahan manual agen tidak tertimpa; nilai yang tidak yakin dibiarkan kosong, bukan ditebak;
- hasil ekstraksi disimpan pada `meta_ai.ekstraksi` pesan pelanggan, dan pembuatan prospek dicatat di Log Audit.

Skor prioritas masih 0 sampai PB-10 (CRM).

### Basis pengetahuan (FTS5)

Artikel dikelola di halaman **Basis Pengetahuan** (Supervisor dan Administrator; semua pengguna dapat melihat).
Pencarian memakai SQLite FTS5 dengan peringkat `bm25`; indeks dijaga sinkron oleh pemicu basis data. Skor 0 sampai 1
pada "Uji pencarian" = 0,6 x cakupan istilah + 0,4 x bm25 relatif; artikel di bawah `AI_AMBANG_RELEVANSI` tidak dipakai AI.
Tidak ada stemming bahasa Indonesia: gunakan kolom **label** untuk sinonim ("daftar" tidak menemukan "pendaftaran").

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
php artisan test            # Laravel
cd wa-gateway && npm test   # gateway Node (tanpa perlu Baileys terpasang)
```

| Berkas tes | Cakupan |
|---|---|
| `LoginTest` | Login, kunci 5× gagal, akun nonaktif, logout, log audit (TC01) |
| `KotakMasukTest` | Percakapan per hak akses, balas, ubah status (TC02, TC07) |
| `WebhookWhatsappTest` | Pesan masuk, duplikat, token, sesi |
| `KirimPesanKeluarTest` | Pengiriman, percobaan ulang, pusat risiko |
| `NomorWhatsappSesiTest` | Penautan QR, kedaluwarsa, putus (TC11) |
| `BaileysGatewayTest` | Driver `baileys` (gateway dipalsukan), event QR, gateway mati |
| `AiClientTest` | Klien AI kompatibel OpenAI (penyedia dipalsukan), konfigurasi, perintah `ai:uji` |
| `ArtikelPengetahuanTest` | Basis pengetahuan: hak akses, CRUD, pengindeksan FTS5, skor dan ambang |
| `MesinJawabanTest` | Mesin jawaban: eskalasi, jawaban AI dengan sumber, mode aturan bawaan, nada marah |
| `PengaturanAiTest` | Pengaturan AI: uji kunci, keamanan alamat dan log, batas BR13, konsol Uji Keputusan |
| `HalamanTest` | Semua halaman terbuka sesuai role |
| `SkemaSrsTest` | Tiap tabel dan kolom kamus data SRS ada, plus perilaku turunannya (PB-04) |
| `MigrasiSkemaSrsTest` | Peningkatan dari skema lama dengan data nyata tidak menghilangkan data |

## Struktur penting

```
app/Services/WhatsApp/   gateway (fake, baileys), sesi nomor, pesan masuk, QR
wa-gateway/              layanan Node.js + Baileys (proses terpisah)
app/Jobs/                KirimPesanKeluar
app/Http/Controllers/    controller web; Api/ untuk webhook
routes/web.php           halaman    routes/api.php  webhook
```
