<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use App\Exceptions\AiTidakTersediaException;
use App\Exceptions\KunciApiDitolakException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien untuk penyedia yang kompatibel dengan OpenAI (SumoPod, OpenAI, dll.).
 * Kunci API tidak pernah disertakan dalam pesan galat maupun log.
 */
class OpenAiCompatibleClient implements AiClient
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 20,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function chat(array $pesan, array $opsi = []): HasilAi
    {
        $muatan = [
            'model' => $this->model,
            'messages' => $pesan,
            'max_tokens' => $opsi['max_tokens'] ?? 500,
            'temperature' => $opsi['temperature'] ?? 0.2,
        ];

        if (!empty($opsi['json'])) {
            $muatan['response_format'] = ['type' => 'json_object'];
        }

        $res = $this->kirim('/chat/completions', $muatan);

        $teks = $res->json('choices.0.message.content');
        if (!is_string($teks) || trim($teks) === '') {
            throw new AiException('Penyedia AI tidak mengembalikan jawaban.');
        }

        return new HasilAi(
            teks: $teks,
            model: (string) ($res->json('model') ?? $this->model),
            tokenMasuk: (int) $res->json('usage.prompt_tokens', 0),
            tokenKeluar: (int) $res->json('usage.completion_tokens', 0),
        );
    }

    public function uji(): HasilUji
    {
        $mulai = hrtime(true);

        $hasil = $this->chat(
            [['role' => 'user', 'content' => 'Balas dengan satu kata: ok']],
            ['max_tokens' => 5, 'temperature' => 0]
        );

        return new HasilUji($hasil->model, (int) round((hrtime(true) - $mulai) / 1_000_000));
    }

    /** Endpoint saldo hanya dikenal pada SumoPod; penyedia lain mengembalikan null. */
    public function saldo(): ?array
    {
        $bagian = parse_url($this->baseUrl);
        $host = $bagian['host'] ?? '';

        if (!str_ends_with($host, 'sumopod.com')) {
            return null;
        }

        try {
            $res = $this->klien()->get(($bagian['scheme'] ?? 'https') . '://' . $host . '/v2/user/info');
        } catch (ConnectionException) {
            return null;
        }

        if ($res->failed() || !is_numeric($res->json('max_budget')) || !is_numeric($res->json('spend'))) {
            return null;
        }

        return [
            'anggaran' => (float) $res->json('max_budget'),
            'terpakai' => (float) $res->json('spend'),
            'sisa' => (float) $res->json('max_budget') - (float) $res->json('spend'),
        ];
    }

    private function kirim(string $jalur, array $muatan): Response
    {
        try {
            $res = $this->klien()->post($this->baseUrl . $jalur, $muatan);
        } catch (ConnectionException) {
            throw new AiTidakTersediaException('Penyedia AI tidak dapat dihubungi atau melewati batas waktu.');
        }

        $status = $res->status();

        if ($status === 401 || $status === 403) {
            throw new KunciApiDitolakException();
        }

        if ($status === 402 || $status === 429) {
            throw new AiTidakTersediaException('Batas penggunaan atau anggaran penyedia AI tercapai.');
        }

        if ($res->serverError()) {
            throw new AiTidakTersediaException("Penyedia AI sedang bermasalah (HTTP {$status}).");
        }

        if ($res->failed()) {
            $pesan = $res->json('error.message');

            throw new AiException(is_string($pesan) && $pesan !== ''
                ? "Penyedia AI menolak permintaan: {$pesan}"
                : "Penyedia AI menolak permintaan (HTTP {$status}).");
        }

        return $res;
    }

    private function klien(): PendingRequest
    {
        return Http::withToken($this->apiKey)->acceptJson()->asJson()->timeout($this->timeout);
    }
}
