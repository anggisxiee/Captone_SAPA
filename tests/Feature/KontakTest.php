<?php

namespace Tests\Feature;

use App\Models\Kontak;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\Prospek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F03/F09: halaman Kontak dari basis data, cari, filter, ubah, ekspor, dan hak akses (BR03/BR04). */
class KontakTest extends TestCase
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

    private function kontak(string $telepon, array $o = [], ?NomorWhatsapp $nomor = null): Kontak
    {
        $k = Kontak::create($o + ['nomor_telepon' => $telepon]);
        if ($nomor) {
            Percakapan::create(['nomor_whatsapp_id' => $nomor->id, 'kontak_id' => $k->id, 'pesan_terakhir_pada' => now()]);
        }

        return $k;
    }

    private function nomor(string $nomor = '6281200000001'): NomorWhatsapp
    {
        return NomorWhatsapp::create(['label' => 'N' . $nomor, 'nomor' => $nomor, 'status_sesi' => 'Tersambung']);
    }

    public function test_menampilkan_kontak_dari_database_bukan_data_statis(): void
    {
        $this->kontak('6285711110001', ['nama' => 'Bu Ratna', 'jenjang' => 'SMP', 'kelas' => '8', 'mapel' => 'Matematika']);

        $html = $this->actingAs($this->user())->get('/kontak')->assertOk()->getContent();

        $this->assertStringContainsString('Bu Ratna', $html);
        $this->assertStringContainsString('SMP Kelas 8', $html);
        $this->assertStringNotContainsString('Rina Kusuma', $html); // data statis lama
        $this->assertStringContainsString('1 calon siswa', $html);
    }

    public function test_pencarian_nama_dan_nomor_serta_filter_jenjang_dan_sumber(): void
    {
        $this->kontak('6285711110001', ['nama' => 'Bu Ratna', 'jenjang' => 'SMP', 'sumber' => 'WhatsApp']);
        $this->kontak('6285711110002', ['nama' => 'Pak Anwar', 'jenjang' => 'SMA', 'sumber' => 'Situs web']);
        $a = $this->actingAs($this->user());

        $this->assertSame(['Pak Anwar'], $a->get('/kontak?q=anwar')->viewData('kontak')->pluck('nama')->all());
        $this->assertSame(['Bu Ratna'], $a->get('/kontak?q=0857-1111-0001')->viewData('kontak')->pluck('nama')->all());
        $this->assertSame(['Pak Anwar'], $a->get('/kontak?jenjang=SMA')->viewData('kontak')->pluck('nama')->all());
        $this->assertSame(['Bu Ratna'], $a->get('/kontak?sumber=WhatsApp')->viewData('kontak')->pluck('nama')->all());
        $this->assertCount(0, $a->get('/kontak?q=anwar&jenjang=SMP')->viewData('kontak'));
    }

    public function test_karakter_like_pada_pencarian_tidak_menjadi_wildcard(): void
    {
        $this->kontak('6285711110001', ['nama' => 'Bu Ratna']);

        $this->assertCount(0, $this->actingAs($this->user())->get('/kontak?q=%25')->viewData('kontak'));
    }

    public function test_mengubah_kontak_menyeragamkan_nomor_menyelaraskan_prospek_dan_dicatat(): void
    {
        $k = $this->kontak('6285711110001', ['nama' => 'Bu Ratna']);
        $p = Prospek::create(['kontak_id' => $k->id, 'tahap' => 'baru', 'skor' => 0]);

        $this->actingAs($this->user())->put("/kontak/{$k->id}", [
            'nama' => ' Ratna Dewi ', 'nomor_telepon' => '0857-1111-9999', 'jenjang' => 'SMP', 'kelas' => '8', 'mapel' => '', 'kota' => 'Sragen', 'sumber' => 'Rekomendasi',
            'f_jenjang' => 'SMA',
        ])->assertRedirect('/kontak?jenjang=SMA')->assertSessionHasNoErrors();

        $k->refresh();
        $this->assertSame(['Ratna Dewi', '6285711119999', 'SMP', null, 'Sragen'], [$k->nama, $k->nomor_telepon, $k->jenjang, $k->mapel, $k->kota]);
        $this->assertGreaterThan(0, $p->fresh()->skor); // BR06: skor dihitung ulang saat data kontak berubah
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Ubah kontak']);
    }

    public function test_br04_nomor_tidak_valid_atau_duplikat_ditolak(): void
    {
        $this->kontak('6285711110002', ['nama' => 'Pak Anwar']);
        $k = $this->kontak('6285711110001', ['nama' => 'Bu Ratna']);
        $a = $this->actingAs($this->user());

        $a->put("/kontak/{$k->id}", ['nomor_telepon' => '12345'])->assertSessionHasErrors('nomor_telepon');
        $a->put("/kontak/{$k->id}", ['nomor_telepon' => '085711110002'])->assertSessionHasErrors('nomor_telepon');
        $a->put("/kontak/{$k->id}", ['nomor_telepon' => '085711110001', 'nama' => 'Ratna'])->assertSessionHasNoErrors(); // nomor sendiri boleh

        $this->assertSame('6285711110001', $k->fresh()->nomor_telepon);
    }

    public function test_br03_agen_hanya_melihat_dan_mengubah_kontak_pada_nomor_yang_ditugaskan(): void
    {
        $milik = $this->nomor('6281200000001');
        $lain = $this->nomor('6281200000002');
        $saya = $this->kontak('6285711110001', ['nama' => 'Milik Saya'], $milik);
        $orang = $this->kontak('6285711110002', ['nama' => 'Milik Lain'], $lain);
        $agen = $this->user('Agen/CS');
        $agen->nomorWhatsapp()->attach($milik->id, ['hak_akses' => 'Balas chat']);
        $a = $this->actingAs($agen);

        $this->assertSame(['Milik Saya'], $a->get('/kontak')->viewData('kontak')->pluck('nama')->all());
        $a->put("/kontak/{$orang->id}", ['nomor_telepon' => '6285711110002', 'nama' => 'Dibajak'])->assertForbidden();
        $a->put("/kontak/{$saya->id}", ['nomor_telepon' => '6285711110001', 'nama' => 'Diubah'])->assertSessionHasNoErrors();

        $this->assertSame('Milik Lain', $orang->fresh()->nama);
        $this->assertSame('Diubah', $saya->fresh()->nama);
    }

    public function test_agen_lihat_saja_tidak_boleh_mengubah(): void
    {
        $nomor = $this->nomor();
        $k = $this->kontak('6285711110001', ['nama' => 'Bu Ratna'], $nomor);
        $agen = $this->user('Agen/CS');
        $agen->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Lihat saja']);

        $this->actingAs($agen)->put("/kontak/{$k->id}", ['nomor_telepon' => '6285711110001'])->assertForbidden();
    }

    public function test_admin_menghapus_kontak_beserta_percakapan_pesan_dan_prospeknya(): void
    {
        $nomor = $this->nomor();
        $k = $this->kontak('6285711110001', ['nama' => 'Bu Ratna'], $nomor);
        $lain = $this->kontak('6285711110002', ['nama' => 'Pak Anwar'], $nomor);
        $p = Percakapan::where('kontak_id', $k->id)->first();
        \App\Models\PesanWhatsapp::create(['percakapan_id' => $p->id, 'arah' => 'masuk', 'jenis_pengirim' => 'pelanggan', 'isi' => 'Halo', 'status' => 'diterima']);
        Prospek::create(['kontak_id' => $k->id, 'tahap' => 'baru']);
        Prospek::create(['kontak_id' => $lain->id, 'tahap' => 'baru']);

        $this->actingAs($this->user())->delete("/kontak/{$k->id}")->assertRedirect('/kontak');

        $this->assertDatabaseMissing('kontak', ['id' => $k->id]);
        $this->assertSame(0, Percakapan::where('kontak_id', $k->id)->count());
        $this->assertSame(0, \App\Models\PesanWhatsapp::count());
        $this->assertSame([$lain->id], Prospek::pluck('kontak_id')->all());
        $this->assertSame(1, Kontak::count());
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Hapus kontak']);
    }

    public function test_hanya_administrator_boleh_menghapus_kontak(): void
    {
        $k = $this->kontak('6285711110001');

        foreach (['Supervisor', 'Agen/CS'] as $role) {
            $this->actingAs($this->user($role))->delete("/kontak/{$k->id}")->assertRedirect('/dasbor');
        }
        $this->assertDatabaseHas('kontak', ['id' => $k->id]);
    }

    public function test_ekspor_csv_mengikuti_filter_dan_menetralkan_rumus(): void
    {
        $this->kontak('6285711110001', ['nama' => '=HYPERLINK("http://x")', 'jenjang' => 'SMP']);
        $this->kontak('6285711110002', ['nama' => 'Pak Anwar', 'jenjang' => 'SMA']);

        $csv = $this->actingAs($this->user())->get('/kontak/ekspor?jenjang=SMP')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Pak Anwar', $csv);
        $this->assertStringContainsString('Nomor WhatsApp', $csv);
    }
}
