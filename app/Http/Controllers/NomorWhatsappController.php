<?php

namespace App\Http\Controllers;

use App\Models\NomorWhatsapp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NomorWhatsappController extends Controller
{
    // Menampilkan daftar nomor WhatsApp
    public function index()
    {
        $nomorWhatsapp = NomorWhatsapp::orderBy('nama')->get();

        return view('nomor-whatsapp', compact('nomorWhatsapp'));
    }

    // Menyimpan nomor WhatsApp baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor' => 'required|string|max:25|unique:nomor_whatsapp,nomor',
        ]);

        NomorWhatsapp::create([
            'nama' => $validated['nama'],
            'nomor' => $validated['nomor'],
            'status' => 'Aktif',
        ]);

        return redirect()
            ->route('nomor-whatsapp')
            ->with('success', 'Nomor WhatsApp berhasil ditambahkan.');
    }

    // Memperbarui nomor WhatsApp
    public function update(Request $request, NomorWhatsapp $nomorWhatsapp)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor' => [
                'required',
                'string',
                'max:25',
                Rule::unique('nomor_whatsapp', 'nomor')->ignore($nomorWhatsapp->id),
            ],
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
}
