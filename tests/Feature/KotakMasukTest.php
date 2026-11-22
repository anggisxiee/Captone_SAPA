<?php

namespace Tests\Feature;

use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TC02 / TC07 - Kotak masuk dan hak akses per nomor (F02, F07 / US03). */
class KotakMasukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function agen(): User
    {
        return User::factory()->create(['role' => 'Agen/CS', 'status' => 'Aktif']);
    }

    private function nomor(string $nama, string $nomor): NomorWhatsapp
    {
        return NomorWhatsapp::create(['nama' => $nama, 'nomor' => $nomor]);
    }

    private function percakapan(NomorWhatsapp $nomor, string $telepon = '6285711110001'): Percakapan
    {
        $percakapan = Percakapan::untuk($nomor, $telepon, 'Bu Ratna');
        $percakapan->update(['belum_dibalas' => true, 'pesan_terakhir_pada' => now()]);

        return $percakapan;
    }

    private function payload(Percakapan $percakapan): array
    {
        return [
            'percakapan_id' => $percakapan->id,
            'isi' => 'Halo, jadwalnya Senin dan Rabu ya.',
        ];
    }

    public function test_hanya_percakapan_dari_nomor_yang_diizinkan_tampil(): void
    {
        $user = $this->agen();
        $boleh = $this->nomor('Admisi', '6281');
        $dilarang = $this->nomor('Sales', '6282');
        $user->nomorWhatsapp()->attach([
            $boleh->id => ['hak_akses' => 'Lihat saja'],
            $dilarang->id => ['hak_akses' => 'Tidak ada'],
        ]);

        $pBoleh = $this->percakapan($boleh, '6285710001');
        $this->percakapan($dilarang, '6285710002');

        $response = $this->actingAs($user)->get(route('kotak-masuk'))->assertOk();

        $this->assertSame(
            [$pBoleh->id],
            collect($response->viewData('daftar'))->pluck('id')->all()
        );
        $this->assertSame(1, $response->viewData('jumlahBelumDibalas'));
        // Hak "Lihat saja" tidak boleh membalas.
        $this->assertSame([], $response->viewData('nomorBolehBalas'));
    }

    public function test_daftar_memuat_pesan_dan_status_dari_database(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);

        $p = $this->percakapan($nomor);
        PesanWhatsapp::create([
            'nomor_whatsapp_id' => $nomor->id,
            'percakapan_id' => $p->id,
            'kontak_nama' => 'Bu Ratna',
            'kontak_telepon' => '6285711110001',
            'pengirim' => 'pelanggan',
            'isi' => 'Ada kelas 6 SD?',
        ]);

        $response = $this->actingAs($user)->get(route('kotak-masuk'))->assertOk();
        $chat = $response->viewData('daftar')[0];

        $this->assertSame('Belum dibalas', $chat['status']);
        $this->assertSame('Ada kelas 6 SD?', $chat['cuplikan']);
        $this->assertSame(['Ada kelas 6 SD?'], array_column($chat['pesan'], 'isi'));
        $response->assertSee('Ada kelas 6 SD?', false);
    }

    public function test_hak_lihat_saja_tidak_boleh_membalas(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Lihat saja']);
        $p = $this->percakapan($nomor);

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($p))
            ->assertForbidden();

        $this->assertDatabaseCount('pesan_whatsapp', 0);
        $this->assertDatabaseCount('antrean_pesan', 0);
    }

    public function test_nomor_tanpa_akses_ditolak_saat_membalas(): void
    {
        $user = $this->agen();
        $p = $this->percakapan($this->nomor('Sales', '6282'));

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($p))
            ->assertForbidden();
    }

    public function test_hak_balas_chat_menyimpan_pesan_percakapan_dan_antrean(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);
        $p = $this->percakapan($nomor);

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($p))
            ->assertRedirect(route('kotak-masuk'));

        $p->refresh();
        $this->assertSame('Ditangani Agen', $p->status);
        $this->assertFalse($p->belum_dibalas);

        $this->assertDatabaseHas('pesan_whatsapp', [
            'percakapan_id' => $p->id,
            'pengirim' => 'agen',
            'kontak_telepon' => '6285711110001',
        ]);
        $this->assertDatabaseHas('antrean_pesan', ['nomor_whatsapp_id' => $nomor->id]);
    }

    public function test_balasan_kedua_memakai_percakapan_yang_sama(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Kelola']);
        $p = $this->percakapan($nomor);

        $this->actingAs($user);
        $this->post(route('kotak-masuk.kirim'), $this->payload($p));
        $this->post(route('kotak-masuk.kirim'), $this->payload($p));

        $this->assertSame(1, Percakapan::count());
        $this->assertSame(2, PesanWhatsapp::count());
    }

    public function test_ambil_alih_dan_selesaikan_mengubah_status_dan_tercatat(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);
        $p = $this->percakapan($nomor);

        $this->actingAs($user);

        $this->patch(route('kotak-masuk.status', $p), ['status' => 'Ditangani Agen'])
            ->assertRedirect(route('kotak-masuk'));
        $this->assertSame('Ditangani Agen', $p->fresh()->status);

        $this->patch(route('kotak-masuk.status', $p), ['status' => 'Selesai']);
        $this->assertSame('Selesai', $p->fresh()->status);

        $this->assertSame(2, \App\Models\LogAudit::where('aksi', 'Ubah status percakapan')->count());
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Kelola']);
        $p = $this->percakapan($nomor);

        $this->actingAs($user)
            ->patch(route('kotak-masuk.status', $p), ['status' => 'Terserah'])
            ->assertSessionHasErrors('status');
    }

    public function test_hak_lihat_saja_tidak_boleh_mengubah_status(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Lihat saja']);
        $p = $this->percakapan($nomor);
        $awal = $p->fresh()->status;

        $this->actingAs($user)
            ->patch(route('kotak-masuk.status', $p), ['status' => 'Selesai'])
            ->assertForbidden();

        $this->assertSame($awal, $p->fresh()->status);
    }
}
