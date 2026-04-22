<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CreateUserRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_redirect_falls_back_to_index_when_creator_cannot_edit_record(): void
    {
        $user = User::factory()->create();

        $page = new class extends CreateUser
        {
            public function redirectUrlFor(User $user): string
            {
                $reflection = new ReflectionClass($this);
                $property = $reflection->getParentClass()->getProperty('record');
                $property->setAccessible(true);
                $property->setValue($this, $user);

                return $this->getRedirectUrl();
            }
        };

        $this->assertSame(
            UserResource::getUrl(),
            $page->redirectUrlFor($user),
        );
    }

    public function test_create_user_redirect_points_to_edit_page_when_creator_can_edit_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $page = new class extends CreateUser
        {
            public function redirectUrlFor(User $user): string
            {
                $reflection = new ReflectionClass($this);
                $property = $reflection->getParentClass()->getProperty('record');
                $property->setAccessible(true);
                $property->setValue($this, $user);

                return $this->getRedirectUrl();
            }
        };

        $this->assertSame(
            UserResource::getUrl('edit', ['record' => $user]),
            $page->redirectUrlFor($user),
        );
    }
}
