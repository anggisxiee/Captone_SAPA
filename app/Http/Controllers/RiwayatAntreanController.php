<?php

namespace App\Http\Controllers;

use App\Models\RiwayatAntrean;

class RiwayatAntreanController extends Controller
{
    /**
     * Menampilkan seluruh riwayat perubahan status antrean.
     */
    public function index()
    {
        $riwayat = RiwayatAntrean::with('antreanPesan')
            ->latest('diubah_pada')
            ->get();

        return view('riwayat-antrean', compact('riwayat'));
    }
}
