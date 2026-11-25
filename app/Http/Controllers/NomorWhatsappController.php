<?php

namespace App\Http\Controllers;

use App\Exceptions\GatewayException;
use App\Exceptions\SudahTersambungException;
use App\Models\NomorWhatsapp;
use App\Services\WhatsApp\QrRenderer;
use App\Services\WhatsApp\SesiNomor;
use App\Support\Telepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NomorWhatsappController extends Controller
{
    private const TUJUAN = ['Admin', 'Sales'];

    // Menampilkan daftar nomor WhatsApp
    public function index()
    {
        $nomorWhatsapp = NomorWhatsapp::orderBy('label')->get();

        return view('nomor-whatsapp', compact('nomorWhatsapp'));
    }

    // Menyimpan nomor WhatsApp baru
    public function store(Request $request)
    {
        $this->seragamkanNomor($request);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor' => 'required|string|max:25|unique:nomor_whatsapp,nomor',
            'tujuan' => ['required', Rule::in(self::TUJUAN)],
        ]);

        $nomor = NomorWhatsapp::create([
            'nama' => $validated['nama'],
            'nomor' => $validated['nomor'],
            'tujuan' => $validated['tujuan'],
            'status' => 'Aktif',
        ]);

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', 'Nomor WhatsApp berhasil ditambahkan. Tautkan dengan memindai kode QR.')
            ->with('tautkan_id', $nomor->id);
    }

    // Memperbarui nomor WhatsApp
    public function update(Request $request, NomorWhatsapp $nomorWhatsapp)
    {
        $this->seragamkanNomor($request);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor' => [
                'required',
                'string',
                'max:25',
                Rule::unique('nomor_whatsapp', 'nomor')->ignore($nomorWhatsapp->id),
            ],
            'tujuan' => ['required', Rule::in(self::TUJUAN)],
        ]);

        $nomorWhatsapp->update($validated);

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', 'Nomor WhatsApp berhasil diperbarui.');
    }

    // Mengubah status nomor WhatsApp
    public function toggleStatus(NomorWhatsapp $nomorWhatsapp)
    {
        $nomorWhatsapp->update([
            'status' => $nomorWhatsapp->status === 'Aktif'
                ? 'Nonaktif'
                : 'Aktif',
        ]);

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', 'Status nomor WhatsApp berhasil diperbarui.');
    }

    // Menghapus nomor WhatsApp
    public function destroy(NomorWhatsapp $nomorWhatsapp)
    {
        $nomorWhatsapp->delete();

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', 'Nomor WhatsApp berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | Penautan sesi WhatsApp (khusus Administrator)
    |--------------------------------------------------------------------------
    */

    // Membuat QR penautan (juga dipakai untuk "Buat ulang kode").
    public function tautkan(NomorWhatsapp $nomorWhatsapp, SesiNomor $sesi): JsonResponse
    {
        if ($nomorWhatsapp->status !== 'Aktif') {
            return response()->json(['pesan' => 'Aktifkan nomor terlebih dahulu.'], 422);
        }

        try {
            $sesi->mulaiTautan($nomorWhatsapp);
        } catch (SudahTersambungException) {
            // Sesi di gateway masih hidup; selaraskan status di SAPA.
            $sesi->tandaiTersambung($nomorWhatsapp);
        } catch (GatewayException $e) {
            return response()->json(['pesan' => $e->getMessage()], 502);
        }

        return response()->json($this->keadaanSesi($nomorWhatsapp->fresh()));
    }

    // Dipantau berkala oleh modal QR.
    public function statusSesi(NomorWhatsapp $nomorWhatsapp): JsonResponse
    {
        return response()->json($this->keadaanSesi($nomorWhatsapp));
    }

    public function putuskan(NomorWhatsapp $nomorWhatsapp, SesiNomor $sesi)
    {
        $sesi->putuskan($nomorWhatsapp);

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', "Sesi {$nomorWhatsapp->label} diputus.");
    }

    /**
     * Khusus mode uji (driver fake): meniru pelanggan memindai QR.
     */
    public function simulasiPindai(NomorWhatsapp $nomorWhatsapp, SesiNomor $sesi): JsonResponse
    {
        abort_unless(config('whatsapp.driver') === 'fake', 404);

        if ($nomorWhatsapp->status_sesi !== 'Menunggu QR' || $nomorWhatsapp->qrKedaluwarsa()) {
            return response()->json(['pesan' => 'Tidak ada QR aktif untuk dipindai.'], 422);
        }

        $sesi->tandaiTersambung($nomorWhatsapp);

        return response()->json($this->keadaanSesi($nomorWhatsapp->fresh()));
    }

    private function keadaanSesi(NomorWhatsapp $nomor): array
    {
        $menunggu = $nomor->status_sesi === 'Menunggu QR';
        $kedaluwarsa = $nomor->qrKedaluwarsa();

        return [
            'status_sesi' => $nomor->status_sesi,
            'kedaluwarsa' => $kedaluwarsa,
            'sisa_detik' => $menunggu && !$kedaluwarsa
                ? max(0, (int) now()->diffInSeconds($nomor->qr_kedaluwarsa_pada, false))
                : 0,
            'qr_svg' => $menunggu && $nomor->qr_token
                ? QrRenderer::svg($nomor->qr_token)
                : null,
        ];
    }

    private function seragamkanNomor(Request $request): void
    {
        if (is_string($request->nomor)) {
            $request->merge(['nomor' => Telepon::normalisasi($request->nomor)]);
        }
    }
}
