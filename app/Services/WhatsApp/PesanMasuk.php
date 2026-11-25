<?php

namespace App\Services\WhatsApp;

use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Services\Prospek\PembaruanProspek;
use App\Support\Telepon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan pesan pelanggan yang diteruskan gateway (F02).
 */
class PesanMasuk
{
    public function __construct(private readonly PembaruanProspek $prospek)
    {
    }

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
            $percakapan = Percakapan::untuk($nomor, $telepon, $nama ?: null);

            // Id pesan dari gateway unik per percakapan; pengiriman ulang tidak disimpan ganda.
            if ($idPesan !== null) {
                $ada = PesanWhatsapp::where('percakapan_id', $percakapan->id)
                    ->where('wa_message_id', $idPesan)
                    ->first();

                if ($ada) {
                    return ['pesan' => $ada, 'duplikat' => true];
                }
            }

            $pesan = PesanWhatsapp::create([
                'percakapan_id' => $percakapan->id,
                'arah' => 'masuk',
                'jenis_pengirim' => 'pelanggan',
                'isi' => $isi,
                'status' => 'diterima',
                'wa_message_id' => $idPesan,
            ]);

            $percakapan->increment('belum_dibaca');
            $percakapan->update([
                // Percakapan yang sudah selesai dibuka kembali.
                'status' => $percakapan->status === 'Selesai' ? 'Baru' : $percakapan->status,
                'pesan_terakhir_pada' => now(),
            ]);

            $nomor->update(['terakhir_aktif_pada' => now()]);

            // F03: kontak dan prospek otomatis, data calon siswa dari isi pesan.
            $this->prospek->terapkan($percakapan->kontak, $pesan);

            return ['pesan' => $pesan, 'duplikat' => false];
        });
    }
}
