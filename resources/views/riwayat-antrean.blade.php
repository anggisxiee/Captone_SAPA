@extends('layouts.app')

@section('title', 'Riwayat Antrean')
@section('subtitle', 'Catatan perubahan status pesan dalam antrean')

@section('content')
<div class="space-y-3 text-xs">

    <div class="card p-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-800">
                    Riwayat Perubahan Status
                </h3>
                <p class="text-slate-500 mt-1">
                    Menampilkan seluruh perubahan status pesan.
                </p>
            </div>

            <span class="badge b-green">
                {{ $riwayat->count() }} Riwayat
            </span>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="th">Waktu</th>
                        <th class="th">Pengirim</th>
                        <th class="th">Penerima</th>
                        <th class="th">Pesan</th>
                        <th class="th">Status Sebelumnya</th>
                        <th class="th">Status Baru</th>
                        <th class="th">Keterangan</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($riwayat as $item)
                        @php
                            $status = $item->status_baru;

                            $warna = match ($status) {
                                'Kirim', 'Terkirim' => 'b-green',
                                'Ditunda' => 'b-amber',
                                'Ditahan', 'Gagal' => 'b-red',
                                default => 'b-amber',
                            };
                        @endphp

                        <tr class="border-b border-slate-100">
                            <td class="td whitespace-nowrap">
                                {{ $item->diubah_pada
                                    ? $item->diubah_pada->format('d/m/Y H:i:s')
                                    : '-' }}
                            </td>

                            <td class="td">
                                {{ $item->antreanPesan->pengirim ?? '-' }}
                            </td>

                            <td class="td">
                                {{ $item->antreanPesan->penerima ?? '-' }}
                            </td>

                            <td class="td max-w-xs">
                                {{ $item->antreanPesan->isi_pesan ?? '-' }}
                            </td>

                            <td class="td">
                                {{ $item->status_sebelumnya ?? '-' }}
                            </td>

                            <td class="td">
                                <span class="badge {{ $warna }}">
                                    {{ $item->status_baru }}
                                </span>
                            </td>

                            <td class="td">
                                {{ $item->keterangan ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"
                                class="td text-center text-slate-500 py-8">
                                Belum ada riwayat perubahan status antrean.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection