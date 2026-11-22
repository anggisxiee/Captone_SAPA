<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanKeluar;
use App\Models\AntreanPesan;
use App\Models\Kontak;
use App\Models\LogAudit;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\Prospek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PB-04: skema basis data sesuai kamus data SRS (Tabel 3.19) dan aturan bisnis terkait.
 */
class SkemaSrsTest extends TestCase
{
    use RefreshDatabase;

    /** Kamus data SRS Tabel 3.19, disalin apa adanya (tabel => kolom). */
    private const KAMUS = [
        'users' => ['id', 'name', 'email', 'password', 'role', 'status'],
        'user_whatsapp_access' => ['id', 'user_id', 'nomor_whatsapp_id', 'hak_akses'],
        'nomor_whatsapp' => ['id', 'label', 'nomor', 'tujuan', 'status', 'mode_ai', 'skor_kesehatan'],
        'pengaturan_ai' => [
            'id', 'nomor_whatsapp_id', 'penyedia', 'model', 'api_key', 'instruksi_persona', 'ambang_keyakinan',
            'kata_kunci_eskalasi', 'jeda_min', 'jeda_max', 'batas_per_menit', 'batas_per_jam',
            'batas_pesan_harian', 'jam_mulai', 'jam_selesai',
        ],
        'kontak' => ['id', 'nomor_telepon', 'nama', 'jenjang', 'kelas', 'mapel', 'kota', 'sumber', 'label'],
        'percakapan' => [
            'id', 'kontak_id', 'nomor_whatsapp_id', 'agen_id', 'status', 'penangan', 'belum_dibaca',
            'waktu_balas_pertama',
        ],
        'pesan_whatsapp' => ['id', 'percakapan_id', 'arah', 'jenis_pengirim', 'isi', 'status', 'meta_ai'],
        'prospek' => [
            'id', 'kontak_id', 'penanggung_jawab', 'tahap', 'skor', 'rincian_skor', 'program',
            'tindak_lanjut_berikut',
        ],
        'aktivitas_prospek' => ['id', 'prospek_id', 'user_id', 'jenis', 'catatan', 'waktu'],
        'artikel_pengetahuan' => ['id', 'judul', 'kategori', 'isi', 'label', 'aktif'],
        'antrean_pesan' => [
            'id', 'nomor_whatsapp_id', 'pesan_whatsapp_id', 'penerima', 'isi_pesan', 'skor_risiko', 'keputusan',
            'percobaan', 'jadwal_kirim', 'terkirim_pada', 'alasan_penahanan',
        ],
        'riwayat_antrean' => ['id', 'antrean_pesan_id', 'status_sebelumnya', 'status_baru', 'keterangan', 'diubah_pada'],
        'insiden_risiko' => [
            'id', 'nomor_whatsapp_id', 'jenis_insiden', 'tingkat_risiko', 'deskripsi', 'status', 'terjadi_pada',
        ],
        'metrik_harian' => [
            'id', 'nomor_whatsapp_id', 'tanggal', 'pesan_masuk', 'pesan_keluar', 'balasan_ai', 'balasan_manusia',
            'belum_dibalas', 'rata_waktu_balas', 'prospek_baru', 'konversi',
        ],
        'log_audit' => ['id', 'user_id', 'aksi', 'entitas', 'entitas_id', 'detail', 'created_at'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_setiap_tabel_dan_kolom_kamus_data_srs_ada(): void
    {
        $kurang = [];

        foreach (self::KAMUS as $tabel => $kolom) {
            if (!Schema::hasTable($tabel)) {
                $kurang[] = "tabel {$tabel}";
                continue;
            }
            foreach ($kolom as $k) {
                if (!Schema::hasColumn($tabel, $k)) {
                    $kurang[] = "{$tabel}.{$k}";
                }
            }
        }

        $this->assertSame([], $kurang, 'Tidak ada dalam skema: ' . implode(', ', $kurang));
    }

    public function test_kolom_yang_tidak_lagi_dipakai_sudah_dibuang(): void
    {
        $this->assertFalse(Schema::hasColumn('nomor_whatsapp', 'nama'), 'diganti label');
        $this->assertFalse(Schema::hasColumn('percakapan', 'belum_dibalas'), 'diganti belum_dibaca');
        foreach (['kontak_nama', 'kontak_telepon', 'pengirim', 'nomor_whatsapp_id'] as $k) {
            $this->assertFalse(Schema::hasColumn('pesan_whatsapp', $k), "pesan_whatsapp.{$k}");
        }
        $this->assertFalse(Schema::hasColumn('antrean_pesan', 'penerima_telepon'));
        $this->assertFalse(Schema::hasColumn('log_audit', 'objek'));
        $this->assertFalse(Schema::hasColumn('log_audit', 'keterangan'));
    }

    // --- nomor_whatsapp -------------------------------------------------------------

    public function test_nomor_memakai_label_dan_nama_tetap_berfungsi_sebagai_alias(): void
    {
        $n = NomorWhatsapp::create(['nama' => 'Lewat alias', 'nomor' => '0812-0000-0001']);

        $this->assertSame('Lewat alias', $n->fresh()->label);
        $this->assertSame('Lewat alias', $n->fresh()->nama);
        $this->assertSame('draf', $n->fresh()->mode_ai);
        $this->assertSame(100, $n->fresh()->skor_kesehatan);

        $n->update(['label' => 'Diubah']);
        $this->assertSame('Diubah', $n->fresh()->nama);
    }

    // --- percakapan -----------------------------------------------------------------

    private function nomor(string $nomor = '6281200000001'): NomorWhatsapp
    {
        return NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => $nomor, 'status_sesi' => 'Tersambung']);
    }

