<?php

namespace App\Http\Controllers;

use App\Models\Kontak;
use App\Models\LogAudit;
use App\Models\PesanWhatsapp;
use App\Models\Prospek;
use App\Models\User;
use App\Services\Prospek\SkorPrioritas;
use App\Support\AksesNomor;
use App\Support\Telepon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Halaman Kontak (F03, F09): daftar calon siswa dan wali murid beserta form ubah data. */
class KontakController extends Controller
{
    public function index(Request $request)
    {
        $kontak = $this->kueri($request)
            ->with(['prospek', 'percakapan' => fn ($q) => $q->select('id', 'kontak_id', 'nomor_whatsapp_id', 'pesan_terakhir_pada')])
            ->paginate(25)
            ->withQueryString();

        return view('kontak', [
            'kontak' => $kontak,
            'total' => $kontak->total(),
            'daftarJenjang' => Kontak::whereNotNull('jenjang')->where('jenjang', '!=', '')->distinct()->orderBy('jenjang')->pluck('jenjang'),
            'daftarSumber' => Kontak::whereNotNull('sumber')->where('sumber', '!=', '')->distinct()->orderBy('sumber')->pluck('sumber'),
            'q' => (string) $request->query('q', ''),
            'jenjang' => (string) $request->query('jenjang', ''),
            'sumber' => (string) $request->query('sumber', ''),
            // null = boleh mengubah semua kontak; selain itu daftar id nomor yang dikelola.
            'dikelola' => $this->dapatDikelola(auth()->user()),
        ]);
    }

    public function update(Request $request, Kontak $kontak)
    {
        abort_unless($this->bolehMengubah(auth()->user(), $kontak), 403, 'Kamu tidak memiliki izin mengubah kontak ini.');

        if (is_string($request->nomor_telepon)) {
            $request->merge(['nomor_telepon' => Telepon::normalisasi($request->nomor_telepon)]);
        }

        $data = $request->validate([
            'nama' => 'nullable|string|max:255',
            // BR04: nomor valid dan tidak boleh duplikat.
            'nomor_telepon' => ['required', 'regex:/^62\d{8,13}$/', Rule::unique('kontak', 'nomor_telepon')->ignore($kontak->id)],
            'jenjang' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:50',
            'mapel' => 'nullable|string|max:255',
            'kota' => 'nullable|string|max:100',
            'sumber' => 'nullable|string|max:100',
        ], [
            'nomor_telepon.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'nomor_telepon.unique' => 'Nomor WhatsApp sudah dipakai kontak lain.',
        ]);

        // Kolom yang dikosongkan menjadi NULL, bukan string kosong.
        $data = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $data);

        $kontak->update($data);

        // BR06: kelengkapan data berubah, jadi skor prospek dihitung ulang.
        foreach (Prospek::where('kontak_id', $kontak->id)->get() as $prospek) {
            app(SkorPrioritas::class)->simpan($prospek);
        }

        $berubah = array_values(array_diff(array_keys($kontak->getChanges()), ['updated_at']));
        if ($berubah) {
            LogAudit::catat('Ubah kontak', "Kontak #{$kontak->id} diubah: " . implode(', ', $berubah), 'kontak', null, $kontak->id, ['diubah' => $berubah]);
        }

        $filter = array_filter([
            'q' => $request->input('f_q'), 'jenjang' => $request->input('f_jenjang'), 'sumber' => $request->input('f_sumber'),
        ], fn ($v) => is_string($v) && $v !== '');

        return redirect()->route('kontak', $filter)
            ->with('success', 'Kontak berhasil diperbarui.');
    }

    /**
     * Menghapus kontak beserta percakapan, pesan, dan prospeknya (khusus Administrator).
     * Riwayat antrean keluar tetap ada untuk keperluan audit pengiriman.
     */
    public function destroy(Kontak $kontak)
    {
        $ringkas = DB::transaction(function () use ($kontak) {
            $idPercakapan = $kontak->percakapan()->pluck('id');
            $jumlahPesan = PesanWhatsapp::whereIn('percakapan_id', $idPercakapan)->count();

            PesanWhatsapp::whereIn('percakapan_id', $idPercakapan)->delete();
            $kontak->percakapan()->delete();
            Prospek::where('kontak_id', $kontak->id)->delete();
            $kontak->delete();

            return ['nomor' => $kontak->nomor_telepon, 'percakapan' => $idPercakapan->count(), 'pesan' => $jumlahPesan];
        });

        LogAudit::catat('Hapus kontak', "Kontak {$ringkas['nomor']} dihapus beserta {$ringkas['percakapan']} percakapan dan {$ringkas['pesan']} pesan", 'kontak', null, $kontak->id, $ringkas);

        return redirect()->route('kontak')->with('success', 'Kontak dan seluruh percakapannya dihapus.');
    }

    /** Ekspor CSV mengikuti pencarian dan filter yang sedang aktif. */
    public function ekspor(Request $request): StreamedResponse
    {
        $kueri = $this->kueri($request)->with('prospek');

        LogAudit::catat('Ekspor kontak', 'Ekspor kontak ke CSV', 'kontak');

        return response()->streamDownload(function () use ($kueri) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Nomor WhatsApp', 'Jenjang', 'Kelas', 'Mata pelajaran', 'Kota', 'Sumber', 'Tahap']);
            foreach ($kueri->lazyById() as $k) {
                fputcsv($out, array_map([$this, 'amankanCsv'], [
                    $k->nama, $k->nomor_telepon, $k->jenjang, $k->kelas, $k->mapel, $k->kota, $k->sumber, $k->prospek?->tahap,
                ]));
            }
            fclose($out);
        }, 'kontak-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Sel yang diawali = + - @ dibaca sebagai rumus oleh spreadsheet; diberi tanda petik. */
    private function amankanCsv(?string $nilai): string
    {
        $nilai = (string) $nilai;

        return $nilai !== '' && str_contains('=+-@', $nilai[0]) ? "'" . $nilai : $nilai;
    }

    private function kueri(Request $request): Builder
    {
        $kueri = Kontak::query()->orderByDesc('id');

        // BR03: Agen/CS hanya melihat kontak yang percakapannya ada di nomor yang ditugaskan.
        $nomorIds = $this->dapatDilihat(auth()->user());
        if ($nomorIds !== null) {
            $kueri->whereHas('percakapan', fn ($q) => $q->whereIn('nomor_whatsapp_id', $nomorIds));
        }

        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $angka = Telepon::normalisasi($q);
            $kueri->where(function (Builder $w) use ($q, $angka) {
                $w->where('nama', 'like', '%' . addcslashes($q, '%_\\') . '%');
                if (strlen($angka) >= 3) {
                    $w->orWhere('nomor_telepon', 'like', '%' . $angka . '%');
                }
            });
        }
        if (($j = (string) $request->query('jenjang', '')) !== '') {
            $kueri->where('jenjang', $j);
        }
        if (($s = (string) $request->query('sumber', '')) !== '') {
            $kueri->where('sumber', $s);
        }

        return $kueri;
    }

    private function dapatDilihat(User $pengguna): ?array
    {
        return AksesNomor::dapatDilihat($pengguna);
    }

    private function dapatDikelola(User $pengguna): ?array
    {
        return AksesNomor::dapatDikelola($pengguna);
    }

    private function bolehMengubah(User $pengguna, Kontak $kontak): bool
    {
        $nomorIds = $this->dapatDikelola($pengguna);

        return $nomorIds === null
            || $kontak->percakapan()->whereIn('nomor_whatsapp_id', $nomorIds)->exists();
    }
}
