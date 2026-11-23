<?php

namespace App\Exceptions;

use RuntimeException;

/** Galat umum dari penyedia AI (permintaan ditolak atau jawaban tidak dapat dipakai). */
class AiException extends RuntimeException
{
}
