<?php

namespace App\Exceptions;

class KunciApiDitolakException extends AiException
{
    public function __construct()
    {
        parent::__construct('Kunci API ditolak penyedia');
    }
}
