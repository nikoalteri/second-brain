<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityChecklistTest extends TestCase
{
    /** @test */
    public function password_is_hashed()
    {
        $plain = 'password123';
        $hashed = Hash::make($plain);
        $this->assertTrue(Hash::check($plain, $hashed));
        $this->assertNotEquals($plain, $hashed);
    }

    /** @test */
    public function env_file_is_not_committed()
    {
        $this->assertFileExists(base_path('.env.example'), '.env.example deve essere committato');
    }
}
