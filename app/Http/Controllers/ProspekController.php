<?php

namespace App\Http\Controllers;

use App\Models\AktivitasProspek;
use App\Models\Kontak;
use App\Models\LogAudit;
use App\Models\Prospek;
use App\Models\User;
use App\Services\Prospek\SkorPrioritas;
use App\Support\AksesNomor;
use App\Support\Telepon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Halaman Prospek (F08): papan per tahap, skor prioritas beserta rinciannya, dan tindak lanjut. */
class ProspekController extends Controller
{
    public function index()
    {
        $pengguna = auth()->user();
        $nomorIds = AksesNomor::dapatDilihat($pengguna);

        $kueri = Prospek::with(['kontak.percakapan', 'penanggungJawab', 'aktivitas' => fn ($q) => $q->with('user')->orderByDesc('waktu')->orderByDesc('id')])
            ->orderByDesc('skor')->orderByDesc('id');

        // BR03: Agen/CS hanya prospek dari percakapan pada nomor yang ditugaskan.
        if ($nomorIds !== null) {
            $kueri->whereHas('kontak.percakapan', fn ($q) => $q->whereIn('nomor_whatsapp_id', $nomorIds));
        }

        $dikelola = AksesNomor::dapatDikelola($pengguna);

        $prospek = $kueri->get()->map(fn (Prospek $p) => [
            'id' => $p->id,
            'nama' => $p->kontak?->nama_tampil ?? '-',
            'telp' => $p->kontak?->nomor_telepon,
            'info' => trim(implode(' · ', array_filter([
                trim(($p->kontak?->jenjang ?? '') . ' ' . ($p->kontak?->kelas ? 'Kelas ' . $p->kontak->kelas : '')),
                $p->program,
            ]))),
            'mapel' => $p->kontak?->mapel,
            'sumber' => $p->kontak?->sumber,
            'program' => $p->program,
            'skor' => $p->skor,
            'warnaSkor' => $this->warnaSkor($p->skor),
            'rincian' => $p->rincian_skor ?? [],
            'tahap' => $p->tahap,
            'pj' => $p->penanggungJawab?->name,
            'tindakLanjut' => $p->tindak_lanjut_berikut?->format('Y-m-d'),
            'bisaUbah' => $this->bolehMengelola($dikelola, $p),
            // Percakapan terbaru pada nomor yang boleh dilihat pengguna (tautan dari detail prospek).
            'percakapanId' => $p->kontak?->percakapan
                ->filter(fn ($c) => $nomorIds === null || in_array((int) $c->nomor_whatsapp_id, $nomorIds, true))
                ->sortByDesc('pesan_terakhir_pada')->first()?->id,
            'aktivitas' => $p->aktivitas->take(15)->map(fn (AktivitasProspek $a) => [
                'jenis' => $a->jenis,
                'catatan' => $a->catatan,
                'oleh' => $a->user?->name ?? 'Sistem',
                'waktu' => $a->waktu?->diffForHumans(),
            ])->values()->all(),
        ])->values()->all();

        $tahap = [
            'baru' => 'Baru',
            'dihubungi' => 'Dihubungi',
            'tertarik' => 'Tertarik',
            'penawaran' => 'Penawaran',
            'daftar' => 'Daftar',
            'batal' => 'Batal',
        ];

        // Bobot yang sedang berlaku (config/prospek.php), bukan angka contoh.
        $bobot = (array) config('prospek.bobot');
        $totalBobot = array_sum($bobot) ?: 1;
        $faktor = [
            ['Kelengkapan data', $bobot['kelengkapan'] ?? 0],
            ['Urgensi', $bobot['urgensi'] ?? 0],
            ['Minat program', $bobot['minat'] ?? 0],
            ['Responsivitas chat', $bobot['responsivitas'] ?? 0],
            ['Riwayat interaksi', $bobot['riwayat'] ?? 0],
        ];
        $faktor = array_map(fn ($f) => [$f[0], (int) round($f[1] / $totalBobot * 100)], $faktor);

        return view('prospek', [
            'prospek' => $prospek,
            'tahap' => $tahap,
            'faktor' => $faktor,
            'prospekTerpilih' => session('prospek_terpilih'),
        ]);
    }

