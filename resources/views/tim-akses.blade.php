
@extends('layouts.app')
@section('title', 'Tim dan Akses')
@section('subtitle', 'Pengguna, peran, dan hak akses per nomor WhatsApp')

@section('content')
@php
    $peran = [
        ['Agen/CS', 'Membalas chat dan mengelola prospek'],
        ['Supervisor', 'Memantau, mengambil alih, dan mengelola basis pengetahuan'],
        ['Administrator', 'Mengelola nomor, AI, tim, dan akses']
    ];

    $level = ['Tidak ada', 'Lihat saja', 'Balas chat', 'Kelola'];

    $editMode = isset($editUser);
    $aksesEdit = $editMode
        ? $editUser->aksesWhatsapp->keyBy('nomor_whatsapp_id')
        : collect();

    $roleAwal = old('role', $editUser->role ?? 'Agen/CS');
    $modalTerbuka = $errors->any() || $editMode;
@endphp

<div
    x-data="{
        open: {{ $modalTerbuka ? 'true' : 'false' }},
        peran: @js($roleAwal)
    }"
    class="text-xs"
>
    {{-- Tombol tambah pengguna --}}
    <div class="flex justify-end -mt-12 mb-4">
        <button
            type="button"
            @click="open = true; peran = 'Agen/CS'"
            class="btn btn-primary !text-[10px] !px-3 !py-1.5"
        >
            Tambah pengguna
        </button>
    </div>

    {{-- Notifikasi --}}
    @if (session('success'))
        <div class="mb-3 rounded-lg bg-green-50 text-green-700 px-3 py-2 text-[10px]">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-3 rounded-lg bg-red-50 text-red-600 px-3 py-2 text-[10px]">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabel pengguna --}}
    <div class="card !p-0 overflow-hidden">
        <table class="w-full table-fixed">
            <colgroup>
                <col style="width: 18%">
                <col style="width: 27%">
                <col style="width: 15%">
                <col style="width: 12%">
                <col style="width: 18%">
                <col style="width: 10%">
            </colgroup>

            <thead class="bg-slate-50">
                <tr>
                    @foreach (['Nama', 'Email', 'Peran', 'Status', 'Terakhir masuk', 'Aksi'] as $h)
                        <th class="th !text-[10px] !px-3 !py-2">
                            {{ $h }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($pengguna as $user)
                    <tr>
                        <td class="td !text-[10px] !px-3 !py-2 font-medium">
                            {{ $user->name }}
                        </td>

                        <td class="td !text-[10px] !px-3 !py-2 text-slate-500 break-all">
                            {{ $user->email }}
                        </td>

                        <td class="td !text-[10px] !px-3 !py-2">
                            {{ $user->role }}
                        </td>

                        <td class="td !text-[10px] !px-3 !py-2">
                            <span class="badge {{ $user->status === 'Aktif' ? 'b-green' : 'b-slate' }} !text-[9px] !px-2 !py-0.5">
                                {{ $user->status }}
                            </span>
                        </td>

                        <td class="td !text-[10px] !px-3 !py-2 text-slate-500">
                            {{ $user->last_login_at
                                ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('d/m/Y H:i')
                                : 'Belum tercatat'
                            }}
                        </td>

                        <td class="td !text-[10px] !px-2 !py-2">
                            <div class="flex flex-col gap-1">
                                <a
                                    href="{{ route('tim-akses.edit', $user->id) }}"
                                    class="btn btn-outline !text-[9px] !px-2 !py-1 text-center"
                                >
                                    Ubah
                                </a>

                                <form
                                    action="{{ route('tim-akses.status', $user->id) }}"
                                    method="POST"
                                    onsubmit="return confirm('Ubah status pengguna ini?')"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="submit"
                                        class="text-[9px] text-teal-700 hover:underline"
                                    >
                                        {{ $user->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <form
                                    action="{{ route('tim-akses.destroy', $user->id) }}"
                                    method="POST"
                                    onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="text-[9px] text-red-600 hover:underline"
                                    >
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="td text-center !py-8 text-slate-400 !text-[10px]">
                            Belum ada data pengguna.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Jumlah pengguna --}}
    <div class="text-[10px] text-slate-400 mt-2">
        {{ $pengguna->count() }} pengguna ·
        {{ $pengguna->where('status', 'Aktif')->count() }} aktif
    </div>

    {{-- Modal tambah / ubah pengguna --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4"
    >
        <div
            @click.outside="open = false"
            class="bg-white rounded-xl p-4 w-full max-w-xl space-y-3 max-h-[90vh] overflow-y-auto"
        >
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-sm">
                    {{ $editMode ? 'Ubah pengguna' : 'Tambah pengguna' }}
                </h3>

                <button
                    type="button"
                    @click="open = false"
                    class="text-slate-400 hover:text-slate-700 text-lg"
                >
                    &times;
                </button>
            </div>

            {{-- Form --}}
            <form
                method="POST"
                action="{{ $editMode
                    ? route('tim-akses.update', $editUser->id)
                    : route('tim-akses.store') }}"
                class="space-y-3"
            >
                @csrf

                @if ($editMode)
                    @method('PUT')
                @endif

                {{-- Nama dan email --}}
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="label !text-[10px]">Nama lengkap</label>
                        <input
                            type="text"
                            name="name"
                            class="field !text-[10px] !py-1.5"
                            value="{{ old('name', $editUser->name ?? '') }}"
                            placeholder="Masukkan nama"
                            required
                        >
                    </div>

                    <div>
                        <label class="label !text-[10px]">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="field !text-[10px] !py-1.5"
                            value="{{ old('email', $editUser->email ?? '') }}"
                            placeholder="Masukkan email"
                            required
                        >
                    </div>
                </div>

                {{-- Password --}}
                <div>
                    <label class="label !text-[10px]">
                        Password
                        @if ($editMode)
                            <span class="text-slate-400">(Kosongkan jika tidak diubah)</span>
                        @endif
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="field !text-[10px] !py-1.5"
                        placeholder="{{ $editMode ? 'Password baru (opsional)' : 'Minimal 8 karakter' }}"
                        {{ $editMode ? '' : 'required' }}
                    >
                </div>

                {{-- Peran --}}
                <div>
                    <label class="label !text-[10px]">Peran</label>

                    <input type="hidden" name="role" :value="peran">

                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($peran as [$nama, $ket])
                            <button
                                type="button"
                                @click="peran = @js($nama)"
                                :class="peran === @js($nama)
                                    ? 'border-teal-600 bg-teal-50'
                                    : 'border-slate-200'"
                                class="border rounded-lg p-2 text-left"
                            >
                                <div class="font-medium text-[10px]">
                                    {{ $nama }}
                                </div>

                                <div class="text-[9px] text-slate-500 mt-1 leading-relaxed">
                                    {{ $ket }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Status khusus edit --}}
                @if ($editMode)
                    <div>
                        <label class="label !text-[10px]">Status pengguna</label>

                        <select
                            name="status"
                            class="field !text-[10px] !py-1.5"
                            required
                        >
                            <option value="Aktif"
                                {{ old('status', $editUser->status) === 'Aktif' ? 'selected' : '' }}>
                                Aktif
                            </option>

                            <option value="Nonaktif"
                                {{ old('status', $editUser->status) === 'Nonaktif' ? 'selected' : '' }}>
                                Nonaktif
                            </option>
                        </select>
                    </div>
                @endif

                {{-- Hak akses WhatsApp --}}
                <div>
                    <label class="label !text-[10px]">
                        Hak akses per nomor WhatsApp
                    </label>

                    <div class="overflow-x-auto">
                        <table class="w-full border border-slate-200 rounded-lg table-fixed">
                            <colgroup>
                                <col style="width: 30%">
                                @foreach ($level as $l)
                                    <col style="width: 17.5%">
                                @endforeach
                            </colgroup>

                            <thead>
                                <tr>
                                    <th class="th !text-[9px] !px-2 !py-2">
                                        Nomor
                                    </th>

                                    @foreach ($level as $l)
                                        <th class="th !text-[9px] !px-1 !py-2 text-center">
                                            {{ $l }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($nomorWhatsapp as $nomor)
                                    @php
                                        $aksesSaatIni = old(
                                            'akses.' . $nomor->id,
                                            $aksesEdit->get($nomor->id)?->hak_akses ?? 'Tidak ada'
                                        );
                                    @endphp

                                    <tr>
                                        <td class="td !text-[9px] !px-2 !py-2">
                                            <div class="font-medium">
                                                {{ $nomor->nama }}
                                            </div>
                                            <div class="text-slate-400">
                                                {{ $nomor->nomor }}
                                            </div>
                                        </td>

                                        @foreach ($level as $l)
                                            <td class="td !text-[9px] !px-1 !py-2 text-center">
                                                <input
                                                    type="radio"
                                                    name="akses[{{ $nomor->id }}]"
                                                    value="{{ $l }}"
                                                    class="accent-teal-700 w-3 h-3"
                                                    {{ $aksesSaatIni === $l ? 'checked' : '' }}
                                                >
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="td text-center !py-4 text-slate-400">
                                            Belum ada nomor WhatsApp.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Tombol --}}
                <div class="flex justify-end gap-2 pt-1">
                    <button
                        type="button"
                        @click="open = false"
                        class="btn btn-outline !text-[10px] !px-3 !py-1.5"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary !text-[10px] !px-3 !py-1.5"
                    >
                        {{ $editMode ? 'Simpan perubahan' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
