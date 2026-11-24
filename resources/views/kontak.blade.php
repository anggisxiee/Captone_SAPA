@extends('layouts.app')
@section('title', 'Kontak')
@section('subtitle', $total . ' calon siswa dan wali murid')

@section('actions')
    <a href="{{ route('kontak.ekspor', request()->only('q', 'jenjang', 'sumber')) }}"
       class="btn btn-outline !text-[10px] !py-1.5">
        Ekspor CSV
    </a>
@endsection

@section('content')
@php
    $bolehUbah = fn ($k) => $dikelola === null
        || $k->percakapan->contains(fn ($p) => in_array((int) $p->nomor_whatsapp_id, $dikelola, true));

    // Form dibuka kembali dengan isian terakhir bila validasi gagal.
    $kontakGagal = old('_kontak_id') ? [
        'id' => (int) old('_kontak_id'),
        'nama' => old('nama'), 'nomor_telepon' => old('nomor_telepon'), 'jenjang' => old('jenjang'),
        'kelas' => old('kelas'), 'mapel' => old('mapel'), 'kota' => old('kota'), 'sumber' => old('sumber'), 'label' => old('label'),
    ] : null;
@endphp

@if ($errors->any())
    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div x-data="{ open: @js($kontakGagal !== null), c: @js($kontakGagal ?? new stdClass) }">

    {{-- Pencarian dan filter --}}
    <form method="GET" action="{{ route('kontak') }}" class="flex items-center gap-2 mb-3">
        <input name="q" value="{{ $q }}" class="field max-w-xs !text-[10px] !py-1.5" placeholder="Cari nama atau nomor">

        <select name="jenjang" class="field w-auto !text-[10px] !py-1.5" onchange="this.form.submit()">
            <option value="">Semua jenjang</option>
            @foreach ($daftarJenjang as $j)
                <option value="{{ $j }}" @selected($jenjang === $j)>{{ $j }}</option>
            @endforeach
        </select>

        <select name="sumber" class="field w-auto !text-[10px] !py-1.5" onchange="this.form.submit()">
            <option value="">Semua sumber</option>
            @foreach ($daftarSumber as $s)
                <option value="{{ $s }}" @selected($sumber === $s)>{{ $s }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline !text-[10px] !py-1.5">Cari</button>

        @if ($q !== '' || $jenjang !== '' || $sumber !== '')
            <a href="{{ route('kontak') }}" class="text-[10px] text-slate-500 underline">Atur ulang</a>
        @endif
    </form>

    {{-- Tabel kontak --}}
    <div class="card !p-0 overflow-hidden">
        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col style="width: 16%">
                <col style="width: 15%">
                <col style="width: 12%">
                <col style="width: 16%">
                <col style="width: 12%">
                <col style="width: 9%">
                <col style="width: 12%">
                <col style="width: 8%">
            </colgroup>

            <thead class="bg-slate-50">
                <tr>
                    @foreach (['Nama','Nomor WhatsApp','Jenjang','Mata pelajaran','Sumber','Tahap','Terakhir dihubungi','Aksi'] as $h)
                        <th class="th !text-[9px] !px-1.5 !py-2 text-left">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($kontak as $k)
                    @php $terakhir = $k->percakapan->max('pesan_terakhir_pada'); @endphp
                    <tr>
                        <td class="td !text-[10px] !px-1.5 !py-2 font-medium break-words">{{ $k->nama_tampil }}</td>
                        <td class="td !text-[9px] !px-1.5 !py-2 break-all">{{ $k->nomor_telepon }}</td>
                        <td class="td !text-[9px] !px-1.5 !py-2">{{ trim(($k->jenjang ?? '') . ' ' . ($k->kelas ? 'Kelas ' . $k->kelas : '')) ?: '-' }}</td>
                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">{{ $k->mapel ?: '-' }}</td>
                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">{{ $k->sumber ?: '-' }}</td>
                        <td class="td !px-1.5 !py-2">
                            <span class="badge b-slate !text-[8px] !px-1.5 !py-0.5">{{ $k->prospek ? ucfirst($k->prospek->tahap) : '-' }}</span>
                        </td>
                        <td class="td !text-[9px] !px-1.5 !py-2 break-words">{{ $terakhir ? \Illuminate\Support\Carbon::parse($terakhir)->diffForHumans() : '-' }}</td>
                        <td class="td !px-1.5 !py-2">
                            @if ($bolehUbah($k))
                                <button type="button"
                                    @click="c = @js(['id' => $k->id, 'nama' => $k->nama, 'nomor_telepon' => $k->nomor_telepon, 'jenjang' => $k->jenjang, 'kelas' => $k->kelas, 'mapel' => $k->mapel, 'kota' => $k->kota, 'sumber' => $k->sumber, 'label' => $k->label, 'percakapanId' => $k->percakapan->sortByDesc('pesan_terakhir_pada')->first()?->id]); open = true"
                                    class="btn btn-outline !text-[9px] !px-2 !py-1">
                                    Ubah
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="td !text-[11px] text-center text-slate-400 !py-6">
                            {{ ($q !== '' || $jenjang !== '' || $sumber !== '') ? 'Kontak tidak ditemukan.' : 'Belum ada kontak. Kontak muncul otomatis saat ada pesan masuk.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between mt-2 text-[10px] text-slate-400">
        <span>
            @if ($kontak->total() > 0)
                Menampilkan {{ $kontak->firstItem() }}–{{ $kontak->lastItem() }} dari {{ $kontak->total() }} kontak
            @endif
        </span>
        <span class="flex gap-2">
            @if ($kontak->previousPageUrl())<a href="{{ $kontak->previousPageUrl() }}" class="underline">‹ Sebelumnya</a>@endif
            @if ($kontak->nextPageUrl())<a href="{{ $kontak->nextPageUrl() }}" class="underline">Berikutnya ›</a>@endif
        </span>
    </div>

    {{-- Modal ubah kontak --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4"
         @keydown.escape.window="open=false">
        <div @click.outside="open=false" class="bg-white rounded-xl p-4 w-full max-w-md space-y-3 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Ubah kontak</h3>
                <button type="button" @click="open=false" class="text-slate-400 text-sm">✕</button>
            </div>

            <form method="POST" :action="'{{ url('/kontak') }}/' + c.id">
                @csrf
                @method('PUT')
                <input type="hidden" name="_kontak_id" :value="c.id">
                {{-- Filter aktif dibawa lewat nama berawalan f_ agar tidak bentrok dengan kolom kontak. --}}
                @foreach (['q' => $q, 'jenjang' => $jenjang, 'sumber' => $sumber] as $nama => $nilai)
                    <input type="hidden" name="f_{{ $nama }}" value="{{ $nilai }}">
                @endforeach

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="label !text-[10px]">Nama lengkap</label>
                        <input name="nama" class="field !text-[11px] !py-1.5" x-model="c.nama" maxlength="255">
                    </div>

                    <div>
                        <label class="label !text-[10px]">Nomor WhatsApp</label>
                        <input name="nomor_telepon" required class="field !text-[11px] !py-1.5" x-model="c.nomor_telepon">
                        <p class="text-[9px] text-slate-400 mt-1">Nomor tidak boleh sama dengan kontak lain.</p>
                    </div>

                    <div>
                        <label class="label !text-[10px]">Jenjang</label>
                        <input name="jenjang" class="field !text-[11px] !py-1.5" x-model="c.jenjang" maxlength="100">
                    </div>

                    <div>
                        <label class="label !text-[10px]">Kelas</label>
                        <input name="kelas" class="field !text-[11px] !py-1.5" x-model="c.kelas" maxlength="50">
                    </div>

                    <div class="col-span-2">
                        <label class="label !text-[10px]">Mata pelajaran peminatan</label>
                        <input name="mapel" class="field !text-[11px] !py-1.5" x-model="c.mapel" maxlength="255">
                    </div>

                    <div>
                        <label class="label !text-[10px]">Kota</label>
                        <input name="kota" class="field !text-[11px] !py-1.5" x-model="c.kota" maxlength="100">
                    </div>

                    <div>
                        <label class="label !text-[10px]">Sumber</label>
                        <input name="sumber" class="field !text-[11px] !py-1.5" x-model="c.sumber" maxlength="100">
                    </div>

                    <div class="col-span-2">
                        <label class="label !text-[10px]">Label</label>
                        <input name="label" class="field !text-[11px] !py-1.5" x-model="c.label" maxlength="100" placeholder="mis. VIP, tindak lanjut minggu ini">
                    </div>
                </div>

                <p class="text-[9px] text-slate-400 mt-2">
                    Tahap prospek diubah di halaman Prospek.
                    <a x-show="c.percakapanId" :href="'{{ route('kotak-masuk') }}?percakapan=' + c.percakapanId" class="text-teal-700 underline">Lihat riwayat percakapan</a>
                </p>

                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" @click="open=false" class="btn btn-outline !text-[10px] !py-1.5 !px-2.5">Batal</button>
                    <button type="submit" class="btn btn-primary !text-[10px] !py-1.5 !px-2.5">Simpan</button>
                </div>
            </form>

            @if (auth()->user()->role === 'Administrator')
                <form method="POST" :action="'{{ url('/kontak') }}/' + c.id"
                      onsubmit="return confirm('Hapus kontak ini beserta semua percakapan, pesan, dan prospeknya? Tindakan ini tidak dapat dibatalkan.')"
                      class="border-t border-slate-100 pt-3 flex items-center justify-between">
                    @csrf
                    @method('DELETE')
                    <span class="text-[9px] text-slate-400">Menghapus juga percakapan, pesan, dan prospeknya.</span>
                    <button type="submit" class="btn btn-danger !text-[10px] !py-1.5 !px-2.5">Hapus kontak</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