    // Menyimpan prospek baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nomor_telepon' => 'required|string|max:20',
            'jenjang' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:100',
            'tahap' => 'required|in:' . implode(',', Prospek::TAHAP),
            'sumber' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
        ]);

        // Setiap prospek terhubung ke satu kontak (SRS: prospek.kontak_id).
        $kontak = Kontak::firstOrCreate(
            ['nomor_telepon' => Telepon::normalisasi($validated['nomor_telepon'])],
            [
                'nama' => $validated['nama'],
                'jenjang' => $validated['jenjang'] ?? null,
                'sumber' => $validated['sumber'] ?? null,
            ]
        );

        $prospek = DB::transaction(function () use ($validated, $kontak) {
            $prospek = Prospek::create([
                'kontak_id' => $kontak->id,
                'penanggung_jawab' => auth()->id(),
                'tahap' => $validated['tahap'],
                'program' => $validated['program'] ?? null,
                'skor' => 0,
            ]);

            if (filled($validated['catatan'] ?? null)) {
                $prospek->aktivitas()->create([
                    'user_id' => auth()->id(), 'jenis' => 'catatan', 'catatan' => $validated['catatan'], 'waktu' => now(),
                ]);
            }

            return $prospek;
        });

        app(SkorPrioritas::class)->simpan($prospek);

        return redirect()
            ->route('prospek')
            ->with('success', 'Prospek berhasil ditambahkan.')
            ->with('prospek_terpilih', $prospek->id);
    }

    /**
     * Memindahkan tahap, mencatat aktivitas (wajib, BR05), dan menjadwalkan tindak lanjut.
     */
    public function tindakLanjut(Request $request, Prospek $prospek)
    {
        abort_unless($this->bolehMengelola(AksesNomor::dapatDikelola(auth()->user()), $prospek), 403, 'Kamu tidak memiliki izin mengelola prospek ini.');

        $data = $request->validate([
            'tahap' => 'required|in:' . implode(',', Prospek::TAHAP),
            'catatan' => 'required|string|max:2000',
            'tindak_lanjut_berikut' => 'nullable|date|after_or_equal:today',
        ], [
            'catatan.required' => 'Catatan wajib diisi.',
            'tindak_lanjut_berikut.after_or_equal' => 'Tanggal tindak lanjut tidak boleh sebelum hari ini.',
        ]);

        $catatan = trim($data['catatan']);
        if ($catatan === '') {
            return back()->withErrors(['catatan' => 'Catatan wajib diisi.'])->withInput();
        }

        $lama = $prospek->tahap;
        $berubah = $lama !== $data['tahap'];

        DB::transaction(function () use ($prospek, $data, $catatan, $lama, $berubah) {
            $prospek->update([
                'tahap' => $data['tahap'],
                'tindak_lanjut_berikut' => $data['tindak_lanjut_berikut'] ?? null,
                // Pemilik prospek: yang pertama kali menindaklanjuti bila belum ada.
                'penanggung_jawab' => $prospek->penanggung_jawab ?? auth()->id(),
            ]);

            $prospek->aktivitas()->create([
                'user_id' => auth()->id(),
                'jenis' => $berubah ? 'perubahan_tahap' : 'catatan',
                'catatan' => $berubah ? 'Tahap ' . ucfirst($lama) . ' → ' . ucfirst($data['tahap']) . '. ' . $catatan : $catatan,
                'waktu' => now(),
            ]);
        });

        // BR06: skor dihitung ulang setiap tahap atau aktivitas berubah.
        app(SkorPrioritas::class)->simpan($prospek);

        if ($berubah) {
            LogAudit::catat('Ubah tahap prospek', "Prospek #{$prospek->id}: {$lama} → {$data['tahap']}", 'prospek', null, $prospek->id, ['sebelum' => $lama, 'sesudah' => $data['tahap']]);
        }

        return redirect()
            ->route('prospek')
            ->with('success', $berubah ? 'Tahap prospek diperbarui dan aktivitas dicatat.' : 'Aktivitas dicatat.')
            ->with('prospek_terpilih', $prospek->id);
    }

    /** $dikelola: null = semua nomor; selain itu id nomor yang boleh dikelola pengguna. */
    private function bolehMengelola(?array $dikelola, Prospek $prospek): bool
    {
        return $dikelola === null
            || ($prospek->kontak && $prospek->kontak->percakapan()->whereIn('nomor_whatsapp_id', $dikelola)->exists());
    }

    private function warnaSkor(int $skor): string
    {
        return match (true) {
            $skor >= (int) config('prospek.ambang_tinggi') => 'tinggi',
            $skor >= (int) config('prospek.ambang_sedang') => 'sedang',
            default => 'rendah',
        };
    }
}
