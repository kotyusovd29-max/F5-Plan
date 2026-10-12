<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalAiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_include_ai_chat_projects(): void
    {
        $user = User::factory()->create();
        Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Альфа',
        ]);
        Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Бета',
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('aiChat.projects', 2)
                ->has('aiChat.prompts.analysis')
                ->has('aiChat.prompts.actions')
            );
    }

    public function test_guest_does_not_receive_ai_chat_payload(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('aiChat', null));
    }
}
