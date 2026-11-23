<?php

namespace App\Http\Controllers;

use App\Exceptions\AiException;
use App\Exceptions\KunciApiDitolakException;
use App\Models\LogAudit;
use App\Models\PengaturanAI;
use App\Services\Ai\KonfigurasiAi;
use App\Services\Ai\MesinJawaban;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Support\Telepon;
use Illuminate\Http\Request;

class PengaturanAIController extends Controller
{
    private function pengaturan(): PengaturanAI
    {
        return PengaturanAI::firstOrCreate(['id' => 1], $this->nilaiBawaan());
    }

    /** @return array<string, mixed> */
    private function nilaiBawaan(): array
    {
        return [
            'penyedia' => 'OpenAI-compatible',
            'model' => config('ai.model'),
            'base_url' => config('ai.base_url'),
            'instruksi_persona' => 'Ramah dan solutif sebagai Kakak Konselor Bimbel Asentra. Sapa orang tua dengan Ayah/Bunda. Jangan menjanjikan kelulusan atau nilai.',
            'ambang_keyakinan' => 0.62,
            'eskalasi_pelanggan_marah' => true,
            'kata_kunci_eskalasi' => 'admin,komplain,refund,bicara manusia,biaya mahal',
            'jeda_min' => 3,
            'jeda_max' => 8,
            'batas_pesan_harian' => 1500,
            'jam_mulai' => '07:00',
            'jam_selesai' => '21:00',
            'variasi_kalimat' => true,
            'nomor_uji' => null,
        ];
    }

    public function index()
    {
        return view('pengaturan-ai', [
            'pengaturanAI' => $this->pengaturan(),
            // Kunci yang benar-benar dipakai (bisa berasal dari .env), sudah disamarkan.
            'konfigurasi' => KonfigurasiAi::muat(),
        ]);
    }

    public function update(Request $request)
    {
        // Daftar nomor uji: dipisah koma/spasi/baris, diseragamkan ke 62xxx.
        if (is_string($request->nomor_uji)) {
            $daftar = preg_split('/[,;\n]+/', $request->nomor_uji, -1, PREG_SPLIT_NO_EMPTY);
            $request->merge(['nomor_uji' => implode(',', array_unique(array_filter(array_map(fn ($n) => Telepon::normalisasi(trim($n)), $daftar))))]);
        }

        $data = $request->validate([
            'penyedia' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'api_key' => 'nullable|string|max:5000',
            'base_url' => ['nullable', 'url', 'max:500', fn ($atribut, $nilai, $gagal) => $this->periksaAlamat($nilai, $gagal)],
            'instruksi_persona' => 'nullable|string',
            // BR13: nilai batas tidak boleh negatif atau nol.
            'ambang_keyakinan' => 'required|numeric|gt:0|max:1',
            'eskalasi_pelanggan_marah' => 'required|boolean',
            'kata_kunci_eskalasi' => 'nullable|string',
            'jeda_min' => 'required|integer|min:1',
            'jeda_max' => 'required|integer|min:1|gte:jeda_min',
            'batas_pesan_harian' => 'required|integer|min:1',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'variasi_kalimat' => 'required|boolean',
            'nomor_uji' => ['nullable', 'string', 'max:1000', function ($atribut, $nilai, $gagal) {
                foreach (array_filter(explode(',', (string) $nilai)) as $n) {
                    if (!preg_match('/^62\d{8,13}$/', $n)) {
                        $gagal("Nomor uji \"{$n}\" tidak valid. Contoh: 081234567890.");
                    }
                }
            }],
        ], [
            'ambang_keyakinan.gt' => 'Ambang keyakinan harus lebih dari 0 dan paling besar 1.',
            'ambang_keyakinan.max' => 'Ambang keyakinan harus lebih dari 0 dan paling besar 1.',
            'jeda_min.min' => 'Jeda minimum harus lebih dari 0 detik.',
            'jeda_max.min' => 'Jeda maksimum harus lebih dari 0 detik.',
            'jeda_max.gte' => 'Jeda maksimum tidak boleh lebih kecil dari jeda minimum.',
            'batas_pesan_harian.min' => 'Batas pesan harian harus lebih dari 0.',
        ]);

        $pengaturan = $this->pengaturan();
        $kunciBaru = trim((string) ($data['api_key'] ?? ''));
        unset($data['api_key']);

        $alamatBaru = rtrim((string) ($data['base_url'] ?? ''), '/');
        $alamatLama = rtrim((string) $pengaturan->base_url, '/');

        // Mengganti alamat penyedia akan mengirim kunci ke alamat baru. Kunci tersimpan tidak boleh
        // ikut terbawa tanpa sepengetahuan pengguna, jadi kunci harus dimasukkan ulang.
        if ($alamatBaru !== '' && $alamatBaru !== $alamatLama && $pengaturan->api_key && $kunciBaru === '') {
            return back()->withInput($request->except('api_key'))->withErrors([
                'api_key' => 'Alamat penyedia berubah: masukkan kunci API untuk penyedia ini.',
            ]);
        }

        $peringatan = null;

        // F13 langkah 3: kunci baru diuji ke penyedia sebelum disimpan.
        if ($kunciBaru !== '') {
            try {
                (new OpenAiCompatibleClient(
                    $alamatBaru ?: (string) config('ai.base_url'),
                    $kunciBaru,
                    $data['model'],
                    (int) config('ai.timeout')
                ))->uji();
            } catch (KunciApiDitolakException $e) {
                // Pengaturan lama tetap dipakai: tidak ada yang disimpan.
                return back()->withInput($request->except('api_key'))->withErrors(['api_key' => $e->getMessage()]);
            } catch (AiException $e) {
                $peringatan = 'Pengaturan disimpan, tetapi koneksi ke penyedia belum dapat diuji: ' . $e->getMessage();
            }

            $data['api_key'] = $kunciBaru;
        }

        $pengaturan->update($data);

        $berubah = array_values(array_diff(array_keys($pengaturan->getChanges()), ['updated_at', 'api_key']));
        $kunciBerubah = $kunciBaru !== '';

        if ($berubah || $kunciBerubah) {
            // Nilai kunci tidak pernah dicatat; hanya fakta bahwa kunci diganti.
            LogAudit::catat('Ubah pengaturan AI', 'Pengaturan AI diubah', 'pengaturan_ai', null, $pengaturan->id, [
                'diubah' => $berubah,
                'kunci_api_diganti' => $kunciBerubah,
            ]);
        }

        return redirect()
            ->route('pengaturan-ai')
            ->with('success', 'Pengaturan AI berhasil disimpan.')
            ->with('peringatan', $peringatan);
    }

