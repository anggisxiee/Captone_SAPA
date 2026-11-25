@extends('layouts.app')
@section('title', 'Basis Pengetahuan')
@section('subtitle', 'Artikel rujukan AI, dicari dengan BM25 (SQLite FTS5)')

@section('actions')
    @if ($bisaKelola)
        {{-- x-data diperlukan agar Alpine memproses @click di header --}}
        <div x-data>
            <button type="button" class="btn btn-primary !text-[10px] !px-3 !py-1.5"
                @click="$dispatch('artikel-baru')">
                Tambah artikel
            </button>
        </div>
    @endif
@endsection

@section('content')
@php
    $url = fn (array $ubah = []) => route('basis-pengetahuan', array_filter(array_merge($filter, $ubah), fn ($v) => $v !== null && $v !== ''));
    $galat = collect($errors->all())->unique();
@endphp

@if ($galat->isNotEmpty())
    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700">
        @foreach ($galat as $g)
            <div>{{ $g }}</div>
        @endforeach
    </div>
@endif

<div
    x-data="{
        buka: {{ old('_mode') ? 'true' : 'false' }},
        mode: @js(old('_mode', 'tambah')),
        id: @js(old('_id')),
        form: {
            judul: @js(old('judul', '')),
            kategori: @js(old('kategori', 'program')),
            isi: @js(old('isi', '')),
            label: @js(old('label', '')),
            aktif: {{ old('_mode') ? (old('aktif') ? 'true' : 'false') : 'true' }}
        },
        urlUbah: @js(route('basis-pengetahuan.update', ['artikel' => '__ID__'])),
        urlTambah: @js(route('basis-pengetahuan.store')),
        baru() {
            this.mode = 'tambah'; this.id = null;
            this.form = { judul: '', kategori: 'program', isi: '', label: '', aktif: true };
            this.buka = true;
        },
        ubah(a) {
            this.mode = 'ubah'; this.id = a.id;
            this.form = { judul: a.judul, kategori: a.kategori, isi: a.isi, label: a.label || '', aktif: a.aktif };
            this.buka = true;
        },
        aksi() { return this.mode === 'ubah' ? this.urlUbah.replace('__ID__', this.id) : this.urlTambah; }
    }"
    @artikel-baru.window="baru()"
    class="grid grid-cols-[minmax(0,1fr)_230px] gap-3 text-xs items-stretch"
