<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TC01 - Login pengguna (F01 / US01). */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role = 'Agen/CS', string $status = 'Aktif'): User
    {
        return User::factory()->create([
            'email' => 'uji@asentra.sch.id',
            'password' => 'rahasia123',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function masuk(string $password = 'rahasia123')
    {
        return $this->post(route('login.post'), [
            'email' => 'uji@asentra.sch.id',
            'password' => $password,
        ]);
    }

    public function test_agen_masuk_ke_kotak_masuk_dan_tercatat_di_log_audit(): void
    {
        $user = $this->buatUser('Agen/CS');

        $this->masuk()->assertRedirect(route('kotak-masuk'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => 'Login']);
    }

    public function test_supervisor_dan_administrator_masuk_ke_dasbor(): void
    {
        foreach (['Supervisor', 'Administrator'] as $role) {
            User::query()->delete();
            $this->buatUser($role);

            $this->masuk()->assertRedirect(route('dasbor'));
            auth()->logout();
        }
    }

    public function test_password_salah_ditolak(): void
    {
        $this->buatUser();

        $this->masuk('salah')->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $this->assertSame(1, LogAudit::where('aksi', 'Login gagal')->count());
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->buatUser('Agen/CS', 'Nonaktif');

        $this->masuk()->assertSessionHasErrors([
            'email' => 'Akun Anda dinonaktifkan, hubungi Administrator.',
        ]);

        $this->assertGuest();
    }

    public function test_login_ditahan_setelah_lima_kali_gagal(): void
    {
        $this->buatUser();

        for ($i = 0; $i < 5; $i++) {
            $this->masuk('salah');
        }

        // Percobaan keenam ditahan walau password benar.
        $this->masuk('rahasia123')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
    }

    public function test_logout_mengakhiri_sesi_dan_tercatat(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => 'Logout']);
    }

    public function test_akun_yang_dinonaktifkan_saat_login_dikeluarkan(): void
    {
        $user = $this->buatUser();
        $this->actingAs($user);
        $user->update(['status' => 'Nonaktif']);

        $this->get(route('dasbor'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
