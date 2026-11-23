<?php

namespace Tests\Feature;

use App\Models\ArtikelPengetahuan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Pengetahuan\PencarianArtikel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F12 / PB-05: basis pengetahuan dengan pencarian teks penuh FTS5 (bm25). */
class ArtikelPengetahuanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Aktif']);
    }

    private function artikel(string $judul, string $isi, string $kategori = 'program', ?string $label = null, bool $aktif = true): ArtikelPengetahuan
    {
        return ArtikelPengetahuan::create(compact('judul', 'isi', 'kategori', 'label', 'aktif'));
    }

    private function cari(string $pertanyaan, bool $hanyaAktif = true): array
    {
        return app(PencarianArtikel::class)->cari($pertanyaan, 5, $hanyaAktif);
    }

    private function judul(array $hasil): array
    {
        return array_map(fn ($h) => $h['artikel']->judul, $hasil);
    }

    // --- hak akses ---------------------------------------------------------------------------

    public function test_semua_pengguna_melihat_daftar_tetapi_hanya_supervisor_dan_admin_mengelola(): void
    {
        $this->artikel('Jadwal SMP', 'Selasa dan Kamis');

        $agen = $this->actingAs($this->user('Agen/CS'))->get(route('basis-pengetahuan'));
        $agen->assertOk()->assertSee('Jadwal SMP')->assertDontSee('Tambah artikel')->assertDontSee('Jalankan uji');

        foreach (['Supervisor', 'Administrator'] as $role) {
            $this->actingAs($this->user($role))->get(route('basis-pengetahuan'))
                ->assertOk()->assertSee('Tambah artikel')->assertSee('Jalankan uji');
        }
    }

    public function test_agen_tidak_dapat_menambah_mengubah_atau_menguji(): void
    {
        $a = $this->artikel('Jadwal SMP', 'Selasa dan Kamis');
        $this->actingAs($this->user('Agen/CS'));

        $data = ['judul' => 'X', 'kategori' => 'jadwal', 'isi' => 'Y'];
        $this->post(route('basis-pengetahuan.store'), $data)->assertRedirect(route('dasbor'));
        $this->put(route('basis-pengetahuan.update', $a), $data)->assertRedirect(route('dasbor'));
        $this->postJson(route('basis-pengetahuan.uji'), ['pertanyaan' => 'jadwal'])->assertRedirect(route('dasbor'));

        $this->assertSame(1, ArtikelPengetahuan::count());
        $this->assertSame('Jadwal SMP', $a->fresh()->judul);
    }

    // --- tambah, ubah, nonaktifkan ---------------------------------------------------------------

    public function test_artikel_baru_langsung_ditemukan_dan_tercatat_di_log_audit(): void
    {
        $admin = $this->user('Administrator');

        $this->actingAs($admin)->post(route('basis-pengetahuan.store'), [
            'judul' => 'Fasilitas kolam renang', 'kategori' => 'fasilitas', 'isi' => 'Tersedia kolam renang untuk siswa.', 'aktif' => '1',
        ])->assertRedirect(route('basis-pengetahuan'));

        $this->assertSame(['Fasilitas kolam renang'], $this->judul($this->cari('ada kolam renang?')));

        $log = LogAudit::where('aksi', 'Tambah artikel')->firstOrFail();
        $this->assertSame(['artikel_pengetahuan', $admin->id], [$log->entitas, $log->user_id]);
        $this->assertSame(ArtikelPengetahuan::first()->id, $log->entitas_id);
        $this->assertSame('fasilitas', $log->detail['kategori']);
    }

    public function test_judul_atau_isi_kosong_ditolak_dengan_pesan_srs_satu_kali(): void
    {
        $this->actingAs($this->user('Supervisor'));

        $r = $this->post(route('basis-pengetahuan.store'), ['judul' => '', 'kategori' => 'jadwal', 'isi' => '']);
        $r->assertSessionHasErrors(['judul', 'isi']);
        $this->assertSame(['Judul dan isi wajib diisi.'], collect(session('errors')->all())->unique()->values()->all());

        $this->post(route('basis-pengetahuan.store'), ['judul' => 'x', 'kategori' => 'tidak-ada', 'isi' => 'y'])->assertSessionHasErrors('kategori');
        $this->post(route('basis-pengetahuan.store'), ['judul' => str_repeat('a', 256), 'kategori' => 'jadwal', 'isi' => 'y'])->assertSessionHasErrors('judul');

        $this->assertSame(0, ArtikelPengetahuan::count());
    }

    public function test_mengubah_artikel_mengindeks_ulang(): void
    {
        $a = $this->artikel('Fasilitas', 'Tersedia kolam renang.', 'fasilitas');
        $this->assertCount(1, $this->cari('kolam'));

        $this->actingAs($this->user('Supervisor'))->put(route('basis-pengetahuan.update', $a), [
            'judul' => 'Fasilitas', 'kategori' => 'fasilitas', 'isi' => 'Tersedia perpustakaan.', 'aktif' => '1',
        ])->assertRedirect();

        $this->assertSame([], $this->cari('kolam'), 'istilah lama tidak lagi ditemukan');
        $this->assertCount(1, $this->cari('perpustakaan'), 'istilah baru langsung ditemukan');
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Ubah artikel', 'entitas_id' => $a->id]);
    }

    public function test_nonaktif_tidak_ditemukan_dan_dapat_diaktifkan_kembali(): void
    {
        $a = $this->artikel('Biaya', 'Biaya bulanan Rp450.000', 'biaya');
        $this->actingAs($this->user('Supervisor'));
        $kirim = fn (bool $aktif) => $this->put(route('basis-pengetahuan.update', $a), array_filter([
            'judul' => 'Biaya', 'kategori' => 'biaya', 'isi' => 'Biaya bulanan Rp450.000', 'aktif' => $aktif ? '1' : null,
        ]));

        $kirim(false)->assertRedirect();
        $this->assertSame([], $this->cari('biaya'));
        $this->assertCount(1, $this->cari('biaya', false), 'tetap ada pada pencarian untuk pengelola');
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Nonaktifkan artikel', 'entitas_id' => $a->id]);

        $kirim(true)->assertRedirect();
        $this->assertCount(1, $this->cari('biaya'));
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Aktifkan artikel', 'entitas_id' => $a->id]);
    }

    public function test_menyimpan_tanpa_perubahan_tidak_mencatat_log(): void
    {
        $a = $this->artikel('Biaya', 'Biaya bulanan', 'biaya');
        $this->actingAs($this->user('Supervisor'))->put(route('basis-pengetahuan.update', $a), [
            'judul' => 'Biaya', 'kategori' => 'biaya', 'isi' => 'Biaya bulanan', 'aktif' => '1',
        ])->assertRedirect();

        $this->assertSame(0, LogAudit::where('entitas', 'artikel_pengetahuan')->count());
    }

    public function test_penghapusan_langsung_di_basis_data_juga_menghapus_dari_indeks(): void
    {
        $a = $this->artikel('Fasilitas', 'Tersedia kolam renang.', 'fasilitas');
        $this->assertCount(1, $this->cari('kolam'));

        DB::table('artikel_pengetahuan')->where('id', $a->id)->delete();   // tanpa lewat aplikasi: pemicu yang menjaga indeks

        $this->assertSame([], $this->cari('kolam'));
    }

    // --- peringkat dan skor ----------------------------------------------------------------------------

    public function test_judul_lebih_berbobot_daripada_isi(): void
    {
        $this->artikel('Kebijakan umum', 'Pembahasan panjang yang menyebut matematika sekali saja di tengah kalimat.');
        $this->artikel('Matematika', 'Ringkasan program.');

        $this->assertSame('Matematika', $this->cari('matematika')[0]['artikel']->judul);
    }

    public function test_skor_dapat_dijelaskan_dan_artikel_tepat_melewati_ambang(): void
    {
        $this->artikel('Jadwal dan biaya SMP kelas 8', 'Selasa dan Kamis pukul 16.00. Biaya mulai Rp450.000 per bulan.', 'jadwal');
        $this->artikel('Fasilitas kelas', 'Kelas berpendingin ruangan dan Wi-Fi.', 'fasilitas');

        $hasil = $this->cari('berapa biaya SMP kelas 8?');

        $this->assertSame('Jadwal dan biaya SMP kelas 8', $hasil[0]['artikel']->judul);
        $this->assertSame(1.0, $hasil[0]['cakupan'], 'semua istilah (biaya, smp, kelas, 8) ada di artikel');
        $this->assertGreaterThanOrEqual(config('ai.ambang_relevansi'), $hasil[0]['skor']);

        $pendamping = collect($hasil)->firstWhere(fn ($h) => $h['artikel']->judul === 'Fasilitas kelas');
        $this->assertLessThan(config('ai.ambang_relevansi'), $pendamping['skor'], 'hanya cocok pada kata "kelas"');

        $this->assertSame(
            [$hasil[0]],
            app(PencarianArtikel::class)->relevan($hasil),
            'hanya artikel di atas ambang yang boleh dipakai AI'
        );
    }

    public function test_ambang_dapat_diatur(): void
    {
        $this->artikel('Jadwal dan biaya SMP', 'Selasa dan Kamis', 'jadwal');
        $hasil = $this->cari('jadwal smp');

        $this->assertCount(1, app(PencarianArtikel::class)->relevan($hasil, 0.1));
        $this->assertCount(0, app(PencarianArtikel::class)->relevan($hasil, 1.01));
    }

    public function test_topik_yang_tidak_ada_dan_sapaan_tidak_menghasilkan_apa_apa(): void
    {
        $this->artikel('Jadwal SMP', 'Selasa dan Kamis pukul 16.00');

        $this->assertSame([], $this->cari('apakah ada kolam renang'));
        $this->assertSame([], $this->cari('halo kak, selamat pagi'), 'hanya kata umum');
        $this->assertSame([], $this->cari(''));
        $this->assertSame([], $this->cari('   ?!  '));
    }

    public function test_angka_pada_pertanyaan_dihitung_sebagai_istilah(): void
    {
        $this->artikel('Kelas 8', 'Materi kelas 8 semester ganjil.');
        $this->artikel('Kelas 9', 'Materi kelas 9 semester ganjil.');

        $this->assertSame('Kelas 8', $this->cari('materi kelas 8')[0]['artikel']->judul);
        $this->assertContains('8', app(PencarianArtikel::class)->istilah('materi kelas 8'));
    }

    public function test_label_menambah_sinonim_yang_tidak_ada_di_isi(): void
    {
        $this->artikel('Pendaftaran siswa baru', 'Isi formulir dan bayar biaya awal.', 'pendaftaran');
        $this->assertSame([], $this->cari('mau daftar'), 'tanpa label: "daftar" bukan awalan dari "pendaftaran"');

        ArtikelPengetahuan::first()->update(['label' => 'daftar,registrasi']);

        $this->assertCount(1, $this->cari('mau daftar'));
        $this->assertCount(1, $this->cari('registrasi'));
    }

    public function test_karakter_khusus_fts5_tidak_menimbulkan_galat_atau_injeksi(): void
    {
        $this->artikel('Jadwal SMP', 'Selasa dan Kamis');

        foreach (['"', '""', 'jadwal OR', 'jadwal AND NOT', 'NEAR(a b)', '*', '(jadwal', 'jadwal)', '^jadwal', 'jadwal*', 'a:b',
            "'; DROP TABLE artikel_pengetahuan; --", 'judul:jadwal', '{jadwal}', str_repeat('jadwal ', 500), "jadwal\0smp"] as $q) {
            $this->assertIsArray($this->cari($q), "pertanyaan: {$q}");
        }

        $this->assertSame(1, ArtikelPengetahuan::count(), 'tabel utuh');
        $this->assertCount(1, $this->cari('"jadwal"'));
    }

    public function test_jumlah_istilah_dibatasi(): void
    {
        $istilah = app(PencarianArtikel::class)->istilah(implode(' ', array_map(fn ($i) => "kata{$i}", range(1, 40))));

        $this->assertCount(12, $istilah);
    }

    // --- uji pencarian (JSON) ------------------------------------------------------------------------------

    public function test_endpoint_uji_mengembalikan_hasil_skor_dan_istilah(): void
    {
        $this->artikel('Jadwal dan biaya SMP kelas 8', 'Selasa dan Kamis. Biaya Rp450.000.', 'jadwal');
        $this->artikel('Fasilitas kelas', 'Wi-Fi dan pendingin.', 'fasilitas');

        $this->actingAs($this->user('Supervisor'))
            ->postJson(route('basis-pengetahuan.uji'), ['pertanyaan' => 'biaya SMP kelas 8'])
            ->assertOk()
            ->assertJsonPath('ambang', 0.7)
            ->assertJsonPath('istilah', ['biaya', 'smp', 'kelas', '8'])
            ->assertJsonPath('hasil.0.kode', 'KB-001')
            ->assertJsonPath('hasil.0.kategori', 'Jadwal')
            ->assertJsonPath('hasil.0.dipakai', true)
            ->assertJsonPath('hasil.1.dipakai', false);
    }

    public function test_endpoint_uji_menolak_pertanyaan_kosong(): void
    {
        $this->actingAs($this->user('Administrator'))
            ->postJson(route('basis-pengetahuan.uji'), ['pertanyaan' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pertanyaan');
    }

    // --- daftar, filter, hitungan ----------------------------------------------------------------------------

    public function test_daftar_menyaring_menurut_kategori_status_dan_kata_kunci_serta_menghitung_per_kategori(): void
    {
        $this->artikel('Jadwal SMP', 'x', 'jadwal');
        $this->artikel('Jadwal SMA', 'x', 'jadwal', 'sma');
        $this->artikel('Biaya bulanan', 'x', 'biaya');
        $this->artikel('Draf garansi', 'x', 'program', null, false);
        $this->actingAs($this->user('Agen/CS'));

        $r = $this->get(route('basis-pengetahuan'))->assertOk();
        $this->assertSame(4, $r->viewData('total'));
        $this->assertSame(2, $r->viewData('hitung')['jadwal']);
        $this->assertCount(4, $r->viewData('daftar'));
        $this->assertSame('Draf garansi', $r->viewData('daftar')->last()->judul, 'artikel nonaktif di urutan akhir');

        $judul = fn ($resp) => $resp->viewData('daftar')->pluck('judul')->all();
        $this->assertEqualsCanonicalizing(['Jadwal SMP', 'Jadwal SMA'], $judul($this->get(route('basis-pengetahuan', ['kategori' => 'jadwal']))));
        $this->assertSame(['Draf garansi'], $judul($this->get(route('basis-pengetahuan', ['status' => 'nonaktif']))));
        $this->assertSame(['Jadwal SMA'], $judul($this->get(route('basis-pengetahuan', ['q' => 'sma']))), 'cocok pada label juga');
        $this->assertCount(4, $this->get(route('basis-pengetahuan', ['kategori' => 'bukan-kategori']))->viewData('daftar'), 'kategori tak dikenal diabaikan');
    }

    // --- data contoh ------------------------------------------------------------------------------------------------

    public function test_data_contoh_seeder_dapat_dicari_dengan_benar(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ambang = config('ai.ambang_relevansi');

        foreach ([
            'berapa biaya bimbel SMP kelas 8?' => 'Jadwal dan biaya SMP kelas 8',
            'ada diskon untuk adik kakak?' => 'Skema diskon kakak-beradik',
            'bisa cicil pembayaran?' => 'Cara pembayaran dan cicilan',
            'fasilitas wifi dan ac ada?' => 'Fasilitas kelas dan ruang belajar',
            'persiapan utbk kelas 12' => 'Program intensif UTBK',
        ] as $tanya => $diharapkan) {
            $hasil = $this->cari($tanya);
            $this->assertSame($diharapkan, $hasil[0]['artikel']->judul, $tanya);
            $this->assertGreaterThanOrEqual($ambang, $hasil[0]['skor'], $tanya);
        }

        $this->assertSame([], $this->cari('garansi nilai naik?'), 'artikel draf (nonaktif) tidak dipakai');
        $this->assertSame([], app(PencarianArtikel::class)->relevan($this->cari('apakah ada kolam renang')));
    }

    public function test_pertanyaan_dua_topik_menunjukkan_keterbatasan_yang_diketahui(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hasil = $this->cari('jadwal kelas 8 SMP dan diskon kakak beradik');

        $this->assertSame('Jadwal dan biaya SMP kelas 8', $hasil[0]['artikel']->judul);
        $this->assertGreaterThanOrEqual(config('ai.ambang_relevansi'), $hasil[0]['skor']);

        $diskon = collect($hasil)->firstWhere(fn ($h) => $h['artikel']->judul === 'Skema diskon kakak-beradik');
        $this->assertNotNull($diskon, 'artikel diskon tetap ditemukan');
        $this->assertLessThan(config('ai.ambang_relevansi'), $diskon['skor'],
            'KETERBATASAN: skor per artikel hanya menutup sebagian pertanyaan; tahap RAG perlu memilih artikel bertahap');
    }
}
