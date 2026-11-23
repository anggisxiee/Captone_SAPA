<?php

namespace Tests\Feature;

use App\Models\ArtikelPengetahuan;
use App\Models\LogAudit;
use App\Models\PengaturanAI;
use App\Models\PesanWhatsapp;
use App\Models\User;
use App\Services\Ai\FakeAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** F13 / PB-08: Pengaturan AI dan konsol Uji Keputusan. */
class PengaturanAiTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'sk-kunci-baru-1234567890wxyz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        FakeAiClient::reset();
    }

    private function admin(string $role = 'Administrator'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Aktif']);
    }

    private function dasar(array $o = []): array
    {
        return $o + [
            'penyedia' => 'OpenAI-compatible', 'model' => 'gpt-4o-mini', 'api_key' => '', 'base_url' => 'https://ai.sumopod.com/v1',
            'instruksi_persona' => 'Ramah.', 'ambang_keyakinan' => '0.62', 'eskalasi_pelanggan_marah' => '1',
            'kata_kunci_eskalasi' => 'admin,refund', 'jeda_min' => '3', 'jeda_max' => '8', 'batas_pesan_harian' => '1500',
            'jam_mulai' => '07:00', 'jam_selesai' => '21:00', 'variasi_kalimat' => '1',
        ];
    }

    private function simpan(array $o = [])
    {
        return $this->actingAs($this->admin())->put(route('pengaturan-ai.update'), $this->dasar($o));
    }

    private function jawabanPenyedia(): array
    {
        return ['model' => 'gpt-4o-mini', 'choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]];
    }

    // --- menyimpan kunci --------------------------------------------------------------------------

    public function test_kunci_baru_diuji_ke_penyedia_lalu_disimpan_terenkripsi_dan_tidak_dicatat(): void
    {
        Http::fake(['ai.sumopod.com/*' => Http::response($this->jawabanPenyedia())]);

        $this->simpan(['api_key' => self::KUNCI, 'model' => 'gpt-4o'])
            ->assertRedirect(route('pengaturan-ai'))
            ->assertSessionHas('success')
            ->assertSessionMissing('peringatan');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ai.sumopod.com/v1/chat/completions'
            && $r->header('Authorization') === ['Bearer ' . self::KUNCI] && $r['model'] === 'gpt-4o');

        $pengaturan = PengaturanAI::find(1);
        $this->assertSame(self::KUNCI, $pengaturan->api_key, 'terbaca kembali oleh aplikasi');
        $mentah = DB::table('pengaturan_ai')->value('api_key');
        $this->assertStringNotContainsString(self::KUNCI, $mentah, 'tersimpan terenkripsi di basis data');

        $log = LogAudit::where('aksi', 'Ubah pengaturan AI')->firstOrFail();
        $this->assertTrue($log->detail['kunci_api_diganti']);
        $this->assertStringNotContainsString(self::KUNCI, json_encode($log->toArray()), 'kunci tidak boleh ada di log audit');
    }

    public function test_kunci_ditolak_penyedia_membatalkan_penyimpanan_dan_pengaturan_lama_tetap(): void
    {
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o-mini', 'base_url' => 'https://ai.sumopod.com/v1', 'api_key' => 'sk-lama', 'ambang_keyakinan' => 0.62]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid']], 401)]);

        $this->simpan(['api_key' => self::KUNCI, 'model' => 'gpt-4o', 'ambang_keyakinan' => '0.9'])
            ->assertSessionHasErrors(['api_key' => 'Kunci API ditolak penyedia']);

        $p = PengaturanAI::find(1);
        $this->assertSame(['sk-lama', 'gpt-4o-mini'], [$p->api_key, $p->model], 'tidak ada yang berubah');
        $this->assertSame(0, LogAudit::where('aksi', 'Ubah pengaturan AI')->count());
    }

    public function test_penyedia_tidak_dapat_dijangkau_tetap_menyimpan_dengan_peringatan(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->simpan(['api_key' => self::KUNCI])
            ->assertRedirect(route('pengaturan-ai'))
            ->assertSessionHas('success')
            ->assertSessionHas('peringatan', fn ($p) => str_contains($p, 'belum dapat diuji'));

        $this->assertSame(self::KUNCI, PengaturanAI::find(1)->api_key);
    }

    public function test_tanpa_kunci_baru_tidak_ada_panggilan_dan_kunci_lama_tetap(): void
    {
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o-mini', 'base_url' => 'https://ai.sumopod.com/v1', 'api_key' => 'sk-lama', 'ambang_keyakinan' => 0.62]);
        Http::fake();

        $this->simpan(['ambang_keyakinan' => '0.8'])->assertRedirect()->assertSessionHas('success');

        Http::assertNothingSent();
        $this->assertSame(['sk-lama', '0.80'], [PengaturanAI::find(1)->api_key, (string) PengaturanAI::find(1)->ambang_keyakinan]);
        $log = LogAudit::where('aksi', 'Ubah pengaturan AI')->firstOrFail();
        $this->assertFalse($log->detail['kunci_api_diganti']);
        $this->assertContains('ambang_keyakinan', $log->detail['diubah']);
    }

    // --- keamanan alamat penyedia -------------------------------------------------------------------

    public function test_mengganti_alamat_tanpa_memasukkan_ulang_kunci_ditolak_agar_kunci_lama_tidak_bocor(): void
    {
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o-mini', 'base_url' => 'https://ai.sumopod.com/v1', 'api_key' => 'sk-lama', 'ambang_keyakinan' => 0.62]);
        Http::fake();

        $this->simpan(['base_url' => 'https://api.penyedia-lain.example/v1'])
            ->assertSessionHasErrors(['api_key' => 'Alamat penyedia berubah: masukkan kunci API untuk penyedia ini.']);

        Http::assertNothingSent();
        $this->assertSame('https://ai.sumopod.com/v1', PengaturanAI::find(1)->base_url);
    }

    public function test_mengganti_alamat_dengan_kunci_baru_menguji_ke_alamat_baru_saja(): void
    {
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o-mini', 'base_url' => 'https://ai.sumopod.com/v1', 'api_key' => 'sk-lama', 'ambang_keyakinan' => 0.62]);
        Http::fake(['api.penyedia-lain.example/*' => Http::response($this->jawabanPenyedia())]);

        $this->simpan(['base_url' => 'https://api.penyedia-lain.example/v1', 'api_key' => self::KUNCI])->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->header('Authorization') === ['Bearer ' . self::KUNCI]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sumopod'));
        $this->assertSame('https://api.penyedia-lain.example/v1', PengaturanAI::find(1)->base_url);
    }

    public function test_alamat_internal_dan_http_polos_ditolak_di_luar_pengembangan(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // Di lingkungan produksi CSRF aktif; yang diuji di sini validasi alamat, bukan CSRF.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        Http::fake();
        $lolos = [];

        foreach (['http://ai.example.com/v1', 'https://127.0.0.1:3001/v1', 'https://localhost/v1', 'https://10.0.0.5/v1',
            'https://192.168.1.10/v1', 'https://169.254.169.254/latest', 'https://gateway.internal/v1', 'https://nas.local/v1'] as $alamat) {
            $r = $this->simpan(['base_url' => $alamat, 'api_key' => self::KUNCI]);
            if (!session('errors') || !session('errors')->has('base_url')) {
                $lolos[] = $alamat;
            }
        }

        $this->assertSame([], $lolos, 'Alamat berikut seharusnya ditolak: ' . implode(', ', $lolos));
        Http::assertNothingSent();

        Http::fake(['ai.sumopod.com/*' => Http::response($this->jawabanPenyedia())]);
        $this->simpan(['api_key' => self::KUNCI])->assertSessionDoesntHaveErrors();
    }

    // --- BR13: nilai batas ------------------------------------------------------------------------------

    public function test_nilai_batas_nol_negatif_atau_tidak_wajar_ditolak(): void
    {
        foreach ([
            ['jeda_min', '0'], ['jeda_min', '-2'], ['jeda_max', '0'], ['ambang_keyakinan', '0'], ['ambang_keyakinan', '-0.1'],
            ['ambang_keyakinan', '1.5'], ['batas_pesan_harian', '0'], ['batas_pesan_harian', '-10'],
        ] as [$kolom, $nilai]) {
            $this->simpan([$kolom => $nilai])->assertSessionHasErrors($kolom);
        }

        $this->simpan(['jeda_min' => '10', 'jeda_max' => '5'])->assertSessionHasErrors('jeda_max');
        $this->assertSame(0, LogAudit::where('aksi', 'Ubah pengaturan AI')->count());
    }

    // --- konsol Uji Keputusan ------------------------------------------------------------------------------

    private function artikel(): void
    {
        ArtikelPengetahuan::create(['judul' => 'Jadwal dan biaya SMP kelas 8', 'kategori' => 'jadwal', 'aktif' => true,
            'isi' => 'Reguler Matematika SMP kelas 8 Selasa dan Kamis pukul 16.00. Biaya mulai Rp450.000 per bulan.']);
    }

    public function test_uji_keputusan_mengembalikan_jejak_lengkap_dan_tidak_mengirim_apa_pun(): void
    {
        $this->artikel();
        FakeAiClient::antre(json_encode(['jawaban' => 'Biaya mulai Rp450.000 per bulan.', 'sumber' => ['KB-001'], 'keyakinan' => 0.9, 'cukup' => true, 'maksud' => 'tanya_biaya']));

        $this->actingAs($this->admin())
            ->postJson(route('pengaturan-ai.uji'), ['pesan' => 'berapa biaya bimbel SMP kelas 8?'])
            ->assertOk()
            ->assertJsonPath('keputusan', 'kirim')
            ->assertJsonPath('mode', 'ai')
            ->assertJsonPath('maksud', 'tanya_biaya')
            ->assertJsonPath('sumber.0.kode', 'KB-001')
            ->assertJsonPath('artikel.0.dipakai', true)
            ->assertJsonPath('ambang', 0.62)
            ->assertJsonPath('ambang_relevansi', 0.7)
            ->assertJsonPath('jawaban_ditolak', null)
            ->assertJsonPath('pesan_pelanggan', fn ($t) => str_contains($t, 'Rp450.000') && str_contains($t, 'Sumber: Jadwal dan biaya SMP kelas 8'));

        $this->assertSame(0, PesanWhatsapp::count(), 'uji tidak membuat pesan');
        $this->assertSame(0, DB::table('antrean_pesan')->count());
    }

    public function test_uji_keputusan_eskalasi_menampilkan_pesan_srs_dan_alasan_tanpa_memanggil_ai(): void
    {
        $this->artikel();

        $this->actingAs($this->admin())
            ->postJson(route('pengaturan-ai.uji'), ['pesan' => 'Saya mau refund'])
            ->assertOk()
            ->assertJsonPath('eskalasi', true)
            ->assertJsonPath('kata_kunci', 'refund')
            ->assertJsonPath('alasan', 'Pesan memuat kata kunci eskalasi')
            ->assertJsonPath('pesan_pelanggan', 'Pertanyaan Anda kami teruskan ke admin.')
            ->assertJsonPath('token', null);

        $this->assertSame([], FakeAiClient::$riwayat);
    }

    public function test_uji_keputusan_keyakinan_rendah_menampilkan_jawaban_yang_ditolak(): void
    {
        $this->artikel();
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'm', 'ambang_keyakinan' => 0.99]);
        FakeAiClient::antre(json_encode(['jawaban' => 'Biaya Rp450.000.', 'keyakinan' => 0.5, 'cukup' => true]));

        $this->actingAs($this->admin())
            ->postJson(route('pengaturan-ai.uji'), ['pesan' => 'berapa biaya bimbel SMP kelas 8?'])
            ->assertJsonPath('eskalasi', true)
            ->assertJsonPath('jawaban_ditolak', fn ($t) => str_contains($t, 'Rp450.000'))
            ->assertJsonPath('pesan_pelanggan', 'Pertanyaan Anda kami teruskan ke admin.');
    }

    public function test_uji_keputusan_memvalidasi_pesan_dan_hanya_untuk_supervisor_dan_admin(): void
    {
        $this->actingAs($this->admin('Supervisor'))->postJson(route('pengaturan-ai.uji'), ['pesan' => ''])->assertUnprocessable();
        $this->actingAs($this->admin('Supervisor'))->postJson(route('pengaturan-ai.uji'), ['pesan' => str_repeat('a', 2001)])->assertUnprocessable();
        $this->actingAs($this->admin('Agen/CS'))->postJson(route('pengaturan-ai.uji'), ['pesan' => 'halo'])->assertRedirect(route('dasbor'));
    }

    // --- halaman ---------------------------------------------------------------------------------------------

    public function test_halaman_menampilkan_kunci_yang_dipakai_dari_env_dalam_bentuk_samar(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI]);

        $html = $this->actingAs($this->admin())->get(route('pengaturan-ai'))->assertOk()->getContent();

        $this->assertStringContainsString('sk-…wxyz', $html);
        $this->assertStringContainsString('dari .env', $html);
        $this->assertStringNotContainsString(self::KUNCI, $html);
        $this->assertStringContainsString('https://ai.sumopod.com/v1', $html, 'alamat bawaan baris pertama = SumoPod');
    }

    public function test_halaman_tanpa_kunci_memberi_tahu_mode_aturan_bawaan_dipakai(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => null]);

        $this->actingAs($this->admin())->get(route('pengaturan-ai'))->assertOk()->assertSee('mode aturan bawaan');
    }

    public function test_notifikasi_sukses_tampil_sekali(): void
    {
        Http::fake();

        $html = $this->actingAs($this->admin())->followingRedirects()
            ->put(route('pengaturan-ai.update'), $this->dasar())->getContent();

        $this->assertSame(1, substr_count($html, 'Pengaturan AI berhasil disimpan.'));
    }

    public function test_reset_mengembalikan_nilai_bawaan_menghapus_kunci_dan_tercatat(): void
    {
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o', 'base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-lama', 'ambang_keyakinan' => 0.9]);

        $this->actingAs($this->admin())->post(route('pengaturan-ai.reset'))->assertRedirect(route('pengaturan-ai'));

        $p = PengaturanAI::find(1);
        $this->assertSame([null, 'https://ai.sumopod.com/v1', 'gpt-4o-mini'], [$p->api_key, $p->base_url, $p->model]);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'Reset pengaturan AI']);
    }
}
