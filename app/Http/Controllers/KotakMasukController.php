<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\NomorWhatsapp;
use App\Models\PesanWhatsapp;
use App\Models\AntreanPesan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KotakMasukController extends Controller
{
    /**
     * Menampilkan halaman Kotak Masuk.
     */
    public function index()
    {
        // Mengambil pengguna yang sedang login.
        $pengguna = User::findOrFail(session('user_id'));

        // Mengambil nomor WhatsApp berdasarkan hak akses.
        if ($pengguna->role === 'Administrator') {
            $nomorWhatsapp = NomorWhatsapp::orderBy('nama')->get();
        } else {
            $nomorWhatsapp = $pengguna->nomorWhatsapp()
                ->wherePivotIn('hak_akses', [
                    'Lihat saja',
                    'Balas chat',
                    'Kelola',
                ])
                ->orderBy('nama')
                ->get();
        }

        // Mengambil pesan dari nomor WhatsApp yang dapat diakses.
        $pesanTersimpan = PesanWhatsapp::whereIn(
            'nomor_whatsapp_id',
            $nomorWhatsapp->pluck('id')
        )
            ->orderBy('created_at')
            ->get();

        return view('kotak-masuk', [
            'penggunaLogin' => $pengguna,
            'nomorWhatsapp' => $nomorWhatsapp,
            'pesanTersimpan' => $pesanTersimpan,
        ]);
    }

    /**
     * Menyimpan pesan balasan agen
     * dan memasukkannya ke antrean pesan.
     */
    public function kirimPesan(Request $request)
    {
        // Validasi data pesan.
        $request->validate([
            'nomor_whatsapp_id' => 'required|exists:nomor_whatsapp,id',
            'kontak_nama' => 'required|string|max:255',
            'kontak_telepon' => 'required|string|max:30',
            'isi' => 'required|string|max:5000',
        ]);

        // Mengambil pengguna yang sedang login.
        $pengguna = User::findOrFail(session('user_id'));

        // Mengambil nomor WhatsApp yang dipilih.
        $nomor = NomorWhatsapp::findOrFail(
            $request->nomor_whatsapp_id
        );

        // Memeriksa hak akses selain Administrator.
        if ($pengguna->role !== 'Administrator') {

            $akses = $pengguna->nomorWhatsapp()
                ->where('nomor_whatsapp.id', $nomor->id)
                ->first();

            // Hanya pengguna dengan hak Balas chat atau Kelola
            // yang boleh membalas pesan.
            if (
                !$akses ||
                !in_array($akses->pivot->hak_akses, [
                    'Balas chat',
                    'Kelola',
                ])
            ) {
                abort(403, 'Kamu tidak memiliki izin membalas pesan.');
            }
        }

        // Menyimpan pesan dan antrean dalam satu transaksi.
        DB::transaction(function () use ($request, $nomor) {

            // 1. Menyimpan pesan ke riwayat percakapan.
            PesanWhatsapp::create([
                'nomor_whatsapp_id' => $nomor->id,
                'kontak_nama' => $request->kontak_nama,
                'kontak_telepon' => $request->kontak_telepon,
                'pengirim' => 'agen',
                'isi' => $request->isi,
            ]);

            // 2. Memasukkan pesan ke antrean pengiriman.
            AntreanPesan::create([
                'nomor_whatsapp_id' => $nomor->id,
                'pengirim' => $nomor->nama,
                'penerima' => $request->kontak_nama,
                'isi_pesan' => $request->isi,
                'skor_risiko' => 0,
                'keputusan' => 'Kirim',
                'percobaan' => 0,
                'maks_percobaan' => 3,
                'jadwal_kirim' => now(),
            ]);
        });

        // Kembali ke halaman Kotak Masuk.
        return redirect()
            ->route('kotak-masuk')
            ->with(
                'success',
                'Pesan berhasil disimpan dan dimasukkan ke antrean.'
            )
            ->with('chat_terpilih', [
                'nomorId' => $nomor->id,
                'telepon' => $request->kontak_telepon,
            ]);
    }
}
