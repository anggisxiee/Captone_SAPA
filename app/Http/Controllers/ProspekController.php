<?php

namespace App\Http\Controllers;

use App\Models\Prospek;
use Illuminate\Http\Request;

class ProspekController extends Controller
{
    public function index()
    {
        // Mengambil data prospek dari database
        $prospek = Prospek::with('penanggungJawab')
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'info' => trim(
                        ($item->jenjang ?? '') . ' · ' .
                        ($item->program ?? ''),
                        ' ·'
                    ),
                    'skor' => $item->skor,
                    'tahap' => $item->tahap,
                    'pj' => $item->penanggungJawab
                        ? $item->penanggungJawab->name
                        : 'Belum ditugaskan',
                    'telp' => $item->nomor_telepon,
                    'catatan' => $item->catatan,
                ];
            });

        $tahap = [
            'baru' => 'Baru',
            'dihubungi' => 'Dihubungi',
            'tertarik' => 'Tertarik',
            'penawaran' => 'Penawaran',
            'daftar' => 'Daftar',
            'batal' => 'Batal',
        ];

        $faktor = [
            ['Kelengkapan data', 90],
            ['Urgensi ujian', 85],
            ['Minat program', 80],
            ['Responsivitas chat', 75],
            ['Riwayat interaksi', 60],
        ];

        return view('prospek', compact(
            'prospek',
            'tahap',
            'faktor'
        ));
    }

    // Menyimpan prospek baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor_telepon' => 'required|string|max:20',
            'jenjang' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:255',
            'skor' => 'nullable|integer|min:0|max:100',
            'tahap' => 'required|in:baru,dihubungi,tertarik,penawaran,daftar,batal',
            'sumber' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
        ]);

        $validated['skor'] = $validated['skor'] ?? 0;
        $validated['penanggung_jawab'] = session('user_id');

        Prospek::create($validated);

        return redirect()
            ->route('prospek')
            ->with('success', 'Prospek berhasil ditambahkan.');
    }
}
