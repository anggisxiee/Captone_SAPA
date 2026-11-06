<?php

namespace App\Http\Controllers;

use App\Models\AntreanPesan;
use App\Models\InsidenRisiko;
use App\Models\NomorWhatsapp;
use App\Models\RiwayatAntrean;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AntreanRisikoController extends Controller
{
    public function index()
    {
        // Mengambil data antrean pesan
        $dataAntrean = AntreanPesan::latest()->get();

        // Mengambil data insiden risiko
        $insiden = InsidenRisiko::with('nomorWhatsapp')
            ->latest('terjadi_pada')
            ->get();

        // Mengambil daftar nomor WhatsApp
        $nomorWhatsapp = NomorWhatsapp::orderBy('nama')->get();

        /*
        |--------------------------------------------------------------------------
        | Statistik Antrean Pesan
        |--------------------------------------------------------------------------
        */

        $jumlahAntrean = AntreanPesan::where('keputusan', 'Kirim')
            ->count();

        $jumlahTerkirim = AntreanPesan::where('keputusan', 'Terkirim')
            ->whereDate('terkirim_pada', today())
            ->count();

        $jumlahDitunda = AntreanPesan::where('keputusan', 'Ditunda')
            ->count();

        $jumlahDitahan = AntreanPesan::where('keputusan', 'Ditahan')
            ->count();

        $stat = [
            ['Dalam antrean', $jumlahAntrean, 'Menunggu proses pengiriman'],
            ['Terkirim hari ini', $jumlahTerkirim, 'Pesan berhasil dikirim'],
            ['Ditunda', $jumlahDitunda, 'Menunggu jadwal pengiriman'],
            ['Ditahan guardrail', $jumlahDitahan, 'Perlu ditinjau'],
        ];

        /*
        |--------------------------------------------------------------------------
        | Statistik Insiden Risiko
        |--------------------------------------------------------------------------
        */

        $jumlahInsiden = InsidenRisiko::count();

        $jumlahInsidenTerbuka = InsidenRisiko::where('status', 'Terbuka')
            ->count();

        $jumlahInsidenDitangani = InsidenRisiko::where('status', 'Ditangani')
            ->count();

        $jumlahInsidenSelesai = InsidenRisiko::where('status', 'Selesai')
            ->count();

        $statInsiden = [
            ['Total Insiden', $jumlahInsiden],
            ['Terbuka', $jumlahInsidenTerbuka],
            ['Sedang Ditangani', $jumlahInsidenDitangani],
            ['Selesai', $jumlahInsidenSelesai],
        ];

        /*
        |--------------------------------------------------------------------------
        | Mengolah Data Antrean
        |--------------------------------------------------------------------------
        */

        $antrean = $dataAntrean->map(function ($pesan) {

            $warna = match ($pesan->keputusan) {
                'Kirim', 'Terkirim' => 'b-green',
                'Ditunda' => 'b-amber',
                'Ditahan', 'Gagal' => 'b-red',
                default => 'b-amber',
            };

            return [
                'id' => $pesan->id,
                'jam' => $pesan->jadwal_kirim
                    ? $pesan->jadwal_kirim->format('H:i:s')
                    : '-',
                'pengirim' => $pesan->pengirim,
                'penerima' => $pesan->penerima,
                'isi' => $pesan->isi_pesan,
                'skor' => $pesan->skor_risiko,
                'keputusan' => $pesan->keputusan,
                'warna' => $warna,
                'coba' => $pesan->percobaan
                    . '/'
                    . $pesan->maks_percobaan,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Menghitung Kesehatan Nomor WhatsApp
        |--------------------------------------------------------------------------
        */

        $kesehatanNomor = $nomorWhatsapp->map(function ($nomor) use ($dataAntrean) {

            // Mengambil antrean berdasarkan nomor WhatsApp
            $pesanNomor = $dataAntrean->where(
                'nomor_whatsapp_id',
                $nomor->id
            );

            // Menghitung jumlah pesan berdasarkan keputusan
            $terkirim = $pesanNomor->where('keputusan', 'Terkirim')->count();

            $gagal = $pesanNomor->where('keputusan', 'Gagal')->count();

            $ditunda = $pesanNomor->where('keputusan', 'Ditunda')->count();

            $ditahan = $pesanNomor->where('keputusan', 'Ditahan')->count();

            // Menghitung skor kesehatan
            $totalPesan = $pesanNomor->count();

            if ($totalPesan === 0) {
                $skorKesehatan = null;
                $kategori = 'Belum Ada Data';
                $warna = 'b-amber';
            } else {
                $skorKesehatan = max(
                    0,
                    100 - ($gagal * 15) - ($ditahan * 10) - ($ditunda * 3)
                );

                // Menentukan kategori kesehatan
                if ($skorKesehatan >= 80) {
                    $kategori = 'Sehat';
                    $warna = 'b-green';
                } elseif ($skorKesehatan >= 50) {
                    $kategori = 'Waspada';
                    $warna = 'b-amber';
                } else {
                    $kategori = 'Berisiko';
                    $warna = 'b-red';
                }
            }

            return [
                'id' => $nomor->id,
                'nama' => $nomor->nama,
                'nomor' => $nomor->nomor,
                'status' => $nomor->status,
                'terkirim' => $terkirim,
                'gagal' => $gagal,
                'ditunda' => $ditunda,
                'ditahan' => $ditahan,
                'total_pesan' => $totalPesan,
                'skor_kesehatan' => $skorKesehatan,
                'kategori' => $kategori,
                'warna' => $warna,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Mengirim Data ke Halaman Antrean dan Risiko
        |--------------------------------------------------------------------------
        */

        return view('antrean-risiko', compact(
            'stat',
            'statInsiden',
            'antrean',
            'insiden',
            'nomorWhatsapp',
            'kesehatanNomor'
        ));
    }

    /**
     * Mengubah status pesan dan menyimpan riwayat perubahannya.
     */
    public function ubahStatus(Request $request, $id)
    {
        $request->validate([
            'keputusan' => 'required|in:Kirim,Ditunda,Ditahan,Gagal',
        ]);

        DB::transaction(function () use ($request, $id) {

            // Mengambil data pesan
            $pesan = AntreanPesan::findOrFail($id);

            // Menyimpan status lama dan status baru
            $statusSebelumnya = $pesan->keputusan;
            $statusBaru = $request->keputusan;

            // Memproses jika status benar-benar berubah
            if ($statusSebelumnya !== $statusBaru) {

                // Memperbarui status antrean
                $pesan->update([
                    'keputusan' => $statusBaru,
                ]);

                // Menyimpan riwayat perubahan status
                RiwayatAntrean::create([
                    'antrean_pesan_id' => $pesan->id,
                    'status_sebelumnya' => $statusSebelumnya,
                    'status_baru' => $statusBaru,
                    'keterangan' => 'Status antrean diperbarui melalui halaman Antrean dan Risiko.',
                    'diubah_pada' => now(),
                ]);
            }
        });

        return redirect()
            ->route('antrean-risiko')
            ->with('success', 'Status pesan berhasil diperbarui dan riwayat berhasil disimpan.');
    }
}
