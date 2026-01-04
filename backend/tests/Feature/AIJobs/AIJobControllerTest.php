<?php

namespace Tests\Feature\AIJobs;

use App\Models\AIJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIJobControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_jobs(): void
    {
        AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test1']);
        AIJob::createJob($this->user->id, AIJob::TYPE_SMART_FILL, ['product_name' => 'test2']);

        $response = $this->actingAs($this->user)->getJson('/api/ai-jobs');

        $response->assertOk();
        $response->assertJsonCount(2, 'jobs');
    }

    public function test_can_filter_jobs_by_status(): void
    {
        $job1 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => '1']);
        $job2 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => '2']);
        
        $job1->markAsProcessing();
        $job1->markAsCompleted([]);

        $response = $this->actingAs($this->user)->getJson('/api/ai-jobs?status=completed');

        $response->assertOk();
        $response->assertJsonCount(1, 'jobs');
        $this->assertEquals($job1->id, $response->json('jobs.0.id'));
    }

    public function test_can_get_active_jobs(): void
    {
        $job1 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => '1']);
        $job2 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => '2']);
        
        $job2->markAsProcessing();
        $job2->markAsCompleted([]);

        $response = $this->actingAs($this->user)->getJson('/api/ai-jobs/active');

        $response->assertOk();
        $response->assertJsonCount(1, 'jobs');
        $this->assertEquals($job1->id, $response->json('jobs.0.id'));
    }

    public function test_can_get_single_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);

        $response = $this->actingAs($this->user)->getJson("/api/ai-jobs/{$job->id}");

        $response->assertOk();
        $response->assertJson([
            'job' => [
                'id' => $job->id,
                'type' => AIJob::TYPE_PRICE_SEARCH,
                'status' => AIJob::STATUS_PENDING,
            ],
        ]);
    }

    public function test_cannot_view_other_users_job(): void
    {
        $otherUser = User::factory()->create();
        $job = AIJob::createJob($otherUser->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);

        $response = $this->actingAs($this->user)->getJson("/api/ai-jobs/{$job->id}");

        $response->assertForbidden();
    }

    public function test_can_cancel_active_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);

        $response = $this->actingAs($this->user)->postJson("/api/ai-jobs/{$job->id}/cancel");

        $response->assertOk();
        
        $job->refresh();
        $this->assertEquals(AIJob::STATUS_CANCELLED, $job->status);
    }

    public function test_cannot_cancel_completed_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);
        $job->markAsProcessing();
        $job->markAsCompleted([]);

        $response = $this->actingAs($this->user)->postJson("/api/ai-jobs/{$job->id}/cancel");

        $response->assertStatus(400);
    }

    public function test_can_delete_completed_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);
        $job->markAsProcessing();
        $job->markAsCompleted([]);

        $response = $this->actingAs($this->user)->deleteJson("/api/ai-jobs/{$job->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('ai_jobs', ['id' => $job->id]);
    }

    public function test_cannot_delete_active_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);

        $response = $this->actingAs($this->user)->deleteJson("/api/ai-jobs/{$job->id}");

        $response->assertStatus(400);
    }

    public function test_can_get_stats(): void
    {
        $job1 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '1']);
        $job2 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '2']);
        $job3 = AIJob::createJob($this->user->id, AIJob::TYPE_SMART_FILL, ['p' => '3']);

        $job1->markAsProcessing();
        $job1->markAsCompleted([]);

        $job2->markAsFailed('error');

        $response = $this->actingAs($this->user)->getJson('/api/ai-jobs/stats');

        $response->assertOk();
        $response->assertJson([
            'total' => 3,
            'completed' => 1,
            'failed' => 1,
            'active' => 1,
        ]);
    }

    public function test_can_clear_history(): void
    {
        $job1 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '1']);
        $job2 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '2']);
        $job3 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '3']);

        $job1->markAsProcessing();
        $job1->markAsCompleted([]);
        $job2->markAsFailed('error');
        // job3 stays pending

        $response = $this->actingAs($this->user)->deleteJson('/api/ai-jobs/history');

        $response->assertOk();
        $response->assertJson(['deleted_count' => 2]);

        // Active job should still exist
        $this->assertDatabaseHas('ai_jobs', ['id' => $job3->id]);
        $this->assertDatabaseMissing('ai_jobs', ['id' => $job1->id]);
    }
}
