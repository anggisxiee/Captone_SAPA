<?php

namespace App\Exceptions;

/**
 * Gateway melaporkan nomor sudah tersambung ketika SAPA meminta QR
 * (misalnya basis data SAPA direset tetapi sesi di gateway masih hidup).
 */
class SudahTersambungException extends GatewayException
{
    public function __construct()
    {
        parent::__construct('Nomor sudah tersambung di gateway.');
    }
}
