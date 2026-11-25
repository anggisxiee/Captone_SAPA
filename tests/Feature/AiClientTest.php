<?php

namespace Tests\Feature;

use App\Exceptions\AiException;
use App\Exceptions\AiTidakTersediaException;
use App\Exceptions\KunciApiDitolakException;
use App\Models\PengaturanAI;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiFactory;
use App\Services\Ai\FakeAiClient;
use App\Services\Ai\KonfigurasiAi;
use App\Services\Ai\OpenAiCompatibleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Klien AI kompatibel OpenAI (SumoPod) dan pemilihan konfigurasinya. Penyedia dipalsukan dengan Http::fake. */
class AiClientTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'sk-rahasia-1234567890abcdef';
    private const BASE = 'https://ai.sumopod.com/v1';

    protected function setUp(): void
    {
        parent::setUp();
        FakeAiClient::reset();
    }

    private function klien(string $base = self::BASE): OpenAiCompatibleClient
    {
        return new OpenAiCompatibleClient($base, self::KUNCI, 'gpt-4o-mini', 10);
    }

    private function jawaban(string $teks = 'Halo!'): array
    {
        return [
            'model' => 'gpt-4o-mini-2024-07-18',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $teks]]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
        ];
    }

    // --- chat ---------------------------------------------------------------------------

    public function test_chat_mengirim_permintaan_sesuai_dokumentasi_sumopod_dan_membaca_jawaban(): void
    {
        Http::fake(['ai.sumopod.com/*' => Http::response($this->jawaban('Selamat siang'))]);

        $hasil = $this->klien()->chat([['role' => 'user', 'content' => 'Halo']], ['max_tokens' => 150, 'temperature' => 0.7]);

        $this->assertSame('Selamat siang', $hasil->teks);
        $this->assertSame('gpt-4o-mini-2024-07-18', $hasil->model);
        $this->assertSame([12, 3], [$hasil->tokenMasuk, $hasil->tokenKeluar]);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://ai.sumopod.com/v1/chat/completions'
            && $r->header('Authorization') === ['Bearer ' . self::KUNCI]
            && $r['model'] === 'gpt-4o-mini'
            && $r['messages'] === [['role' => 'user', 'content' => 'Halo']]
            && $r['max_tokens'] === 150
            && $r['temperature'] === 0.7
            && !isset($r['response_format']));
    }

    public function test_opsi_json_meminta_keluaran_json(): void
    {
        Http::fake(['*' => Http::response($this->jawaban('{"maksud":"tanya_biaya"}'))]);

        $this->klien()->chat([['role' => 'user', 'content' => 'x']], ['json' => true]);

        Http::assertSent(fn (Request $r) => $r['response_format'] === ['type' => 'json_object']);
    }

    public function test_alamat_dasar_dengan_garis_miring_akhir_dirapikan(): void
    {
        Http::fake(['*' => Http::response($this->jawaban())]);

        $this->klien('https://ai.sumopod.com/v1/')->chat([['role' => 'user', 'content' => 'x']]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ai.sumopod.com/v1/chat/completions');
    }

    // --- galat --------------------------------------------------------------------------

    public function test_kunci_ditolak_memberi_pesan_srs_dan_tidak_membocorkan_kunci(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key sk-rahasia']], 401)]);

        try {
            $this->klien()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Seharusnya ditolak');
        } catch (KunciApiDitolakException $e) {
            $this->assertSame('Kunci API ditolak penyedia', $e->getMessage());
            $this->assertStringNotContainsString(self::KUNCI, $e->getMessage());
        }
    }

    public function test_timeout_dan_jaringan_mati_berarti_tidak_tersedia_untuk_mode_aturan_bawaan(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(AiTidakTersediaException::class);
        $this->klien()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_anggaran_habis_gangguan_penyedia_dianggap_tidak_tersedia(): void
    {
        foreach ([402, 429, 500, 503] as $status) {
            Http::fake(['*' => Http::response(['error' => ['message' => 'x']], $status)]);

            try {
                $this->klien()->chat([['role' => 'user', 'content' => 'x']]);
                $this->fail("HTTP {$status} seharusnya gagal");
            } catch (AiTidakTersediaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_permintaan_ditolak_penyedia_memakai_pesan_penyedia_dan_bukan_tidak_tersedia(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'model tidak ada']], 400)]);

        try {
            $this->klien()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail();
        } catch (AiException $e) {
            $this->assertNotInstanceOf(AiTidakTersediaException::class, $e);
            $this->assertStringContainsString('model tidak ada', $e->getMessage());
        }
    }

    public function test_jawaban_kosong_atau_bentuk_tak_dikenal_ditolak(): void
    {
        foreach ([['choices' => []], $this->jawaban('   '), ['pesan' => 'aneh']] as $i => $badan) {
            Http::fake(['*' => Http::response($badan)]);

            try {
                $this->klien()->chat([['role' => 'user', 'content' => 'x']]);
                $this->fail("Bentuk jawaban #{$i} seharusnya ditolak");
            } catch (AiException $e) {
                $this->assertStringContainsString('tidak mengembalikan jawaban', $e->getMessage());
            }
        }
    }

    // --- uji koneksi dan saldo --------------------------------------------------------------

    public function test_uji_koneksi_memakai_permintaan_sekecil_mungkin(): void
    {
        Http::fake(['*' => Http::response($this->jawaban('ok'))]);

        $uji = $this->klien()->uji();

        $this->assertSame('gpt-4o-mini-2024-07-18', $uji->model);
        $this->assertGreaterThanOrEqual(0, $uji->latensiMs);
        Http::assertSent(fn (Request $r) => $r['max_tokens'] === 5 && $r['temperature'] === 0);
    }

    public function test_saldo_sumopod_dihitung_dari_anggaran_dikurangi_terpakai(): void
    {
        Http::fake(['ai.sumopod.com/v2/user/info' => Http::response(['spend' => 47.5, 'max_budget' => 99.3])]);

        $saldo = $this->klien()->saldo();

        $this->assertEqualsWithDelta(51.8, $saldo['sisa'], 0.001);
        $this->assertSame(99.3, $saldo['anggaran']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://ai.sumopod.com/v2/user/info'
            && $r->header('Authorization') === ['Bearer ' . self::KUNCI]);
    }

    public function test_saldo_null_untuk_penyedia_lain_atau_bila_gagal(): void
    {
        Http::fake();
        $this->assertNull($this->klien('https://api.openai.com/v1')->saldo());
        Http::assertNothingSent();

        Http::fake(['*' => Http::response([], 500)]);
        $this->assertNull($this->klien()->saldo());

        Http::fake(fn () => throw new ConnectionException('x'));
        $this->assertNull($this->klien()->saldo());
    }

    // --- konfigurasi dan pabrik -----------------------------------------------------------------

    public function test_tes_memakai_driver_simulasi_secara_bawaan(): void
    {
        $this->assertInstanceOf(FakeAiClient::class, app(AiClient::class));
    }

    public function test_driver_openai_tanpa_kunci_berarti_tidak_tersedia(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => null]);

        $this->expectException(AiTidakTersediaException::class);
        AiFactory::buat();
    }

    public function test_driver_openai_memakai_nilai_env_bila_pengaturan_kosong(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI, 'ai.base_url' => self::BASE, 'ai.model' => 'gpt-4o-mini']);

        $k = KonfigurasiAi::muat();

        $this->assertSame(['openai', 'env', self::BASE, 'gpt-4o-mini', self::KUNCI], [$k->driver, $k->sumber, $k->baseUrl, $k->model, $k->apiKey]);
        $this->assertInstanceOf(OpenAiCompatibleClient::class, app(AiClient::class));
    }

    public function test_kunci_di_pengaturan_menggantikan_seluruh_nilai_env_agar_kunci_tidak_salah_alamat(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => 'sk-dari-env', 'ai.base_url' => self::BASE, 'ai.model' => 'gpt-4o-mini']);
        PengaturanAI::create([
            'id' => 1, 'penyedia' => 'OpenAI-compatible', 'model' => 'gpt-4o', 'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-dari-pengaturan', 'ambang_keyakinan' => 0.62,
        ]);

        $k = KonfigurasiAi::muat();

        $this->assertSame('pengaturan', $k->sumber);
        $this->assertSame(['sk-dari-pengaturan', 'https://api.openai.com/v1', 'gpt-4o'], [$k->apiKey, $k->baseUrl, $k->model]);

        // Permintaan sungguhan harus pergi ke alamat milik kunci itu, bukan ke alamat dari .env.
        Http::fake(['*' => Http::response($this->jawaban())]);
        app(AiClient::class)->chat([['role' => 'user', 'content' => 'x']]);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.openai.com/v1/')
            && $r->header('Authorization') === ['Bearer sk-dari-pengaturan']);
    }

    public function test_pengaturan_tanpa_kunci_tidak_menimpa_env(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI, 'ai.base_url' => self::BASE]);
        PengaturanAI::create(['id' => 1, 'penyedia' => 'x', 'model' => 'gpt-4o', 'base_url' => 'https://lain.test/v1', 'ambang_keyakinan' => 0.62]);

        $k = KonfigurasiAi::muat();

        $this->assertSame('env', $k->sumber);
        $this->assertSame(self::BASE, $k->baseUrl);
    }

    public function test_driver_tidak_dikenal_ditolak(): void
    {
        config(['ai.driver' => 'tidak-ada', 'ai.api_key' => self::KUNCI]);

        $this->expectException(\InvalidArgumentException::class);
        AiFactory::buat();
    }

    public function test_kunci_samar_tidak_menampilkan_kunci_utuh(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI]);

        $samar = KonfigurasiAi::muat()->kunciSamar();

        $this->assertSame('sk-…cdef', $samar);
        $this->assertStringNotContainsString('rahasia', $samar);
    }

    // --- klien simulasi -----------------------------------------------------------------------

    public function test_klien_simulasi_dapat_diprogram_dan_mencatat_riwayat(): void
    {
        FakeAiClient::antre('jawaban satu', 'jawaban dua');
        $klien = new FakeAiClient();

        $this->assertSame('jawaban satu', $klien->chat([['role' => 'user', 'content' => 'a']])->teks);
        $this->assertSame('jawaban dua', $klien->chat([['role' => 'user', 'content' => 'b']])->teks);
        $this->assertSame('[simulasi] c', $klien->chat([['role' => 'user', 'content' => 'c']])->teks);
        $this->assertCount(3, FakeAiClient::$riwayat);

        FakeAiClient::gagal(new AiTidakTersediaException('mati'));
        $this->expectException(AiTidakTersediaException::class);
        $klien->chat([['role' => 'user', 'content' => 'd']]);
    }

    // --- perintah ai:uji -------------------------------------------------------------------------

    public function test_perintah_uji_berhasil_dan_tidak_menampilkan_kunci(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI, 'ai.base_url' => self::BASE]);
        Http::fake([
            'ai.sumopod.com/v1/*' => Http::response($this->jawaban('Halo dari AI')),
            'ai.sumopod.com/v2/*' => Http::response(['spend' => 10, 'max_budget' => 100]),
        ]);

        $this->artisan('ai:uji', ['pesan' => 'Sapa saya'])
            ->expectsOutputToContain('Koneksi berhasil')
            ->expectsOutputToContain('Halo dari AI')
            ->expectsOutputToContain('Saldo penyedia: sisa 90.00')
            ->doesntExpectOutputToContain(self::KUNCI)
            ->assertSuccessful();
    }

    public function test_perintah_uji_melaporkan_kunci_ditolak_dengan_petunjuk(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => self::KUNCI, 'ai.base_url' => self::BASE]);
        Http::fake(['*' => Http::response([], 401)]);

        $this->artisan('ai:uji')
            ->expectsOutputToContain('Kunci API ditolak penyedia')
            ->doesntExpectOutputToContain(self::KUNCI)
            ->assertFailed();
    }

    public function test_perintah_uji_tanpa_kunci_memberi_pesan_jelas(): void
    {
        config(['ai.driver' => 'openai', 'ai.api_key' => null]);

        $this->artisan('ai:uji')->expectsOutputToContain('(belum diisi)')->expectsOutputToContain('Kunci API AI belum diatur')->assertFailed();
    }
}
