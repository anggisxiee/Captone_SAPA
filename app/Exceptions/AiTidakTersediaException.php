<?php

namespace App\Exceptions;

/**
 * Penyedia AI tidak dapat dipakai saat ini (jaringan, timeout, anggaran habis, atau
 * gangguan di sisi penyedia). Pemanggil harus beralih ke mode aturan bawaan (F04).
 */
class AiTidakTersediaException extends AiException
{
}
