<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class SessionExpiredTest extends TestCase
{
    public function test_token_mismatch_exception_redirects_to_login_with_flash_message(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class);

        // Bind custom test route that throws TokenMismatchException
        app('router')->get('/test-session-expired', function () {
            throw new TokenMismatchException();
        });

        $response = $this->get('/test-session-expired');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول مجدداً.');
    }

    public function test_custom_419_page_renders_with_login_button(): void
    {
        $response = $this->view('errors.419');

        $response->assertSee('انتهت صلاحية الجلسة');
        $response->assertSee('تسجيل الدخول مجدداً');
        $response->assertSee(route('login'));
    }
}
