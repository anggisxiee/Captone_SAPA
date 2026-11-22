<?php

namespace App\Services\WhatsApp;

use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Support\Telepon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan pesan pelanggan yang diteruskan gateway (F02).
 */
class PesanMasuk
{
    /**
     * @return array{pesan: PesanWhatsapp, duplikat: bool}
     */
    public function simpan(
        NomorWhatsapp $nomor,
        string $dari,
        ?string $nama,
        string $isi,
        ?string $idPesan = null
    ): array {
        $telepon = Telepon::normalisasi($dari);

        return DB::transaction(function () use ($nomor, $telepon, $nama, $isi, $idPesan) {
            if ($idPesan !== null) {
                $ada = PesanWhatsapp::where('nomor_whatsapp_id', $nomor->id)
                    ->where('wa_message_id', $idPesan)
                    ->first();

                if ($ada) {
                    return ['pesan' => $ada, 'duplikat' => true];
                }
            }

            $percakapan = Percakapan::untuk($nomor, $telepon, $nama ?: $telepon);

            $pesan = PesanWhatsapp::create([
                'nomor_whatsapp_id' => $nomor->id,
                'percakapan_id' => $percakapan->id,
                'kontak_nama' => $percakapan->kontak->nama,
                'kontak_telepon' => $telepon,
                'pengirim' => 'pelanggan',
                'isi' => $isi,
                'wa_message_id' => $idPesan,
            ]);

            $percakapan->update([
                // Percakapan yang sudah selesai dibuka kembali.
                'status' => $percakapan->status === 'Selesai' ? 'Baru' : $percakapan->status,
                'belum_dibalas' => true,
                'pesan_terakhir_pada' => now(),
            ]);

            $nomor->update(['terakhir_aktif_pada' => now()]);

            return ['pesan' => $pesan, 'duplikat' => false];
        });
    }
}
