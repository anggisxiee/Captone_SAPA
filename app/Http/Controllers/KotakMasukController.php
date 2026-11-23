<?php

namespace App\Http\Controllers;

use App\Jobs\KirimPesanKeluar;
use App\Models\AntreanPesan;
use App\Models\LogAudit;
use App\Models\NomorWhatsapp;
use App\Models\Percakapan;
use App\Models\PesanWhatsapp;
use App\Models\Prospek;
use App\Models\User;
use App\Support\Telepon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KotakMasukController extends Controller
{
    private const HAK_MEMBALAS = ['Balas chat', 'Kelola'];

    /**
     * Menampilkan halaman Kotak Masuk.
     */
    public function index()
    {
        $pengguna = auth()->user();

        $nomorWhatsapp = $this->nomorDapatDilihat($pengguna);
        $nomorBolehBalas = $this->nomorBolehBalas($pengguna);

        [$daftar, $jumlahBelumDibalas] = $this->muatDaftar($nomorWhatsapp);

        // Percakapan yang dipilih kembali setelah membalas atau mengubah status.
        $idTerpilih = session('percakapan_terpilih');
        $chatAwal = collect($daftar)->firstWhere('id', $idTerpilih)
            ?? ($daftar[0] ?? null);

        return view('kotak-masuk', [
            'penggunaLogin' => $pengguna,
            'nomorWhatsapp' => $nomorWhatsapp,
            'nomorBolehBalas' => $nomorBolehBalas,
            'daftar' => $daftar,
            'chatAwal' => $chatAwal,
            'jumlahBelumDibalas' => $jumlahBelumDibalas,
        ]);
    }

    /**
     * Data percakapan terbaru untuk pembaruan otomatis halaman (polling).
     */
    public function data()
    {
        [$daftar, $jumlah] = $this->muatDaftar($this->nomorDapatDilihat(auth()->user()));

        return response()->json(['daftar' => $daftar, 'jumlahBelumDibalas' => $jumlah]);
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: int} */
    private function muatDaftar(Collection $nomorWhatsapp): array
    {
        $percakapan = Percakapan::with([
            'kontak',
            'nomorWhatsapp',
            'pesan' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
        ])
            ->whereIn('nomor_whatsapp_id', $nomorWhatsapp->pluck('id'))
            ->orderByDesc('pesan_terakhir_pada')
            ->orderByDesc('id')
            ->get();

        $prospek = Prospek::whereIn(
            'nomor_telepon',
            $percakapan->pluck('kontak.nomor_telepon')
        )->get()->keyBy('nomor_telepon');

        $daftar = $percakapan
            ->map(fn (Percakapan $p) => $this->ubahKeBentukTampilan(
                $p,
                $prospek->get($p->kontak->nomor_telepon)
            ))
            ->values()
            ->all();

        return [$daftar, $percakapan->filter(fn (Percakapan $p) => $p->belum_dibalas)->count()];
    }

    /**
     * Menyimpan pesan balasan agen dan memasukkannya ke antrean pesan.
     */
    public function kirimPesan(Request $request)
    {
        $request->validate([
            'percakapan_id' => 'required|exists:percakapan,id',
            'isi' => 'required|string|max:5000',
        ]);

        $percakapan = Percakapan::with(['kontak', 'nomorWhatsapp'])
            ->findOrFail($request->percakapan_id);

        $this->pastikanBolehMembalas(auth()->user(), $percakapan->nomor_whatsapp_id);

        $this->simpanBalasan($percakapan, $request->isi);

        return redirect()
            ->route('kotak-masuk')
            ->with('success', 'Pesan berhasil disimpan dan dimasukkan ke antrean.')
            ->with('percakapan_terpilih', $percakapan->id);
    }

    /**
     * Memulai percakapan ke nomor pelanggan (tombol "Pesan baru").
     */
    public function pesanBaru(Request $request)
    {
        if (is_string($request->telepon)) {
            $request->merge(['telepon' => Telepon::normalisasi($request->telepon)]);
        }

        $data = $request->validate([
            'nomor_whatsapp_id' => 'required|exists:nomor_whatsapp,id',
            'telepon' => ['required', 'regex:/^62\d{8,13}$/'],
            'nama' => 'nullable|string|max:255',
            'isi' => 'required|string|max:5000',
        ], [
            'telepon.regex' => 'Nomor tujuan tidak valid. Contoh: 081234567890.',
        ]);

        $this->pastikanBolehMembalas(auth()->user(), (int) $data['nomor_whatsapp_id']);

        $nomor = NomorWhatsapp::findOrFail($data['nomor_whatsapp_id']);

        // Tidak boleh mengirim ke nomor WhatsApp milik SAPA sendiri.
        if (NomorWhatsapp::where('nomor', $data['telepon'])->exists()) {
            return back()->withErrors(['telepon' => 'Nomor tujuan adalah nomor WhatsApp SAPA sendiri.'])->withInput();
        }

        $percakapan = Percakapan::untuk($nomor, $data['telepon'], $data['nama'] ?: $data['telepon']);
        $percakapan->load(['kontak', 'nomorWhatsapp']);

        $this->simpanBalasan($percakapan, $data['isi']);

        return redirect()
            ->route('kotak-masuk')
            ->with('success', 'Pesan dimasukkan ke antrean.')
            ->with('percakapan_terpilih', $percakapan->id);
    }

    private function simpanBalasan(Percakapan $percakapan, string $isi): void
    {
        $nomor = $percakapan->nomorWhatsapp;
        $kontak = $percakapan->kontak;

        $antrean = DB::transaction(function () use ($isi, $percakapan, $nomor, $kontak) {
            // Dihitung sebelum balasan ini tersimpan agar pesan masuk pertama terbaca.
            $percakapan->catatBalasanPertama();

            $percakapan->update([
                'status' => 'Ditangani Agen',
                'agen_id' => auth()->id(),
                'belum_dibaca' => 0,
                'pesan_terakhir_pada' => now(),
            ]);

            $pesan = PesanWhatsapp::create([
                'percakapan_id' => $percakapan->id,
                'arah' => 'keluar',
                'jenis_pengirim' => 'agen',
                'isi' => $isi,
                'status' => 'menunggu',
            ]);

            return AntreanPesan::create([
                'nomor_whatsapp_id' => $nomor->id,
                'pesan_whatsapp_id' => $pesan->id,
                'pengirim' => $nomor->label,
                'penerima' => $kontak->nomor_telepon,
                'isi_pesan' => $isi,
                'skor_risiko' => 0,
                'keputusan' => 'Kirim',
                'percobaan' => 0,
                'maks_percobaan' => 3,
                'jadwal_kirim' => now(),
            ]);
        });

        KirimPesanKeluar::dispatch($antrean->id);
    }

    /**
     * Mengubah status percakapan: ambil alih, kembalikan ke AI, atau selesai.
     */
    public function ubahStatus(Request $request, Percakapan $percakapan)
    {
        $request->validate([
            'status' => 'required|in:Ditangani Agen,Ditangani AI,Selesai',
        ]);

        $this->pastikanBolehMembalas(auth()->user(), $percakapan->nomor_whatsapp_id);

        $lama = $percakapan->status;
        $perubahan = ['status' => $request->status];

        // Mengambil alih menugaskan percakapan ke pengguna ini; dikembalikan ke AI melepasnya.
        if ($request->status === 'Ditangani Agen') {
            $perubahan['agen_id'] = auth()->id();
        } elseif ($request->status === 'Ditangani AI') {
            $perubahan['agen_id'] = null;
        }

        $percakapan->update($perubahan);

        LogAudit::catat(
            'Ubah status percakapan',
            "Percakapan #{$percakapan->id}: {$lama} → {$request->status}",
            'percakapan',
            null,
            $percakapan->id,
            ['sebelum' => $lama, 'sesudah' => $request->status]
        );

        return redirect()
            ->route('kotak-masuk')
            ->with('success', 'Status percakapan diubah menjadi "' . $request->status . '".')
            ->with('percakapan_terpilih', $percakapan->id);
    }

    /**
     * Nomor WhatsApp yang boleh dilihat pengguna.
     */
    private function nomorDapatDilihat(User $pengguna): Collection
    {
        // BR03: Supervisor membaca semua nomor, Administrator memiliki semua fitur.
        // Agen/CS hanya nomor yang ditugaskan kepadanya.
        if (in_array($pengguna->role, ['Administrator', 'Supervisor'], true)) {
            return NomorWhatsapp::orderBy('label')->get();
        }

        return $pengguna->nomorWhatsapp()
            ->wherePivotIn('hak_akses', ['Lihat saja', ...self::HAK_MEMBALAS])
            ->orderBy('nomor_whatsapp.label')
            ->get();
    }

    /**
     * ID nomor WhatsApp yang boleh dipakai pengguna untuk membalas.
     */
    private function nomorBolehBalas(User $pengguna): array
    {
        if ($pengguna->role === 'Administrator') {
            return NomorWhatsapp::pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $pengguna->nomorWhatsapp()
            ->wherePivotIn('hak_akses', self::HAK_MEMBALAS)
            ->pluck('nomor_whatsapp.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function pastikanBolehMembalas(User $pengguna, int $nomorWhatsappId): void
    {
        if (!in_array($nomorWhatsappId, $this->nomorBolehBalas($pengguna), true)) {
            abort(403, 'Kamu tidak memiliki izin membalas pesan.');
        }
    }

    /**
     * Mengubah satu percakapan menjadi struktur yang dipakai view.
     */
    private function ubahKeBentukTampilan(Percakapan $p, ?Prospek $prospek): array
    {
        $kontak = $p->kontak;

        // Eskalasi AI: diserahkan ke agen tanpa agen yang ditugaskan (BR08: AI berhenti).
        $eskalasiAi = $p->status === 'Ditangani Agen' && $p->agen_id === null;

        [$label, $warna] = match (true) {
            $eskalasiAi => ['Eskalasi AI', 'b-red'],
            $p->belum_dibalas => ['Belum dibalas', 'b-amber'],
            $p->status === 'Ditangani AI' => ['Ditangani AI', 'b-green'],
            $p->status === 'Ditangani Agen' => ['Ditangani agen', 'b-slate'],
            $p->status === 'Selesai' => ['Selesai', 'b-slate'],
            default => ['Baru', 'b-amber'],
        };

        // Saran AI berstatus "draf" bukan bagian percakapan: hanya tampil di panel Keputusan AI.
        $jejak = $p->pesan->where('jenis_pengirim', 'ai')->whereNotNull('meta_ai')->last();
        $meta = $jejak?->meta_ai ?? [];
        $judulArtikel = collect($meta['sumber'] ?? [])->pluck('judul')->implode('; ');

        $pesan = $p->pesan->where('status', '!=', 'draf')->map(fn (PesanWhatsapp $m) => [
            'pengirim' => $m->jenis_pengirim,
            'isi' => $m->isi,
            'jam' => $m->created_at?->format('H:i') ?? '',
        ])->values()->all();

        $terakhir = $p->pesan->where('status', '!=', 'draf')->last();

        return [
            'id' => $p->id,
            'nama' => $kontak->nama_tampil,
            'telepon' => $kontak->nomor_telepon,
            'jam' => $this->formatWaktu($p->pesan_terakhir_pada),
            'cuplikan' => $terakhir->isi ?? 'Belum ada pesan.',
            'nomor' => $p->nomorWhatsapp->label,
            'nomorId' => $p->nomor_whatsapp_id,
            'status' => $label,
            'statusAsli' => $p->status,
            'belumDibalas' => $p->belum_dibalas,
            'warna' => $warna,
            'jenjang' => trim(($kontak->jenjang ?? '') . ' ' . ($kontak->kelas ?? '')) ?: '-',
            'mapel' => $kontak->mapel ?: '-',
            'cabang' => '-',
            'sumber' => $prospek?->sumber ?: '-',
            'tahap' => $prospek ? ucfirst($prospek->tahap) : '-',
            'skor' => $prospek ? "{$prospek->skor} / 100" : '-',
            // Keputusan AI terbaru dari meta_ai pesan AI (draf atau terkirim).
            'maksud' => $meta['maksud'] ?? '-',
            'keyakinan' => isset($meta['keyakinan']) ? number_format($meta['keyakinan'], 2) : '-',
            'ambang' => isset($meta['ambang']) ? number_format($meta['ambang'], 2) : '-',
            'artikel' => $judulArtikel ?: '-',
            'keputusanAi' => $meta ? (($meta['keputusan'] ?? '') === 'eskalasi'
                ? 'Eskalasi: ' . (\App\Services\Ai\KeputusanAi::ALASAN[$meta['alasan'] ?? ''] ?? '-')
                : 'Jawab dari artikel') : '-',
            'modeAi' => $meta ? (($jejak->status === 'draf') ? 'Saran (belum dikirim)' : 'Otomatis (terkirim)') : '-',
            'saranAi' => ($jejak && $jejak->status === 'draf') ? $jejak->isi : '',
            'pesan' => $pesan,
        ];
    }

    private function formatWaktu(?Carbon $waktu): string
    {
        if (!$waktu) {
            return '';
        }

        if ($waktu->isToday()) {
            return $waktu->format('H:i');
        }

        return $waktu->isYesterday() ? 'Kemarin' : $waktu->format('d/m');
    }
}
