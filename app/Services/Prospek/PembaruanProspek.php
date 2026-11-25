<?php

namespace App\Services\Prospek;

use App\Models\Kontak;
use App\Models\LogAudit;
use App\Models\PesanWhatsapp;
use App\Models\Prospek;

/**
 * F03: memastikan pengirim punya prospek (BR04) dan mengisi data calon siswa dari isi pesan.
 *
 * Data yang sudah terisi tidak ditimpa, sehingga perubahan manual agen tidak hilang;
 * hanya kolom kosong yang dilengkapi.
 */
class PembaruanProspek
{
    public function __construct(
        private readonly EkstraksiPesan $ekstraksi,
        private readonly SkorPrioritas $skor,
    ) {
    }

    public function terapkan(Kontak $kontak, PesanWhatsapp $pesan): void
    {
        $hasil = $this->ekstraksi->ekstrak($pesan->isi);

        $isi = [];
        foreach (['jenjang', 'kelas', 'mapel'] as $kolom) {
            if ($hasil[$kolom] !== null && !$kontak->{$kolom}) {
                $isi[$kolom] = $hasil[$kolom];
            }
        }
        // Nama yang hanya berupa nomor telepon dianggap belum diketahui.
        if ($hasil['nama'] !== null && (!$kontak->nama || $kontak->nama === $kontak->nomor_telepon)) {
            $isi['nama'] = $hasil['nama'];
        }
        if (!$kontak->sumber) {
            $isi['sumber'] = 'WhatsApp';
        }
        if ($isi) {
            $kontak->update($isi);
        }

        $prospek = Prospek::where('kontak_id', $kontak->id)->first();

        if (!$prospek) {
            $prospek = Prospek::create(['kontak_id' => $kontak->id, 'tahap' => 'baru', 'skor' => 0]);

            LogAudit::catat('Buat prospek otomatis', "Prospek baru dari pesan WhatsApp {$kontak->nomor_telepon}", 'prospek', null, $prospek->id);
        }

        // Jejak ekstraksi pada pesan pelanggan agar dapat diperiksa.
        $pesan->update(['meta_ai' => ['ekstraksi' => $hasil]]);

        // BR06: skor dihitung ulang setiap ada pesan atau perubahan data kontak.
        $this->skor->simpan($prospek);
    }
}
