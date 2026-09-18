<?php

namespace Tests\Feature;

use Tests\TestCase;

class CookiePolicyTest extends TestCase
{
    public function test_policy_is_public_and_reflects_instance_configuration(): void
    {
        config([
            'session.driver' => 'array',
            'session.cookie' => 'custom-session',
            'session.lifetime' => 45,
            'legal.operator_name' => 'Example operator',
            'legal.contact_email' => 'operator@example.test',
        ]);

        $this->get('/cookie-policy')
            ->assertOk()
            ->assertSee('Example operator')
            ->assertSee('operator@example.test')
            ->assertSee('custom-session')
            ->assertSee('45 minutes')
            ->assertSee('fluxa_refresh_token')
            ->assertSee('fluxa_user')
            ->assertDontSee('resources/js/app.js');
    }

    public function test_policy_describes_browser_session_expiry_when_enabled(): void
    {
        config(['session.driver' => 'array', 'session.expire_on_close' => true]);

        $this->get('/cookie-policy')
            ->assertOk()
            ->assertSee('Until the browser session ends');
    }
}
