<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Permintaan HTTP keluar yang tidak dipalsukan (Http::fake) langsung gagal,
        // sehingga tes tidak pernah menyentuh gateway atau penyedia AI sungguhan.
        Http::preventStrayRequests();
    }
}
