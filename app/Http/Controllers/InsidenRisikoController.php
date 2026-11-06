<?php

namespace App\Http\Controllers;

use App\Models\InsidenRisiko;
use Illuminate\Http\Request;

class InsidenRisikoController extends Controller
{
    /**
     * Menambahkan insiden risiko baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_whatsapp_id' => 'nullable|exists:nomor_whatsapp,id',
            'jenis_insiden' => 'required|string|max:100',
            'tingkat_risiko' => 'required|in:Rendah,Sedang,Tinggi,Kritis',
            'deskripsi' => 'required|string',
        ]);

        $validated['status'] = 'Terbuka';
        $validated['terjadi_pada'] = now();

        InsidenRisiko::create($validated);

        return redirect()
            ->route('antrean-risiko')
            ->with('success', 'Insiden risiko berhasil ditambahkan.');
    }

    /**
     * Mengubah status insiden risiko.
     */
    public function ubahStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:Terbuka,Ditangani,Selesai',
        ]);

        $insiden = InsidenRisiko::findOrFail($id);

        $insiden->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('antrean-risiko')
            ->with('success', 'Status insiden berhasil diperbarui.');
    }

    /**
     * Menghapus insiden risiko.
     */
    public function destroy($id)
    {
        $insiden = InsidenRisiko::findOrFail($id);

        $insiden->delete();

        return redirect()
            ->route('antrean-risiko')
            ->with('success', 'Insiden risiko berhasil dihapus.');
    }
}
