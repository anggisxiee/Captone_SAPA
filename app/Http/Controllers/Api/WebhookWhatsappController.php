<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NomorWhatsapp;
use App\Services\WhatsApp\PesanMasuk;
use App\Services\WhatsApp\SesiNomor;
use App\Support\Telepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookWhatsappController extends Controller
{
    /**
     * Pesan pelanggan yang diterima gateway (F02).
     */
    public function pesanMasuk(Request $request, PesanMasuk $layanan): JsonResponse
    {
        $data = $request->validate([
            'nomor' => 'required|string|max:30',
            'dari' => 'required|string|max:30',
            'nama' => 'nullable|string|max:255',
            'isi' => 'required|string|max:5000',
            'id_pesan' => 'nullable|string|max:255',
        ]);

        $nomor = $this->cariNomor($data['nomor']);

        if (!$nomor) {
            return response()->json(['pesan' => 'Nomor WhatsApp tidak dikenal.'], 404);
        }

        // Prakondisi F02: nomor tujuan harus tersambung.
        if (!$nomor->dapatMenerimaDanMengirim()) {
            return response()->json(['pesan' => 'Nomor WhatsApp tidak tersambung.'], 409);
        }

        $hasil = $layanan->simpan(
            $nomor,
            $data['dari'],
            $data['nama'] ?? null,
            $data['isi'],
            $data['id_pesan'] ?? null
        );

        return response()->json([
            'pesan' => $hasil['duplikat'] ? 'Pesan sudah pernah diterima.' : 'Pesan disimpan.',
            'duplikat' => $hasil['duplikat'],
            'id' => $hasil['pesan']->id,
        ], $hasil['duplikat'] ? 200 : 201);
    }

    /**
     * Perubahan status sesi dari gateway: tersambung, terputus, atau gagal.
     */
    public function sesi(Request $request, SesiNomor $sesi): JsonResponse
    {
        $data = $request->validate([
            'nomor' => 'required|string|max:30',
            'status' => 'required|in:tersambung,terputus,gagal',
            'alasan' => 'nullable|string|max:500',
        ]);

        $nomor = $this->cariNomor($data['nomor']);

        if (!$nomor) {
            return response()->json(['pesan' => 'Nomor WhatsApp tidak dikenal.'], 404);
        }

        match ($data['status']) {
            'tersambung' => $sesi->tandaiTersambung($nomor),
            'terputus' => $sesi->tandaiTerputus($nomor),
            'gagal' => $sesi->tandaiGagal($nomor, $data['alasan'] ?? null),
        };

        return response()->json(['pesan' => 'Status sesi diperbarui.', 'status_sesi' => $nomor->fresh()->status_sesi]);
    }

    private function cariNomor(string $nomor): ?NomorWhatsapp
    {
        return NomorWhatsapp::where('nomor', Telepon::normalisasi($nomor))->first();
    }
}