    /**
     * Konsol "Uji Keputusan" (F13 3a): menjalankan mesin jawaban pada satu pesan tanpa mengirim apa pun.
     * Pemanggilan ke penyedia AI nyata (dan memakai sedikit saldo) hanya terjadi bila pesan lolos
     * penyaringan gratis (kata kunci, nada, dan pencarian artikel).
     */
    public function uji(Request $request, MesinJawaban $mesin)
    {
        $request->validate(['pesan' => 'required|string|max:2000']);

        $k = $mesin->putuskan($request->pesan, $this->pengaturan());
        $d = $k->detail;

        return response()->json([
            'success' => true,
            'keputusan' => $k->keputusan,
            'eskalasi' => $k->eskalasi(),
            'alasan' => $k->teksAlasan(),
            'alasan_kode' => $k->alasan,
            'mode' => $k->mode,
            'model' => $d['model'] ?? null,
            'maksud' => $k->maksud,
            'kata_kunci' => $d['kata_kunci'] ?? null,
            'nada' => $d['nada'] ?? null,
            'artikel' => $d['artikel'] ?? [],
            'sumber' => $k->sumber,
            'keyakinan' => $k->keyakinan,
            'ambang' => $k->ambang,
            'ambang_relevansi' => (float) config('ai.ambang_relevansi'),
            // Yang akan diterima pelanggan: jawaban, atau pesan eskalasi standar.
            'pesan_pelanggan' => $k->eskalasi() ? MesinJawaban::PESAN_ESKALASI : $k->jawaban,
            // Pada eskalasi karena keyakinan, jawaban yang ditolak tetap ditampilkan untuk dinilai.
            'jawaban_ditolak' => $k->eskalasi() ? $k->jawaban : null,
            'token' => isset($d['token_masuk']) ? ($d['token_masuk'] + $d['token_keluar']) : null,
            'latensi_ms' => $d['latensi_ms'] ?? null,
            'galat' => $d['galat'] ?? null,
        ]);
    }

    public function reset()
    {
        $pengaturan = $this->pengaturan();

        $pengaturan->update($this->nilaiBawaan() + ['api_key' => null]);

        LogAudit::catat('Reset pengaturan AI', 'Pengaturan AI dikembalikan ke nilai bawaan', 'pengaturan_ai', null, $pengaturan->id);

        return redirect()
            ->route('pengaturan-ai')
            ->with('success', 'Pengaturan AI berhasil direset.');
    }

    /**
     * Alamat penyedia dipakai server untuk memanggil keluar, jadi tidak boleh menunjuk ke layanan
     * internal (mis. gateway di 127.0.0.1) atau memakai http polos di luar pengembangan.
     */
    private function periksaAlamat(?string $alamat, \Closure $gagal): void
    {
        if (!$alamat) {
            return;
        }

        $bagian = parse_url($alamat);
        $host = strtolower($bagian['host'] ?? '');
        $lokal = app()->environment('local', 'testing');

        if (!$lokal && ($bagian['scheme'] ?? '') !== 'https') {
            $gagal('Alamat penyedia harus memakai https.');

            return;
        }

        $internal = $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));

        if ($internal && !$lokal) {
            $gagal('Alamat penyedia tidak boleh menunjuk ke jaringan internal.');
        }
    }
}
