<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\NomorWhatsapp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TC11 - Menautkan nomor WhatsApp lewat QR (F11 / US02). */
class NomorWhatsappSesiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Administrator', 'status' => 'Aktif']);
    }

    private function nomor(array $o = []): NomorWhatsapp
    {
        return NomorWhatsapp::create($o + ['nama' => 'Admisi', 'nomor' => '6281200000001']);
    }

    public function test_tambah_nomor_menyimpan_tujuan_dan_menyeragamkan_format(): void
    {
        $this->actingAs($this->admin())
            ->post(route('nomor-whatsapp.store'), [
                'nama' => 'Sales', 'nomor' => '0812-0000-0002', 'tujuan' => 'Sales',
            ])
            ->assertRedirect(route('nomor-whatsapp'))
            ->assertSessionHas('tautkan_id');

        $this->assertDatabaseHas('nomor_whatsapp', [
            'nomor' => '6281200000002',
            'tujuan' => 'Sales',
            'status_sesi' => 'Belum tertaut',
        ]);
    }

    public function test_nomor_duplikat_beda_format_ditolak(): void
    {
        $this->nomor();

        $this->actingAs($this->admin())
            ->post(route('nomor-whatsapp.store'), [
                'nama' => 'Dobel', 'nomor' => '081200000001', 'tujuan' => 'Admin',
            ])
            ->assertSessionHasErrors('nomor');
    }

    public function test_tautkan_membuat_qr_dan_status_menunggu(): void
    {
        $nomor = $this->nomor();

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertOk()
            ->assertJson(['status_sesi' => 'Menunggu QR', 'kedaluwarsa' => false])
            ->assertJsonPath('sisa_detik', fn ($v) => $v > 55 && $v <= 60)
            ->assertJsonPath('qr_svg', fn ($v) => str_contains($v, '<svg'));

        $this->assertDatabaseHas('log_audit', ['aksi' => 'Mulai tautan nomor']);
    }

    public function test_pindai_qr_menghubungkan_nomor(): void
    {
        $nomor = $this->nomor();
        $this->actingAs($this->admin());

        $this->postJson(route('nomor-whatsapp.tautkan', $nomor));
        $this->postJson(route('nomor-whatsapp.simulasi-pindai', $nomor))
            ->assertOk()
            ->assertJson(['status_sesi' => 'Tersambung', 'qr_svg' => null]);

        $nomor->refresh();
        $this->assertNull($nomor->qr_token);
        $this->assertNotNull($nomor->terakhir_aktif_pada);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Nomor tersambung']);
    }

    public function test_qr_kedaluwarsa_terdeteksi_dan_dapat_dibuat_ulang(): void
    {
        $nomor = $this->nomor();
        $this->actingAs($this->admin());
        $this->postJson(route('nomor-whatsapp.tautkan', $nomor));
        $tokenLama = $nomor->fresh()->qr_token;

        $this->travel(61)->seconds();

        $this->getJson(route('nomor-whatsapp.sesi', $nomor))
            ->assertJson(['kedaluwarsa' => true, 'sisa_detik' => 0]);

        // QR kedaluwarsa tidak bisa dipindai.
        $this->postJson(route('nomor-whatsapp.simulasi-pindai', $nomor))->assertUnprocessable();

        $this->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertJson(['kedaluwarsa' => false]);
        $this->assertNotSame($tokenLama, $nomor->fresh()->qr_token);
    }

    public function test_putuskan_mengubah_status_dan_tidak_memengaruhi_nomor_lain(): void
    {
        $a = $this->nomor(['status_sesi' => 'Tersambung']);
        $b = $this->nomor(['nama' => 'Sales', 'nomor' => '6281200000002', 'status_sesi' => 'Tersambung']);

        $this->actingAs($this->admin())
            ->post(route('nomor-whatsapp.putuskan', $a))
            ->assertRedirect(route('nomor-whatsapp'));

        $this->assertSame('Terputus', $a->fresh()->status_sesi);
        $this->assertSame('Tersambung', $b->fresh()->status_sesi);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Nomor terputus']);
    }

    public function test_nomor_nonaktif_tidak_dapat_ditautkan(): void
    {
        $nomor = $this->nomor(['status' => 'Nonaktif']);

        $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->assertUnprocessable();
    }

    public function test_supervisor_dan_agen_tidak_boleh_menautkan(): void
    {
        $nomor = $this->nomor();

        foreach (['Supervisor', 'Agen/CS'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'Aktif']);

            $this->actingAs($user)
                ->post(route('nomor-whatsapp.tautkan', $nomor))
                ->assertRedirect(route('dasbor'));
        }

        $this->assertSame('Belum tertaut', $nomor->fresh()->status_sesi);
    }

    public function test_halaman_menampilkan_status_sesi_dan_tombol_tautkan_untuk_admin(): void
    {
        $this->nomor();

        $this->actingAs($this->admin())
            ->get(route('nomor-whatsapp'))
            ->assertOk()
            ->assertSee('Belum tertaut')
            ->assertSee('Simulasikan pindai');
    }

    public function test_supervisor_tidak_melihat_tombol_tautkan_pada_kartu(): void
    {
        $this->nomor();
        $sup = User::factory()->create(['role' => 'Supervisor', 'status' => 'Aktif']);

        $this->actingAs($sup)
            ->get(route('nomor-whatsapp'))
            ->assertOk()
            ->assertDontSee('Simulasikan pindai')
            ->assertDontSee('Tautkan ulang');
    }

    public function test_qr_dirender_oleh_pustaka_asli_bukan_placeholder(): void
    {
        $nomor = $this->nomor();

        $svg = $this->actingAs($this->admin())
            ->postJson(route('nomor-whatsapp.tautkan', $nomor))
            ->json('qr_svg');

        $this->assertStringNotContainsString('Pratinjau QR', $svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    /**
     * Atribut @click yang dirender harus utuh. Dulu @js di dalam atribut
     * berkutip tunggal memotong atribut di "JSON.parse(" sehingga tombol
     * Kelola, Hapus, dan Tautkan tidak berfungsi.
     */
    public function test_atribut_klik_pada_kartu_nomor_utuh(): void
    {
        $this->nomor(['nama' => 'Belum Tertaut']);

        $html = $this->actingAs($this->admin())
            ->get(route('nomor-whatsapp'))
            ->assertOk()
            ->getContent();

        foreach (['mulaiTautan', 'bukaEdit', 'bukaHapus'] as $fungsi) {
            $this->assertMatchesRegularExpression(
                '/@click="' . $fungsi . '\\(JSON\\.parse\\(.+?\\)\\)"/',
                $html,
                "Tombol {$fungsi} tidak dirender dengan benar"
            );
        }

        // Tidak ada atribut @click berkutip tunggal yang memuat JSON.parse.
        $this->assertDoesNotMatchRegularExpression("/@click='[^']*JSON\\.parse/", $html);
    }
}
