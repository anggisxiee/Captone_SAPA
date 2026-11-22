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

    private function payload(NomorWhatsapp $nomor): array
    {
        return [
            'nomor_whatsapp_id' => $nomor->id,
            'kontak_nama' => 'Bu Ratna',
            'kontak_telepon' => '6285711110001',
            'isi' => 'Halo, jadwalnya Senin dan Rabu ya.',
        ];
    }

    public function test_hanya_pesan_dari_nomor_yang_diizinkan_tampil(): void
    {
        $user = $this->agen();
        $boleh = $this->nomor('Admisi', '6281');
        $dilarang = $this->nomor('Sales', '6282');
        $user->nomorWhatsapp()->attach([
            $boleh->id => ['hak_akses' => 'Lihat saja'],
            $dilarang->id => ['hak_akses' => 'Tidak ada'],
        ]);

        foreach ([$boleh, $dilarang] as $n) {
            PesanWhatsapp::create([
                'nomor_whatsapp_id' => $n->id,
                'kontak_nama' => 'Pelanggan ' . $n->nama,
                'kontak_telepon' => '628570' . $n->id,
                'pengirim' => 'pelanggan',
                'isi' => 'Pesan untuk ' . $n->nama,
            ]);
        }

        $response = $this->actingAs($user)->get(route('kotak-masuk'))->assertOk();

        // Diperiksa dari data controller karena view masih memakai data dummy.
        $isi = $response->viewData('pesanTersimpan')->pluck('isi')->all();
        $this->assertSame(['Pesan untuk Admisi'], $isi);
        $this->assertSame([$boleh->id], $response->viewData('nomorWhatsapp')->pluck('id')->all());
    }

    public function test_hak_lihat_saja_tidak_boleh_membalas(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Lihat saja']);

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($nomor))
            ->assertForbidden();

        $this->assertDatabaseCount('pesan_whatsapp', 0);
        $this->assertDatabaseCount('antrean_pesan', 0);
    }

    public function test_nomor_tanpa_akses_ditolak_saat_membalas(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Sales', '6282');

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($nomor))
            ->assertForbidden();
    }

    public function test_hak_balas_chat_menyimpan_pesan_percakapan_dan_antrean(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Balas chat']);

        $this->actingAs($user)
            ->post(route('kotak-masuk.kirim'), $this->payload($nomor))
            ->assertRedirect(route('kotak-masuk'));

        $percakapan = Percakapan::firstOrFail();
        $this->assertSame('Ditangani Agen', $percakapan->status);
        $this->assertFalse($percakapan->belum_dibalas);

        $this->assertDatabaseHas('kontak', ['nomor_telepon' => '6285711110001']);
        $this->assertDatabaseHas('pesan_whatsapp', [
            'percakapan_id' => $percakapan->id,
            'pengirim' => 'agen',
        ]);
        $this->assertDatabaseHas('antrean_pesan', ['nomor_whatsapp_id' => $nomor->id]);
    }

    public function test_balasan_kedua_memakai_percakapan_yang_sama(): void
    {
        $user = $this->agen();
        $nomor = $this->nomor('Admisi', '6281');
        $user->nomorWhatsapp()->attach($nomor->id, ['hak_akses' => 'Kelola']);

        $this->actingAs($user);
        $this->post(route('kotak-masuk.kirim'), $this->payload($nomor));
        $this->post(route('kotak-masuk.kirim'), $this->payload($nomor));

        $this->assertSame(1, Percakapan::count());
        $this->assertSame(2, PesanWhatsapp::count());
    }
}
