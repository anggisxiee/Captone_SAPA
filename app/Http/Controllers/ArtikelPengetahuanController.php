<?php

namespace App\Http\Controllers;

use App\Models\ArtikelPengetahuan;
use App\Models\LogAudit;
use App\Services\Pengetahuan\PencarianArtikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtikelPengetahuanController extends Controller
{
    private const PESAN_WAJIB = 'Judul dan isi wajib diisi.';

    /** Daftar artikel dapat dilihat semua pengguna; mengelola khusus Supervisor dan Administrator (F12). */
    public function index(Request $request)
    {
        $kategori = array_key_exists((string) $request->query('kategori'), ArtikelPengetahuan::KATEGORI)
            ? $request->query('kategori')
            : null;
        $status = in_array($request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : null;
        $kata = trim((string) $request->query('q', ''));

        $daftar = ArtikelPengetahuan::query()
            ->when($kategori, fn ($q) => $q->where('kategori', $kategori))
            ->when($status, fn ($q) => $q->where('aktif', $status === 'aktif'))
            ->when($kata !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('judul', 'like', "%{$kata}%")
                ->orWhere('label', 'like', "%{$kata}%")))
            ->orderByDesc('aktif')
            ->orderBy('judul')
            ->get();

        $hitung = ArtikelPengetahuan::selectRaw('kategori, count(*) as jumlah')->groupBy('kategori')->pluck('jumlah', 'kategori');

        return view('basis-pengetahuan', [
            'daftar' => $daftar,
            'hitung' => $hitung,
            'total' => $hitung->sum(),
            'filter' => ['kategori' => $kategori, 'status' => $status, 'q' => $kata],
            'bisaKelola' => in_array(auth()->user()->role, ['Supervisor', 'Administrator'], true),
            'ambang' => (float) config('ai.ambang_relevansi'),
        ]);
    }

    public function store(Request $request)
    {
        $artikel = ArtikelPengetahuan::create($this->validasi($request));

        LogAudit::catat(
            'Tambah artikel',
            "Artikel {$artikel->kode}: {$artikel->judul}",
            'artikel_pengetahuan',
            null,
            $artikel->id,
            ['judul' => $artikel->judul, 'kategori' => $artikel->kategori]
        );

        return redirect()->route('basis-pengetahuan')
            ->with('success', "Artikel {$artikel->kode} ditambahkan dan langsung dapat ditemukan.");
    }

    public function update(Request $request, ArtikelPengetahuan $artikel)
    {
        $artikel->update($this->validasi($request));

        $berubah = array_values(array_diff(array_keys($artikel->getChanges()), ['updated_at']));

        if (!$berubah) {
            return redirect()->route('basis-pengetahuan')->with('success', 'Tidak ada perubahan pada artikel.');
        }

        $aksi = match (true) {
            $berubah === ['aktif'] && $artikel->aktif => 'Aktifkan artikel',
            $berubah === ['aktif'] => 'Nonaktifkan artikel',
            default => 'Ubah artikel',
        };

        LogAudit::catat($aksi, "Artikel {$artikel->kode}: {$artikel->judul}", 'artikel_pengetahuan', null, $artikel->id, ['diubah' => $berubah]);

        return redirect()->route('basis-pengetahuan')->with('success', "Artikel {$artikel->kode} disimpan.");
    }

    /** "Uji Pencarian" (F12 4a): artikel paling relevan beserta skornya. */
    public function uji(Request $request, PencarianArtikel $pencarian): JsonResponse
    {
        $data = $request->validate(['pertanyaan' => 'required|string|max:500'], [
            'pertanyaan.required' => 'Pertanyaan uji wajib diisi.',
        ]);

        $ambang = (float) config('ai.ambang_relevansi');

        return response()->json([
            'ambang' => $ambang,
            'istilah' => $pencarian->istilah($data['pertanyaan']),
            'hasil' => array_map(fn ($h) => [
                'kode' => $h['artikel']->kode,
                'judul' => $h['artikel']->judul,
                'kategori' => $h['artikel']->nama_kategori,
                'skor' => $h['skor'],
                'cakupan' => $h['cakupan'],
                'dipakai' => $h['skor'] >= $ambang,
            ], $pencarian->cari($data['pertanyaan'], 5)),
        ]);
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'kategori' => ['required', Rule::in(array_keys(ArtikelPengetahuan::KATEGORI))],
            'isi' => 'required|string|max:20000',
            'label' => 'nullable|string|max:255',
        ], [
            'judul.required' => self::PESAN_WAJIB,
            'isi.required' => self::PESAN_WAJIB,
            'kategori.in' => 'Kategori tidak valid.',
        ]);

        $data['aktif'] = $request->boolean('aktif');
        $data['label'] = ($data['label'] ?? '') === '' ? null : $data['label'];

        return $data;
    }
}
