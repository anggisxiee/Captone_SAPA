<?php

namespace App\Support;

class Telepon
{
    /**
     * Menyeragamkan nomor telepon ke format 62xxxxxxxx (hanya angka).
     * "0812-3456-7890", "+62 812 3456 7890" dan "6281234567890" menjadi
     * "6281234567890".
     */
    public static function normalisasi(string $nomor): string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($angka, '0')) {
            return '62' . substr($angka, 1);
        }

        return $angka;
    }
}
