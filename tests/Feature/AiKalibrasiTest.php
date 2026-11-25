<?php

namespace Tests\Feature;

use App\Services\Ai\FakeAiClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Set uji kalibrasi: strukturnya sah dan mode aturan tidak boleh berhalusinasi. */
class AiKalibrasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_pertanyaan_berisi_50an_soal_dengan_bidang_sah(): void
    {
        $soal = require database_path('kalibrasi/pertanyaan.php');

        $this->assertGreaterThanOrEqual(45, count($soal));
        foreach ($soal as $s) {
            $this->assertContains($s['harapan'], ['jawab', 'eskalasi']);
            $this->assertNotSame('', trim($s['pesan']));
            $this->assertSame($s['harapan'] === 'jawab', isset($s['artikel']), $s['pesan']);
        }
        $this->assertGreaterThanOrEqual(10, collect($soal)->where('kategori', 'jebakan')->count());
        $this->assertGreaterThanOrEqual(3, collect($soal)->where('kategori', 'manipulasi')->count());
    }

    public function test_mode_aturan_tidak_menghasilkan_halusinasi_pada_set_uji(): void
    {
        FakeAiClient::reset();
        $this->seed(DatabaseSeeder::class);

        $this->artisan('ai:kalibrasi', ['--tanpa-ai' => true])
            ->expectsOutputToContain('Halusinasi')
            ->assertSuccessful();
    }

    public function test_perintah_gagal_bila_ada_halusinasi(): void
    {
        FakeAiClient::reset();
        $this->seed(DatabaseSeeder::class);

        // Ambang nol membuat artikel apa pun dianggap cocok: mesin menjawab pertanyaan jebakan.
        config(['ai.ambang_relevansi_aturan' => 0.0]);
        $this->artisan('ai:kalibrasi', ['--tanpa-ai' => true, '--ambang-relevansi' => '0', '--ambang-keyakinan' => '0.01'])
            ->assertFailed();
    }
}