>

    {{-- Bagian kiri --}}
    <div class="grid grid-cols-[145px_minmax(0,1fr)] gap-3 items-stretch min-w-0">

        {{-- Kategori --}}
        <div class="card !p-3 h-full">
            <div class="font-semibold text-xs mb-2">Kategori</div>

            <a href="{{ $url(['kategori' => null]) }}"
                class="flex justify-between items-center gap-1 rounded-md px-2 py-1.5 text-[10px]
                    {{ !$filter['kategori'] ? 'bg-teal-50 text-teal-700 font-medium' : 'text-slate-600 hover:bg-slate-50' }}">
                <span>Semua</span><span class="shrink-0">{{ $total }}</span>
            </a>

            @foreach (\App\Models\ArtikelPengetahuan::KATEGORI as $kunci => $nama)
                <a href="{{ $url(['kategori' => $kunci]) }}"
                    class="flex justify-between items-center gap-1 rounded-md px-2 py-1.5 text-[10px]
                        {{ $filter['kategori'] === $kunci ? 'bg-teal-50 text-teal-700 font-medium' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>{{ $nama }}</span><span class="shrink-0">{{ $hitung[$kunci] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        {{-- Daftar artikel --}}
        <div class="min-w-0 flex flex-col">
            <form method="GET" action="{{ route('basis-pengetahuan') }}" class="flex gap-2 mb-2">
                @if ($filter['kategori'])
                    <input type="hidden" name="kategori" value="{{ $filter['kategori'] }}">
                @endif
                <input type="search" name="q" value="{{ $filter['q'] }}"
                    class="field !text-[10px] !py-1.5 min-w-0 flex-1" placeholder="Cari judul atau kata kunci">
                <select name="status" onchange="this.form.submit()" class="field w-24 !text-[10px] !py-1.5 shrink-0">
                    <option value="">Status</option>
                    <option value="aktif" @selected($filter['status'] === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($filter['status'] === 'nonaktif')>Nonaktif</option>
                </select>
            </form>

            <div class="card !p-0 overflow-hidden flex-1">
                <table class="w-full table-fixed border-collapse">
                    <colgroup>
                        <col style="width: 15%">
                        <col style="width: 38%">
                        <col style="width: 29%">
                        <col style="width: 18%">
                    </colgroup>

                    <thead class="bg-slate-50">
                        <tr>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Kode</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Judul artikel</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Kategori</th>
                            <th class="th !text-[9px] !px-1.5 !py-2 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($daftar as $a)
                            <tr class="hover:bg-slate-50 {{ $bisaKelola ? 'cursor-pointer' : '' }}"
                                @if ($bisaKelola)
                                    @click="ubah(@js(['id' => $a->id, 'judul' => $a->judul, 'kategori' => $a->kategori, 'isi' => $a->isi, 'label' => $a->label, 'aktif' => $a->aktif]))"
                                @endif>
                                <td class="td !text-[9px] !px-1.5 !py-2 text-slate-500 break-words">{{ $a->kode }}</td>
                                <td class="td !text-[10px] !px-1.5 !py-2 font-medium break-words">{{ $a->judul }}</td>
                                <td class="td !text-[9px] !px-1.5 !py-2 break-words">{{ $a->nama_kategori }}</td>
                                <td class="td !text-[9px] !px-1.5 !py-2">
                                    <span class="badge {{ $a->aktif ? 'b-green' : 'b-slate' }} !text-[9px] !px-1.5 !py-0.5">
                                        {{ $a->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="td !text-[10px] text-center text-slate-400 !py-6">
                                    Belum ada artikel{{ array_filter($filter) ? ' yang cocok dengan filter' : '' }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-[10px] text-slate-400 mt-2">
                Menampilkan {{ $daftar->count() }} dari {{ $total }} artikel
                @if ($bisaKelola) · klik baris untuk mengubah @endif
            </div>
        </div>
    </div>

    {{-- Panel uji pencarian --}}
    @if ($bisaKelola)
        <div
            x-data="{
                pertanyaan: '',
                hasil: null, istilah: [], ambang: @js($ambang),
                sibuk: false, galat: '',
                csrf: @js(csrf_token()), url: @js(route('basis-pengetahuan.uji')),
                async uji() {
                    this.sibuk = true; this.galat = '';
                    try {
                        const res = await fetch(this.url, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ pertanyaan: this.pertanyaan })
                        });
                        const d = await res.json().catch(() => ({}));
                        if (!res.ok) { this.galat = d.errors?.pertanyaan?.[0] || d.message || 'Pencarian gagal.'; this.hasil = null; }
                        else { this.hasil = d.hasil; this.istilah = d.istilah; this.ambang = d.ambang; }
                    } catch (e) { this.galat = 'Tidak dapat menghubungi server.'; }
                    this.sibuk = false;
                }
            }"
            class="card !p-3 h-full space-y-2.5"
        >
            <div class="font-semibold text-xs">Uji pencarian</div>

            <div>
                <label class="label !text-[10px]">Pertanyaan uji</label>
                <textarea x-model="pertanyaan" rows="2" class="field !text-[10px] !py-2"
                    placeholder="mis. jadwal kelas 8 SMP dan diskon kakak beradik"></textarea>
            </div>

            <button type="button" @click="uji()" :disabled="sibuk || pertanyaan.trim() === ''"
                class="btn btn-primary w-full !text-[10px] !py-1.5 disabled:opacity-50">
                <span x-text="sibuk ? 'Mencari…' : 'Jalankan uji'"></span>
            </button>

            <p x-show="galat" x-text="galat" class="text-[10px] text-red-600"></p>

            <template x-if="hasil !== null">
                <div class="space-y-2">
                    <div class="font-semibold text-[10px] pt-1">Hasil teratas</div>

                    <p x-show="hasil.length === 0" class="text-[10px] text-slate-400">
                        Tidak ada artikel aktif yang cocok.
                    </p>

                    <template x-for="h in hasil" :key="h.kode">
                        <div class="flex justify-between items-start gap-2 text-[10px]">
                            <span class="min-w-0 break-words">
                                <span class="font-medium" x-text="h.kode"></span>
                                <span x-text="h.judul"></span>
                                <span class="block text-[9px] text-slate-400"
                                    x-text="h.kategori + ' · cakupan ' + Math.round(h.cakupan * 100) + '%'"></span>
                            </span>
                            <span class="badge !text-[9px] !px-1.5 !py-0.5 shrink-0"
                                :class="h.dipakai ? 'b-green' : 'b-red'"
                                x-text="h.skor.toFixed(2).replace('.', ',')"></span>
                        </div>
                    </template>

                    <p x-show="istilah.length" class="text-[9px] text-slate-400">
                        Istilah dicari: <span x-text="istilah.join(', ')"></span>
                    </p>
                </div>
            </template>

            <p class="text-[9px] leading-relaxed text-slate-400 pt-1">
                Artikel dengan skor di bawah ambang
                <span x-text="ambang.toFixed(2).replace('.', ',')"></span>
                tidak dipakai AI.
            </p>
        </div>
    @endif

    {{-- Modal tambah / ubah artikel --}}
    @if ($bisaKelola)
        <div x-show="buka" x-cloak class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div @click.outside="buka = false" class="bg-white rounded-xl p-4 w-full max-w-lg">
                <h3 class="font-semibold text-sm mb-3"
                    x-text="mode === 'ubah' ? 'Ubah artikel' : 'Tambah artikel'"></h3>

                <form method="POST" :action="aksi()" class="space-y-3">
                    @csrf
                    <template x-if="mode === 'ubah'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="_mode" :value="mode">
                    <input type="hidden" name="_id" :value="id">

                    <div>
                        <label class="label !text-[10px]">Judul</label>
                        <input type="text" name="judul" x-model="form.judul" maxlength="255" class="field w-full !text-xs">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label !text-[10px]">Kategori</label>
                            <select name="kategori" x-model="form.kategori" class="field w-full !text-xs">
                                @foreach (\App\Models\ArtikelPengetahuan::KATEGORI as $kunci => $nama)
                                    <option value="{{ $kunci }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label !text-[10px]">Kata kunci tambahan (label)</label>
                            <input type="text" name="label" x-model="form.label" maxlength="255" class="field w-full !text-xs"
                                placeholder="sinonim, pisahkan dengan koma">
                        </div>
                    </div>

                    <div>
                        <label class="label !text-[10px]">Isi</label>
                        <textarea name="isi" x-model="form.isi" rows="8" class="field w-full !text-xs"></textarea>
                        <p class="text-[9px] text-slate-400 mt-1">
                            AI hanya menjawab dari isi artikel yang aktif. Tulis fakta yang boleh disampaikan kepada pelanggan.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-[11px]">
                        <input type="checkbox" name="aktif" value="1" x-model="form.aktif">
                        Aktif (dipakai AI)
                    </label>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="buka = false" class="btn btn-outline !text-[10px] !px-2.5 !py-1">Batal</button>
                        <button type="submit" class="btn btn-primary !text-[10px] !px-2.5 !py-1">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
