<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tes asap: semua halaman terbuka sesuai role setelah login memakai Auth Laravel. */
class HalamanTest extends TestCase
{
    use RefreshDatabase;

    private const UMUM = ['dasbor', 'prospek', 'kontak', 'basis-pengetahuan', 'kotak-masuk'];

    private const SUPERVISOR = [
        'nomor-whatsapp', 'pengaturan-ai', 'antrean-risiko',
        'riwayat-antrean', 'log-audit',
    ];

    private const ADMIN = ['tim-akses'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Aktif']);
    }

    public function test_tamu_diarahkan_ke_login_dari_semua_halaman(): void
    {
        foreach ([...self::UMUM, ...self::SUPERVISOR, ...self::ADMIN] as $rute) {
            $this->get(route($rute))->assertRedirect(route('login'));
        }
    }

    public function test_administrator_dapat_membuka_semua_halaman(): void
    {
        $this->actingAs($this->user('Administrator'));

        foreach ([...self::UMUM, ...self::SUPERVISOR, ...self::ADMIN] as $rute) {
            $this->get(route($rute))->assertOk();
        }
    }

    public function test_supervisor_tidak_dapat_membuka_tim_akses(): void
    {
        $this->actingAs($this->user('Supervisor'));

        foreach ([...self::UMUM, ...self::SUPERVISOR] as $rute) {
            $this->get(route($rute))->assertOk();
        }

        $this->get(route('tim-akses'))->assertRedirect(route('dasbor'));
    }

    public function test_agen_hanya_dapat_membuka_halaman_umum(): void
    {
        $this->actingAs($this->user('Agen/CS'));

        foreach (self::UMUM as $rute) {
            $this->get(route($rute))->assertOk();
        }

        foreach ([...self::SUPERVISOR, ...self::ADMIN] as $rute) {
            $this->get(route($rute))->assertRedirect(route('dasbor'));
        }
    }
}