    private function user(string $role = 'Agen/CS'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Aktif']);
    }

    public function test_penangan_selalu_mengikuti_status(): void
    {
        $p = Percakapan::untuk($this->nomor(), '6285711110001', 'Bu Ratna');
        $this->assertSame('AI', $p->fresh()->penangan);

        $p->update(['status' => 'Ditangani Agen']);
        $this->assertSame('agen', $p->fresh()->penangan);

        $p->update(['status' => 'Selesai']);
        $this->assertSame('agen', $p->fresh()->penangan, 'Selesai tidak mengubah penangan terakhir');

        $p->update(['status' => 'Ditangani AI']);
        $this->assertSame('AI', $p->fresh()->penangan);
    }

    public function test_belum_dibaca_bertambah_per_pesan_masuk_dan_nol_setelah_dibalas(): void
    {
        $nomor = $this->nomor();
        config(['whatsapp.webhook_token' => 'token-uji']);
        $kirim = fn (string $id) => $this->postJson('/api/webhook/whatsapp/pesan', [
            'nomor' => $nomor->nomor, 'dari' => '6285711110001', 'nama' => 'Bu Ratna', 'isi' => 'Halo', 'id_pesan' => $id,
        ], ['X-Gateway-Token' => 'token-uji'])->assertCreated();

        $kirim('A');
        $kirim('B');
        $kirim('C');

        $p = Percakapan::firstOrFail();
        $this->assertSame(3, $p->belum_dibaca);
        $this->assertTrue($p->belum_dibalas);

        $agen = $this->user();
        $agen->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);
        $this->actingAs($agen)->post(route('kotak-masuk.kirim'), ['percakapan_id' => $p->id, 'isi' => 'Halo juga'])
            ->assertRedirect();

