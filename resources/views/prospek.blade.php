
@extends('layouts.app')

@section('title', 'Prospek')
@section('subtitle', 'Pipeline calon siswa dari percakapan WhatsApp')

@section('actions')
    <button
        type="button"
        @click="$dispatch('buka-tambah-prospek')"
        class="rounded-md bg-teal-600 px-3 py-2 text-[10px] font-medium text-white hover:bg-teal-700"
    >
        + Tambah Prospek
    </button>
@endsection

@section('content')

<div
    x-data="{
        sel: null,
        data: @js($prospek),
        kataKunci: '',
        filterTahap: '',
        filterProgram: '',
        bukaTambah: {{ $errors->any() ? 'true' : 'false' }},

        get daftarProgram() {
            return [...new Set(this.data.map(p => p.info).filter(Boolean))];
        },

        dataTahap(tahap) {
            return this.data.filter(p => {
                const kata = this.kataKunci.toLowerCase();

                return p.tahap === tahap
                    && (!kata || (p.nama || '').toLowerCase().includes(kata)
                        || (p.telp || '').toLowerCase().includes(kata))
                    && (!this.filterTahap || p.tahap === this.filterTahap)
                    && (!this.filterProgram || p.info === this.filterProgram);
            });
        },

        namaTahap(kode) {
            return {
                baru: 'Baru',
                dihubungi: 'Dihubungi',
                tertarik: 'Tertarik',
                penawaran: 'Penawaran',
                daftar: 'Daftar',
                batal: 'Batal'
            }[kode] || kode;
        },

        get prospekTerpilih() {
            return this.data.find(p => p.id === this.sel);
        }
    }"
    @buka-tambah-prospek.window="bukaTambah = true"
    class="space-y-3 text-xs"
