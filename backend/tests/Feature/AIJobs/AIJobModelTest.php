<?php

namespace Tests\Feature\AIJobs;

use App\Models\AIJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIJobModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_ai_job(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_PRODUCT_IDENTIFICATION,
            inputData: ['query' => 'test product'],
        );

        $this->assertDatabaseHas('ai_jobs', [
            'id' => $job->id,
            'user_id' => $this->user->id,
            'type' => AIJob::TYPE_PRODUCT_IDENTIFICATION,
            'status' => AIJob::STATUS_PENDING,
        ]);

        $this->assertEquals(['query' => 'test product'], $job->input_data);
    }

    public function test_can_mark_job_as_processing(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_PRICE_SEARCH,
            inputData: ['query' => 'test'],
        );

        $job->markAsProcessing();

        $this->assertEquals(AIJob::STATUS_PROCESSING, $job->status);
        $this->assertNotNull($job->started_at);
    }

    public function test_can_mark_job_as_completed(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_SMART_FILL,
            inputData: ['product_name' => 'test'],
        );

        $job->markAsProcessing();
        $job->markAsCompleted(['result' => 'success']);

        $this->assertEquals(AIJob::STATUS_COMPLETED, $job->status);
        $this->assertEquals(100, $job->progress);
        $this->assertEquals(['result' => 'success'], $job->output_data);
        $this->assertNotNull($job->completed_at);
    }

    public function test_can_mark_job_as_failed(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_PRICE_REFRESH,
            inputData: ['item_id' => 1],
        );

        $job->markAsProcessing();
        $job->markAsFailed('Something went wrong');

        $this->assertEquals(AIJob::STATUS_FAILED, $job->status);
        $this->assertEquals('Something went wrong', $job->error_message);
        $this->assertNotNull($job->completed_at);
    }

    public function test_can_mark_job_as_cancelled(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_IMAGE_ANALYSIS,
            inputData: ['image' => 'base64data'],
        );

        $job->markAsCancelled();

        $this->assertEquals(AIJob::STATUS_CANCELLED, $job->status);
        $this->assertNotNull($job->completed_at);
    }

    public function test_can_update_progress(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_PRICE_SEARCH,
            inputData: ['query' => 'test'],
        );

        $job->updateProgress(50);

        $this->assertEquals(50, $job->progress);
    }

    public function test_progress_clamps_to_valid_range(): void
    {
        $job = AIJob::createJob(
            userId: $this->user->id,
            type: AIJob::TYPE_PRICE_SEARCH,
            inputData: ['query' => 'test'],
        );

        $job->updateProgress(150);
        $this->assertEquals(100, $job->progress);

        $job->updateProgress(-10);
        $this->assertEquals(0, $job->progress);
    }

    public function test_scope_for_user_filters_correctly(): void
    {
        $otherUser = User::factory()->create();

        AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'a']);
        AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'b']);
        AIJob::createJob($otherUser->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'c']);

        $jobs = AIJob::forUser($this->user->id)->get();

        $this->assertCount(2, $jobs);
    }

    public function test_scope_active_returns_pending_and_processing(): void
    {
        $job1 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '1']);
        $job2 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '2']);
        $job3 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '3']);
        $job4 = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '4']);

        $job2->markAsProcessing();
        $job3->markAsProcessing();
        $job3->markAsCompleted([]);
        $job4->markAsFailed('error');

        $activeJobs = AIJob::forUser($this->user->id)->active()->get();

        $this->assertCount(2, $activeJobs);
        $this->assertTrue($activeJobs->contains('id', $job1->id));
        $this->assertTrue($activeJobs->contains('id', $job2->id));
    }

    public function test_can_be_cancelled_returns_correct_value(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['q' => '1']);

        $this->assertTrue($job->canBeCancelled());

        $job->markAsProcessing();
        $this->assertTrue($job->canBeCancelled());

        $job->markAsCompleted([]);
        $this->assertFalse($job->canBeCancelled());
    }

    public function test_type_label_returns_human_readable(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRODUCT_IDENTIFICATION, ['q' => '1']);

        $this->assertEquals('Product Identification', $job->type_label);
    }

    public function test_input_summary_returns_correct_value(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'Sony WH-1000XM5']);

        $this->assertEquals('Sony WH-1000XM5', $job->input_summary);
    }
}
