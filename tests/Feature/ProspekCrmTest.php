<?php

namespace Tests\Feature;

use App\Models\Kontak;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\Prospek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F08 / BR05 / BR06 / BR03: papan prospek, tindak lanjut dengan catatan wajib, skor dan hak akses. */
class ProspekCrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role = 'Administrator'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Aktif']);
    }

    private function prospek(string $telepon = '6285711110001', array $o = [], ?NomorWhatsapp $nomor = null): Prospek
    {
        $k = Kontak::create(['nomor_telepon' => $telepon, 'nama' => 'Kontak ' . substr($telepon, -2)]);
        if ($nomor) {
            Percakapan::create(['nomor_whatsapp_id' => $nomor->id, 'kontak_id' => $k->id]);
        }

        return Prospek::create($o + ['kontak_id' => $k->id, 'tahap' => 'baru']);
    }

    private function nomor(string $nomor = '6281200000001'): NomorWhatsapp
    {
        return NomorWhatsapp::create(['label' => 'N' . $nomor, 'nomor' => $nomor, 'status_sesi' => 'Tersambung']);
    }

    private function lanjut(Prospek $p, array $data = [], ?User $oleh = null)
    {
        return $this->actingAs($oleh ?? $this->user())->patch("/prospek/{$p->id}/tindak-lanjut", $data + [
            'tahap' => $p->tahap, 'catatan' => 'Sudah dihubungi lewat telepon.',
        ]);
    }

    // --- BR05: tindak lanjut -----------------------------------------------------------------

    public function test_pindah_tahap_mencatat_aktivitas_audit_dan_menetapkan_pemilik(): void
    {
        $p = $this->prospek();
        $admin = $this->user();

        $this->lanjut($p, ['tahap' => 'dihubungi', 'tindak_lanjut_berikut' => now()->addDays(3)->format('Y-m-d')], $admin)
            ->assertRedirect('/prospek')->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('dihubungi', $p->tahap);
        $this->assertSame($admin->id, $p->penanggung_jawab);
        $this->assertSame(now()->addDays(3)->format('Y-m-d'), $p->tindak_lanjut_berikut->format('Y-m-d'));
        $a = $p->aktivitas()->sole();
        $this->assertSame('perubahan_tahap', $a->jenis);
        $this->assertStringContainsString('Baru → Dihubungi', $a->catatan);
        $this->assertSame($admin->id, $a->user_id);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Ubah tahap prospek']);
    }

    public function test_tanpa_ganti_tahap_hanya_mencatat_aktivitas(): void
    {
        $p = $this->prospek(o: ['tahap' => 'tertarik']);

        $this->lanjut($p, ['catatan' => 'Menunggu jawaban orang tua'])->assertSessionHasNoErrors();

        $this->assertSame('catatan', $p->aktivitas()->sole()->jenis);
        $this->assertDatabaseMissing('log_audit', ['aksi' => 'Ubah tahap prospek']);
    }

    public function test_br05_catatan_kosong_atau_spasi_ditolak_dan_tidak_ada_yang_berubah(): void
    {
        $p = $this->prospek();

        $this->lanjut($p, ['tahap' => 'daftar', 'catatan' => ''])->assertSessionHasErrors('catatan');
        $this->lanjut($p, ['tahap' => 'daftar', 'catatan' => "   \n "])->assertSessionHasErrors('catatan');

        $this->assertSame('baru', $p->fresh()->tahap);
        $this->assertSame(0, $p->aktivitas()->count());
    }

    public function test_tahap_tidak_valid_dan_tanggal_lampau_ditolak(): void
    {
        $p = $this->prospek();

        $this->lanjut($p, ['tahap' => 'ngawur'])->assertSessionHasErrors('tahap');
        $this->lanjut($p, ['tindak_lanjut_berikut' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('tindak_lanjut_berikut');
        $this->assertSame(0, $p->aktivitas()->count());
    }

    public function test_pemilik_yang_sudah_ada_tidak_diganti(): void
    {
        $pemilik = $this->user('Supervisor');
        $p = $this->prospek(o: ['penanggung_jawab' => $pemilik->id]);

        $this->lanjut($p, [], $this->user())->assertSessionHasNoErrors();

        $this->assertSame($pemilik->id, $p->fresh()->penanggung_jawab);
    }

    // --- BR06: skor dihitung ulang ---------------------------------------------------------------

    public function test_skor_dihitung_ulang_saat_tahap_dan_aktivitas_berubah(): void
    {
        $p = $this->prospek();
        app(\App\Services\Prospek\SkorPrioritas::class)->simpan($p);
        $sebelum = $p->fresh()->skor;

        $this->lanjut($p, ['tahap' => 'penawaran']);

        $p->refresh();
        $this->assertGreaterThan($sebelum, $p->skor);
        $this->assertCount(5, $p->rincian_skor);
    }

    public function test_pesan_whatsapp_baru_menghitung_ulang_skor_lewat_webhook(): void
    {
        config(['whatsapp.webhook_token' => 'tok']);
        NomorWhatsapp::create(['label' => 'Admisi', 'nomor' => '6281200000001', 'status_sesi' => 'Tersambung', 'mode_ai' => 'mati']);
        $kirim = fn (string $isi, string $id) => $this->postJson('/api/webhook/whatsapp/pesan', [
            'nomor' => '6281200000001', 'dari' => '6285711110001', 'isi' => $isi, 'id_pesan' => $id,
        ], ['X-Gateway-Token' => 'tok'])->assertSuccessful();

        $kirim('Halo', 'A');
        $awal = Prospek::sole()->skor;
        $kirim('Mau daftar kelas 8 SMP matematika, ujian minggu depan', 'B');

        $this->assertGreaterThan($awal, Prospek::sole()->skor);
    }

    // --- BR03: hak akses -------------------------------------------------------------------------

    public function test_agen_hanya_melihat_dan_mengelola_prospek_pada_nomor_yang_ditugaskan(): void
    {
        $milik = $this->nomor('6281200000001');
        $lain = $this->nomor('6281200000002');
        $saya = $this->prospek('6285711110001', [], $milik);
        $orang = $this->prospek('6285711110002', [], $lain);
        $agen = $this->user('Agen/CS');
        $agen->nomorWhatsapp()->attach($milik->id, ['hak_akses' => 'Balas chat']);

        $data = $this->actingAs($agen)->get('/prospek')->viewData('prospek');
        $this->assertSame([$saya->id], array_column($data, 'id'));
        $this->assertTrue($data[0]['bisaUbah']);

        $this->lanjut($orang, [], $agen)->assertForbidden();
        $this->assertSame(0, $orang->aktivitas()->count());
    }

    public function test_agen_lihat_saja_tidak_boleh_tindak_lanjut(): void
    {
        $nomor = $this->nomor();
        $p = $this->prospek('6285711110001', [], $nomor);
        $agen = $this->user('Agen/CS');
        $agen->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Lihat saja']);

        $this->assertFalse($this->actingAs($agen)->get('/prospek')->viewData('prospek')[0]['bisaUbah']);
        $this->lanjut($p, [], $agen)->assertForbidden();
    }

    // --- tampilan ----------------------------------------------------------------------------

    public function test_papan_diurutkan_menurut_skor_dengan_rincian_dan_warna(): void
    {
        // Prospek bernilai tinggi dibuat lebih dulu: urutan harus mengikuti skor, bukan id.
        $tinggi = $this->prospek('6285711110002', ['skor' => 85, 'rincian_skor' => [['kunci' => 'urgensi', 'label' => 'Urgensi', 'bobot' => 25, 'nilai' => 1, 'poin' => 25, 'keterangan' => 'x']]]);
        $rendah = $this->prospek('6285711110001', ['skor' => 20]);

        $data = $this->actingAs($this->user())->get('/prospek')->assertOk()->viewData('prospek');

        $this->assertSame([$tinggi->id, $rendah->id], array_column($data, 'id'));
        $this->assertSame(['tinggi', 'rendah'], array_column($data, 'warnaSkor'));
        $this->assertSame('Urgensi', $data[0]['rincian'][0]['label']);
    }

    public function test_bobot_pada_halaman_berasal_dari_konfigurasi_dan_berjumlah_100(): void
    {
        $faktor = $this->actingAs($this->user())->get('/prospek')->viewData('faktor');

        $this->assertSame(100, array_sum(array_column($faktor, 1)));
        $this->assertSame(['Kelengkapan data', 25], $faktor[0]);
    }

    public function test_riwayat_aktivitas_tampil_pada_data_prospek(): void
    {
        $p = $this->prospek();
        $this->lanjut($p, ['catatan' => 'Orang tua minta brosur']);

        $data = $this->actingAs($this->user())->get('/prospek')->viewData('prospek');

        $this->assertSame('Orang tua minta brosur', $data[0]['aktivitas'][0]['catatan']);
    }

    // --- tambah prospek manual ------------------------------------------------------------------

    public function test_tambah_prospek_membuat_aktivitas_dari_catatan_dan_menghitung_skor(): void
    {
        $admin = $this->user();

        $this->actingAs($admin)->post('/prospek', [
            'nama' => 'Calon Siswa', 'nomor_telepon' => '0812-3456-7890', 'jenjang' => 'SMP', 'program' => 'Reguler',
            'tahap' => 'dihubungi', 'sumber' => 'Instagram', 'catatan' => 'Dari pameran sekolah',
        ])->assertRedirect('/prospek')->assertSessionHasNoErrors();

        $p = Prospek::sole();
        $this->assertSame(['6281234567890', 'Reguler', $admin->id], [$p->kontak->nomor_telepon, $p->program, $p->penanggung_jawab]);
        $this->assertSame('Dari pameran sekolah', $p->aktivitas()->sole()->catatan);
        $this->assertGreaterThan(0, $p->skor);
    }
}