>

    {{-- Notifikasi Validasi --}}
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-3 text-red-700">
            <div class="font-semibold mb-1">Data belum berhasil disimpan:</div>
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-2">

        <template x-for="item in [
            {kode:'baru', label:'Baru'},
            {kode:'dihubungi', label:'Dihubungi'},
            {kode:'tertarik', label:'Tertarik'},
            {kode:'penawaran', label:'Penawaran'},
            {kode:'daftar', label:'Daftar'},
            {kode:'batal', label:'Batal'}
        ]" :key="item.kode">

            <button
                type="button"
                @click="filterTahap = filterTahap === item.kode ? '' : item.kode"
                class="bg-white border rounded-lg p-3 text-left hover:border-teal-400"
                :class="filterTahap === item.kode ? 'border-teal-500 ring-1 ring-teal-100' : 'border-slate-200'"
            >
                <div class="text-[10px] text-slate-500" x-text="item.label"></div>

                <div
                    class="text-xl font-semibold text-slate-800"
                    x-text="data.filter(p => p.tahap === item.kode).length"
                ></div>
            </button>

        </template>

    </div>

    {{-- Pencarian dan Filter --}}
    <div class="bg-white border border-slate-200 rounded-lg p-3">

        <div class="flex flex-col md:flex-row gap-2">

            <div class="flex-1">
                <input
                    type="text"
                    x-model="kataKunci"
                    placeholder="Cari nama atau nomor telepon..."
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                >
            </div>

            <div class="md:w-44">
                <select
                    x-model="filterTahap"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                >
                    <option value="">Semua Tahap</option>
                    <option value="baru">Baru</option>
                    <option value="dihubungi">Dihubungi</option>
                    <option value="tertarik">Tertarik</option>
                    <option value="penawaran">Penawaran</option>
                    <option value="daftar">Daftar</option>
                    <option value="batal">Batal</option>
                </select>
            </div>

            <div class="md:w-52">
                <select
                    x-model="filterProgram"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                >
                    <option value="">Semua Program</option>

                    <template x-for="program in daftarProgram" :key="program">
                        <option :value="program" x-text="program"></option>
                    </template>
                </select>
            </div>

            <button
                type="button"
                @click="kataKunci = ''; filterTahap = ''; filterProgram = ''; sel = null"
                class="rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50"
            >
                Reset
            </button>

        </div>
    </div>

    {{-- Pipeline dan Detail --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">

        {{-- Pipeline --}}
        <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">

            <template x-for="tahap in [
                'baru',
                'dihubungi',
                'tertarik',
                'penawaran',
                'daftar',
                'batal'
            ]" :key="tahap">

                <div class="bg-slate-100 rounded-lg p-2 min-h-36">

                    <div class="flex items-center justify-between mb-2">
                        <span
                            class="font-semibold text-slate-700"
                            x-text="namaTahap(tahap)"
                        ></span>

                        <span
                            class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500"
                            x-text="dataTahap(tahap).length"
                        ></span>
                    </div>

                    <div class="space-y-2">

                        <template x-for="p in dataTahap(tahap)" :key="p.id">

                            <button
                                type="button"
                                @click="sel = p.id"
                                class="w-full rounded-md border bg-white p-2 text-left hover:border-teal-400"
                                :class="sel === p.id ? 'border-teal-500 ring-1 ring-teal-100' : 'border-slate-200'"
                            >

                                <div
                                    class="font-semibold text-slate-800"
                                    x-text="p.nama"
                                ></div>

                                <div
                                    class="text-[10px] text-slate-500 mt-1"
                                    x-text="p.info || 'Program belum diisi'"
                                ></div>

                                <div class="flex items-center justify-between mt-2">

                                    <span class="text-[10px] text-slate-500">
                                        Skor
                                    </span>

                                    <span
                                        class="font-semibold text-teal-700"
                                        x-text="(p.skor ?? 0) + '/100'"
                                    ></span>

                                </div>

                            </button>

                        </template>

                        <div
                            x-show="dataTahap(tahap).length === 0"
                            class="text-center text-[10px] text-slate-400 py-4"
                        >
                            Belum ada prospek
                        </div>

                    </div>

                </div>

            </template>

        </div>

        {{-- Detail Prospek --}}
        <div class="bg-white border border-slate-200 rounded-lg p-3">

            <div class="font-semibold text-slate-800 mb-3">
                Detail Prospek
            </div>

            <template x-if="prospekTerpilih">

                <div class="space-y-3">

                    <div>
                        <div class="text-[10px] text-slate-500">Nama Prospek</div>
                        <div
                            class="font-semibold text-slate-800"
                            x-text="prospekTerpilih.nama"
                        ></div>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Nomor Telepon</div>
                        <div x-text="prospekTerpilih.telp || '-'"></div>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Jenjang dan Program</div>
                        <div x-text="prospekTerpilih.info || '-'"></div>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Tahap Pipeline</div>
                        <span
                            class="inline-block rounded-full bg-teal-50 text-teal-700 px-2 py-1 text-[10px]"
                            x-text="namaTahap(prospekTerpilih.tahap)"
                        ></span>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Skor Prospek</div>
                        <div
                            class="text-xl font-semibold text-teal-700"
                            x-text="(prospekTerpilih.skor ?? 0) + '/100'"
                        ></div>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Penanggung Jawab</div>
                        <div x-text="prospekTerpilih.pj || 'Belum ditugaskan'"></div>
                    </div>

                    <div>
                        <div class="text-[10px] text-slate-500">Catatan</div>
                        <div
                            class="rounded-md bg-slate-50 p-2 text-slate-600"
                            x-text="prospekTerpilih.catatan || 'Belum ada catatan.'"
                        ></div>
                    </div>

                    <button
                        type="button"
                        @click="sel = null"
                        class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50"
                    >
                        Tutup Detail
                    </button>

                </div>

            </template>

            <div
                x-show="!prospekTerpilih"
                class="py-10 text-center text-slate-400"
            >
                <div class="text-2xl mb-2">◎</div>
                <div>Pilih prospek untuk melihat detail.</div>
            </div>

        </div>

    </div>

    {{-- Faktor Penilaian --}}
    <div class="bg-white border border-slate-200 rounded-lg p-3">

        <div class="font-semibold text-slate-800 mb-3">
            Faktor Penilaian Skor Prospek
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">

            @foreach ($faktor as $item)

                <div>
                    <div class="flex justify-between gap-2 mb-1">
                        <span class="text-[10px] text-slate-600">
                            {{ $item[0] }}
                        </span>

                        <span class="text-[10px] font-semibold text-teal-700">
                            {{ $item[1] }}%
                        </span>
                    </div>

                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-teal-500 rounded-full"
                            style="width: {{ $item[1] }}%"
                        ></div>
                    </div>
                </div>

            @endforeach

        </div>

    </div>

    {{-- Popup Tambah Prospek --}}
    <div
        x-cloak
        x-show="bukaTambah"
        x-transition.opacity
        @keydown.escape.window="bukaTambah = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
    >

        <div
            @click.outside="bukaTambah = false"
            class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl"
        >

            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">

                <div>
                    <h2 class="font-semibold text-slate-800">
                        Tambah Prospek
                    </h2>

                    <p class="text-[10px] text-slate-500">
                        Masukkan informasi calon siswa
                    </p>
                </div>

                <button
                    type="button"
                    @click="bukaTambah = false"
                    class="text-slate-400 hover:text-slate-700 text-lg"
                >
                    &times;
                </button>

            </div>

            <form
                method="POST"
                action="{{ route('prospek.store') }}"
                class="space-y-3 p-4"
            >
                @csrf

                <div>
                    <label class="block mb-1 font-medium text-slate-700">
                        Nama Lengkap *
                    </label>

                    <input
                        type="text"
                        name="nama"
                        value="{{ old('nama') }}"
                        required
                        maxlength="255"
                        placeholder="Masukkan nama calon siswa"
                        class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                    >
                </div>

                <div>
                    <label class="block mb-1 font-medium text-slate-700">
                        Nomor Telepon *
                    </label>

                    <input
                        type="text"
                        name="nomor_telepon"
                        value="{{ old('nomor_telepon') }}"
                        required
                        maxlength="20"
                        placeholder="Contoh: 081234567890"
                        class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                    >
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                    <div>
                        <label class="block mb-1 font-medium text-slate-700">
                            Jenjang
                        </label>

                        <select
                            name="jenjang"
                            class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                        >
                            <option value="">Pilih Jenjang</option>
                            <option value="SD" @selected(old('jenjang') === 'SD')>SD</option>
                            <option value="SMP" @selected(old('jenjang') === 'SMP')>SMP</option>
                            <option value="SMA" @selected(old('jenjang') === 'SMA')>SMA</option>
                            <option value="Alumni" @selected(old('jenjang') === 'Alumni')>Alumni</option>
                            <option value="Umum" @selected(old('jenjang') === 'Umum')>Umum</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 font-medium text-slate-700">
                            Program
                        </label>

                        <input
                            type="text"
                            name="program"
                            value="{{ old('program') }}"
                            maxlength="255"
                            placeholder="Contoh: UTBK SNBT"
                            class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                        >
                    </div>

                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                    <div>
                        <label class="block mb-1 font-medium text-slate-700">
                            Skor Prospek
                        </label>

                        <input
                            type="number"
                            name="skor"
                            value="{{ old('skor', 0) }}"
                            min="0"
                            max="100"
                            class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                        >
                    </div>

                    <div>
                        <label class="block mb-1 font-medium text-slate-700">
                            Tahap Pipeline *
                        </label>

                        <select
                            name="tahap"
                            required
                            class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                        >
                            <option value="baru" @selected(old('tahap', 'baru') === 'baru')>Baru</option>
                            <option value="dihubungi" @selected(old('tahap') === 'dihubungi')>Dihubungi</option>
                            <option value="tertarik" @selected(old('tahap') === 'tertarik')>Tertarik</option>
                            <option value="penawaran" @selected(old('tahap') === 'penawaran')>Penawaran</option>
                            <option value="daftar" @selected(old('tahap') === 'daftar')>Daftar</option>
                            <option value="batal" @selected(old('tahap') === 'batal')>Batal</option>
                        </select>
                    </div>

                </div>

                <div>
                    <label class="block mb-1 font-medium text-slate-700">
                        Sumber Prospek
                    </label>

                    <select
                        name="sumber"
                        class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                    >
                        <option value="">Pilih Sumber</option>
                        <option value="WhatsApp" @selected(old('sumber') === 'WhatsApp')>WhatsApp</option>
                        <option value="Website" @selected(old('sumber') === 'Website')>Website</option>
                        <option value="Instagram" @selected(old('sumber') === 'Instagram')>Instagram</option>
                        <option value="Referral" @selected(old('sumber') === 'Referral')>Referral</option>
                        <option value="Lainnya" @selected(old('sumber') === 'Lainnya')>Lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-medium text-slate-700">
                        Catatan
                    </label>

                    <textarea
                        name="catatan"
                        rows="3"
                        placeholder="Tambahkan catatan mengenai calon siswa..."
                        class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"
                    >{{ old('catatan') }}</textarea>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">

                    <button
                        type="button"
                        @click="bukaTambah = false"
                        class="rounded-md border border-slate-200 px-4 py-2 text-xs text-slate-600 hover:bg-slate-50"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="rounded-md bg-teal-600 px-4 py-2 text-xs font-medium text-white hover:bg-teal-700"
                    >
                        Simpan Prospek
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection
