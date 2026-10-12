<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_guest_cannot_open_knowledge_base(): void
    {
        $this->get(route('knowledge.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_open_knowledge_base(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('KnowledgeBase/Index')
                ->has('articles')
                ->where('articles.0.id', 'start')
                ->has('articles', fn ($articles) => $articles
                    ->each(fn ($article) => $article
                        ->has('id')
                        ->has('title')
                        ->has('summary')
                        ->has('category')
                        ->has('sections')
                        ->etc()
                    )
                )
            );

        $ids = collect(\App\Support\KnowledgeBase::articles())->pluck('id');

        foreach ([
            'training',
            'home-filters',
            'lifecycle',
            'clone',
            'library',
            'package',
            'rop-panel',
            'notifications',
            'playbook',
            'admin',
            'google',
            'access',
            'amocrm',
        ] as $id) {
            $this->assertTrue($ids->contains($id), "Missing knowledge article: {$id}");
        }
    }
}
