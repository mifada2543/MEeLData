<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Root route tidak lagi merender halaman welcome, melainkan redirect
 * berdasarkan authentication state (lihat routes/web.php).
 * Perilaku lengkapnya diuji pada AuthenticationTest.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_application_boots_successfully(): void
    {
        $this->get('/up')->assertOk();
    }
}
