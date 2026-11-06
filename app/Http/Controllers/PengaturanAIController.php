<?php

namespace App\Http\Controllers;

use App\Models\PengaturanAI;
use Illuminate\Http\Request;

class PengaturanAIController extends Controller
{
    private function pengaturan()
    {
        return PengaturanAI::firstOrCreate(
            ['id' => 1],
            [
                'penyedia' => 'OpenAI-compatible',
                'model' => 'gpt-4o-mini',
                'base_url' => 'https://api.openai.com/v1',
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
            ]
        );
    }

    public function index()
    {
        $pengaturanAI = $this->pengaturan();

        return view('pengaturan-ai', compact('pengaturanAI'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'penyedia' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'api_key' => 'nullable|string|max:5000',
            'base_url' => 'nullable|url|max:500',
            'instruksi_persona' => 'nullable|string',
            'ambang_keyakinan' => 'required|numeric|between:0,1',
            'eskalasi_pelanggan_marah' => 'required|boolean',
            'kata_kunci_eskalasi' => 'nullable|string',
            'jeda_min' => 'required|integer|min:0',
            'jeda_max' => 'required|integer|min:0|gte:jeda_min',
            'batas_pesan_harian' => 'required|integer|min:1',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'variasi_kalimat' => 'required|boolean',
        ]);

        $pengaturan = $this->pengaturan();

        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $pengaturan->update($data);

        return redirect()
            ->route('pengaturan-ai')
            ->with('success', 'Pengaturan AI berhasil disimpan.');
    }

    // Simulasi pengujian AI berdasarkan aturan dan kata kunci.
    public function uji(Request $request)
    {
        $request->validate([
            'pesan' => 'required|string|max:2000',
        ]);

        $pengaturan = $this->pengaturan();

        $pesan = mb_strtolower($request->pesan);

        // Mengambil kata kunci eskalasi dari pengaturan.
        $daftarKataKunci = preg_split(
            '/[,;\n]+/',
            $pengaturan->kata_kunci_eskalasi ?? ''
        );

        $kataKunciTerdeteksi = null;

        foreach ($daftarKataKunci as $kata) {
            $kata = mb_strtolower(trim($kata));

            if ($kata !== '' && str_contains($pesan, $kata)) {
                $kataKunciTerdeteksi = $kata;
                break;
            }
        }

        // Menentukan kategori pesan.
        if (str_contains($pesan, 'refund')) {
            $intent = 'Permintaan refund';
        } elseif (str_contains($pesan, 'komplain')) {
            $intent = 'Komplain pelanggan';
        } elseif (
            str_contains($pesan, 'hasil tryout') ||
            str_contains($pesan, 'nilai tryout')
        ) {
            $intent = 'Pertanyaan hasil tryout';
        } elseif (str_contains($pesan, 'biaya')) {
            $intent = 'Pertanyaan biaya';
        } elseif (str_contains($pesan, 'admin')) {
            $intent = 'Permintaan bantuan admin';
        } else {
            $intent = 'Pertanyaan umum';
        }

        // Menentukan apakah pesan perlu diteruskan ke admin.
        $eskalasi = $pengaturan->eskalasi_pelanggan_marah
            && $kataKunciTerdeteksi !== null;

        // Membuat balasan simulasi berdasarkan kategori pesan.
        if ($eskalasi) {
            $balasan = 'Baik Ayah/Bunda, terima kasih sudah menyampaikan hal tersebut. '
                . 'Agar dapat dibantu lebih lanjut, pesan Ayah/Bunda akan diteruskan '
                . 'kepada tim admin Asentra.';
        } elseif ($intent === 'Pertanyaan hasil tryout') {
            $balasan = 'Baik Ayah/Bunda, untuk hasil tryout dapat dibantu '
                . 'dengan melakukan pengecekan terlebih dahulu. Mohon tunggu '
                . 'sebentar, ya.';
        } elseif ($intent === 'Pertanyaan biaya') {
            $balasan = 'Baik Ayah/Bunda, untuk informasi biaya program '
                . 'Asentra, silakan sampaikan program yang ingin ditanyakan '
                . 'agar kami dapat membantu memberikan informasi yang sesuai.';
        } else {
            $balasan = 'Halo Ayah/Bunda, terima kasih sudah menghubungi Asentra. '
                . 'Boleh dijelaskan lebih lanjut kebutuhan atau pertanyaan '
                . 'Ayah/Bunda agar kami dapat membantu?';
        }

        // Nilai ini merupakan kecocokan aturan, bukan confidence AI sungguhan.
        $tingkatKecocokan = $intent === 'Pertanyaan umum' ? 0.35 : 0.85;

        return response()->json([
            'success' => true,
            'mode' => 'Simulasi aturan',
            'intent' => $intent,
            'kata_kunci_terdeteksi' => $kataKunciTerdeteksi,
            'eskalasi' => $eskalasi,
            'tingkat_kecocokan' => $tingkatKecocokan,
            'ambang_keyakinan' => (float) $pengaturan->ambang_keyakinan,
            'balasan' => $balasan,
        ]);
    }

    public function reset()
    {
        $pengaturan = $this->pengaturan();

        $pengaturan->update([
            'penyedia' => 'OpenAI-compatible',
            'model' => 'gpt-4o-mini',
            'api_key' => null,
            'base_url' => 'https://api.openai.com/v1',
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
        ]);

        return redirect()
            ->route('pengaturan-ai')
            ->with('success', 'Pengaturan AI berhasil direset.');
    }
}