        $p->refresh();
        $this->assertSame(0, $p->belum_dibaca);
        $this->assertFalse($p->belum_dibalas);
        $this->assertSame($agen->id, $p->agen_id, 'agen penangan tercatat');
        $this->assertSame('agen', $p->penangan);
    }

    public function test_waktu_balas_pertama_diisi_sekali_dalam_detik(): void
    {
        $nomor = $this->nomor();
        $p = Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');
        $masuk = new PesanWhatsapp([
            'percakapan_id' => $p->id, 'arah' => 'masuk', 'jenis_pengirim' => 'pelanggan', 'isi' => 'Halo', 'status' => 'diterima',
        ]);
        $masuk->created_at = now()->subSeconds(90);
        $masuk->save();

        $agen = $this->user();
        $agen->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Kelola']);
        $balas = fn () => $this->actingAs($agen)->post(route('kotak-masuk.kirim'), ['percakapan_id' => $p->id, 'isi' => 'Balasan']);

        $balas();
        $pertama = $p->fresh()->waktu_balas_pertama;
        $this->assertGreaterThanOrEqual(90, $pertama);
        $this->assertLessThan(100, $pertama);

        $this->travel(5)->minutes();
        $balas();
        $this->assertSame($pertama, $p->fresh()->waktu_balas_pertama, 'tidak ditimpa balasan berikutnya');
    }

    public function test_ambil_alih_menugaskan_agen_dan_kembalikan_ke_ai_melepasnya(): void
    {
        $nomor = $this->nomor();
        $p = Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');
        $agen = $this->user();
        $agen->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);
        $this->actingAs($agen);

        $this->patch(route('kotak-masuk.status', $p), ['status' => 'Ditangani Agen']);
        $this->assertSame($agen->id, $p->fresh()->agen_id);

        $this->patch(route('kotak-masuk.status', $p), ['status' => 'Ditangani AI']);
        $this->assertNull($p->fresh()->agen_id);
        $this->assertSame('AI', $p->fresh()->penangan);
    }

    // --- kontak ---------------------------------------------------------------------

    public function test_kontak_boleh_tanpa_nama_dan_nama_baru_diisi_kemudian(): void
    {
        $nomor = $this->nomor();
        config(['whatsapp.webhook_token' => 'token-uji']);
        $kirim = fn (array $d) => $this->postJson('/api/webhook/whatsapp/pesan', $d + [
            'nomor' => $nomor->nomor, 'dari' => '6285711110001', 'isi' => 'Halo',
        ], ['X-Gateway-Token' => 'token-uji'])->assertCreated();

        $kirim(['id_pesan' => 'A']);
        $kontak = Kontak::firstOrFail();
        $this->assertNull($kontak->nama);
        $this->assertSame('6285711110001', $kontak->nama_tampil, 'nomor ditampilkan bila nama belum ada');

        $kirim(['id_pesan' => 'B', 'nama' => 'Bu Ratna']);
        $this->assertSame('Bu Ratna', $kontak->fresh()->nama);

        $kirim(['id_pesan' => 'C', 'nama' => 'Nama Lain']);
        $this->assertSame('Bu Ratna', $kontak->fresh()->nama, 'nama yang sudah ada tidak ditimpa');
    }

    // --- pesan_whatsapp dan antrean_pesan ----------------------------------------------

    public function test_balasan_terhubung_ke_antrean_dan_status_pesan_mengikuti_hasil_kirim(): void
    {
        $nomor = $this->nomor();
        $p = Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');
        $agen = $this->user('Administrator');
        $this->actingAs($agen);

        $this->post(route('kotak-masuk.kirim'), ['percakapan_id' => $p->id, 'isi' => 'Berhasil'])->assertRedirect();
        $this->post(route('kotak-masuk.kirim'), ['percakapan_id' => $p->id, 'isi' => 'Ini [gagal]'])->assertRedirect();

        $antrean = AntreanPesan::orderBy('id')->get();
        $this->assertSame('6285711110001', $antrean[0]->penerima, 'penerima berisi nomor tujuan (SRS)');
        $this->assertNotNull($antrean[0]->pesan_whatsapp_id);

        $this->assertSame('terkirim', $antrean[0]->pesanWhatsapp->status);
        $this->assertSame('keluar', $antrean[0]->pesanWhatsapp->arah);
        $this->assertSame('gagal', $antrean[1]->pesanWhatsapp->status);
        $this->assertSame('Gagal', $antrean[1]->keputusan);
    }

    public function test_pesan_masuk_berstatus_diterima(): void
    {
        $nomor = $this->nomor();
        config(['whatsapp.webhook_token' => 'token-uji']);
        $this->postJson('/api/webhook/whatsapp/pesan', [
            'nomor' => $nomor->nomor, 'dari' => '6285711110001', 'isi' => 'Halo', 'id_pesan' => 'X',
        ], ['X-Gateway-Token' => 'token-uji'])->assertCreated();

        $this->assertDatabaseHas('pesan_whatsapp', ['arah' => 'masuk', 'jenis_pengirim' => 'pelanggan', 'status' => 'diterima']);
    }

    public function test_meta_ai_disimpan_sebagai_json(): void
    {
        $p = Percakapan::untuk($this->nomor(), '6285711110001', 'Bu Ratna');
        $m = PesanWhatsapp::create([
            'percakapan_id' => $p->id, 'arah' => 'keluar', 'jenis_pengirim' => 'ai', 'isi' => 'Jawaban', 'status' => 'terkirim',
            'meta_ai' => ['maksud' => 'tanya_biaya', 'keyakinan' => 0.88, 'artikel' => [4, 12]],
        ]);

        $this->assertSame(0.88, $m->fresh()->meta_ai['keyakinan']);
        $this->assertSame([4, 12], $m->fresh()->meta_ai['artikel']);
    }

    // --- log audit ------------------------------------------------------------------

    public function test_log_audit_memakai_nama_tabel_id_dan_detail_json(): void
    {
        $nomor = $this->nomor();
        $p = Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');
        $agen = $this->user('Administrator');
        $this->actingAs($agen)->patch(route('kotak-masuk.status', $p), ['status' => 'Selesai']);

        $log = LogAudit::where('aksi', 'Ubah status percakapan')->firstOrFail();
        $this->assertSame('percakapan', $log->entitas);
        $this->assertSame($p->id, $log->entitas_id);
        $this->assertSame('Baru', $log->detail['sebelum']);
        $this->assertSame('Selesai', $log->detail['sesudah']);
        $this->assertSame($agen->id, $log->user_id);
    }

    public function test_login_tercatat_untuk_entitas_users(): void
    {
        $user = User::factory()->create(['email' => 'uji@asentra.sch.id', 'password' => 'rahasia123', 'role' => 'Agen/CS', 'status' => 'Aktif']);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'rahasia123']);

        $log = LogAudit::where('aksi', 'Login')->firstOrFail();
        $this->assertSame('users', $log->entitas);
        $this->assertSame($user->id, $log->entitas_id);
    }

    // --- BR03: Supervisor membaca semua nomor ------------------------------------------

    public function test_supervisor_melihat_semua_nomor_tetapi_membalas_hanya_dengan_hak(): void
    {
        $a = $this->nomor('6281200000001');
        $b = NomorWhatsapp::create(['label' => 'Sales', 'nomor' => '6281200000002', 'status_sesi' => 'Tersambung']);
        $pa = Percakapan::untuk($a, '6285711110001', 'A');
        $pb = Percakapan::untuk($b, '6285711110002', 'B');

        $sup = $this->user('Supervisor');
        $sup->nomorWhatsapp()->attach($a->id, ['hak_akses' => 'Balas chat']); // nomor b: tanpa baris akses

        $respons = $this->actingAs($sup)->get(route('kotak-masuk'))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$pa->id, $pb->id],
            collect($respons->viewData('daftar'))->pluck('id')->all(),
            'BR03: Supervisor membaca semua nomor'
        );
        $this->assertSame([$a->id], $respons->viewData('nomorBolehBalas'), 'membalas tetap per hak akses');

        $this->post(route('kotak-masuk.kirim'), ['percakapan_id' => $pb->id, 'isi' => 'Tidak boleh'])->assertForbidden();
        $this->post(route('kotak-masuk.kirim'), ['percakapan_id' => $pa->id, 'isi' => 'Boleh'])->assertRedirect();
    }

    public function test_agen_hanya_melihat_nomor_yang_ditugaskan(): void
    {
        $a = $this->nomor('6281200000001');
        $b = NomorWhatsapp::create(['label' => 'Sales', 'nomor' => '6281200000002']);
        $pa = Percakapan::untuk($a, '6285711110001', 'A');
        Percakapan::untuk($b, '6285711110002', 'B');
        $agen = $this->user();
        $agen->nomorWhatsapp()->attach($a->id, ['hak_akses' => 'Lihat saja']);

        $respons = $this->actingAs($agen)->get(route('kotak-masuk'))->assertOk();

        $this->assertSame([$pa->id], collect($respons->viewData('daftar'))->pluck('id')->all());
    }

    // --- prospek --------------------------------------------------------------------

    public function test_prospek_baru_terhubung_ke_kontak_dengan_nomor_diseragamkan(): void
    {
        $this->actingAs($this->user('Administrator'))->post(route('prospek.store'), [
            'nama' => 'Calon Siswa', 'nomor_telepon' => '0812-3456-7890', 'tahap' => 'baru', 'jenjang' => 'SMP', 'sumber' => 'Instagram',
        ])->assertRedirect(route('prospek'));

        $prospek = Prospek::firstOrFail();
        $this->assertNotNull($prospek->kontak_id);
        $this->assertSame('6281234567890', $prospek->kontak->nomor_telepon);
        $this->assertSame('Calon Siswa', $prospek->kontak->nama);
    }

    public function test_prospek_dengan_nomor_yang_sama_memakai_kontak_yang_sama(): void
    {
        $this->actingAs($this->user('Administrator'));
        $kirim = fn (string $nomor) => $this->post(route('prospek.store'), ['nama' => 'X', 'nomor_telepon' => $nomor, 'tahap' => 'baru']);

        $kirim('081234567890');
        $kirim('+62 812 3456 7890');

        $this->assertSame(1, Kontak::count());
        $this->assertSame(2, Prospek::count());
        $this->assertSame(1, Prospek::distinct('kontak_id')->count('kontak_id'));
    }

    public function test_halaman_antrean_menampilkan_nama_kontak_dan_nomor_tujuan(): void
    {
        $nomor = $this->nomor();
        $p = Percakapan::untuk($nomor, '6285711110001', 'Bu Ratna');
        $this->actingAs($this->user('Administrator'))
            ->post(route('kotak-masuk.kirim'), ['percakapan_id' => $p->id, 'isi' => 'Halo'])
            ->assertRedirect();

        $this->get(route('antrean-risiko'))
            ->assertOk()
            ->assertSee('Bu Ratna · 6285711110001');
    }
}
