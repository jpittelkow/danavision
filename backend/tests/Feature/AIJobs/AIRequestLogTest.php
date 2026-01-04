<?php

namespace Tests\Feature\AIJobs;

use App\Models\AIJob;
use App\Models\AIRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIRequestLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_log_entry(): void
    {
        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'claude',
            requestType: AIRequestLog::TYPE_COMPLETION,
            requestData: ['prompt' => 'test prompt'],
            aiJobId: null,
            model: 'claude-3-sonnet'
        );

        $this->assertDatabaseHas('ai_request_logs', [
            'id' => $log->id,
            'user_id' => $this->user->id,
            'provider' => 'claude',
            'model' => 'claude-3-sonnet',
            'request_type' => AIRequestLog::TYPE_COMPLETION,
            'status' => AIRequestLog::STATUS_PENDING,
        ]);
    }

    public function test_can_mark_log_as_success(): void
    {
        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'openai',
            requestType: AIRequestLog::TYPE_COMPLETION,
            requestData: ['prompt' => 'test'],
        );

        $log->markAsSuccess(
            responseData: ['response' => 'test response'],
            durationMs: 1500,
            tokensInput: 100,
            tokensOutput: 50,
        );

        $log->refresh();
        $this->assertEquals(AIRequestLog::STATUS_SUCCESS, $log->status);
        $this->assertEquals(1500, $log->duration_ms);
        $this->assertEquals(100, $log->tokens_input);
        $this->assertEquals(50, $log->tokens_output);
    }

    public function test_can_mark_log_as_failed(): void
    {
        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'gemini',
            requestType: AIRequestLog::TYPE_COMPLETION,
            requestData: ['prompt' => 'test'],
        );

        $log->markAsFailed('API rate limit exceeded', 5000);

        $log->refresh();
        $this->assertEquals(AIRequestLog::STATUS_FAILED, $log->status);
        $this->assertEquals('API rate limit exceeded', $log->error_message);
        $this->assertEquals(5000, $log->duration_ms);
    }

    public function test_can_store_serp_data(): void
    {
        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'claude',
            requestType: AIRequestLog::TYPE_PRICE_AGGREGATION,
            requestData: ['prompt' => 'aggregate prices'],
        );

        $serpData = [
            'shopping_results' => [
                ['title' => 'Product 1', 'price' => 29.99],
                ['title' => 'Product 2', 'price' => 34.99],
            ],
            'search_parameters' => ['q' => 'test product'],
        ];

        $log->markAsSuccess(
            responseData: ['aggregated' => true],
            durationMs: 2000,
            serpData: $serpData,
        );

        $log->refresh();
        $this->assertEquals($serpData, $log->serp_data);
    }

    public function test_can_link_to_ai_job(): void
    {
        $job = AIJob::createJob($this->user->id, AIJob::TYPE_PRICE_SEARCH, ['query' => 'test']);

        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'claude',
            requestType: AIRequestLog::TYPE_PRICE_AGGREGATION,
            requestData: ['prompt' => 'test'],
            aiJobId: $job->id,
        );

        $this->assertEquals($job->id, $log->ai_job_id);
        $this->assertEquals($job->id, $log->aiJob->id);
    }

    public function test_scope_for_user(): void
    {
        $otherUser = User::factory()->create();

        AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);
        AIRequestLog::createLog($this->user->id, 'openai', AIRequestLog::TYPE_COMPLETION, []);
        AIRequestLog::createLog($otherUser->id, 'gemini', AIRequestLog::TYPE_COMPLETION, []);

        $logs = AIRequestLog::forUser($this->user->id)->get();

        $this->assertCount(2, $logs);
    }

    public function test_scope_successful(): void
    {
        $log1 = AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);
        $log2 = AIRequestLog::createLog($this->user->id, 'openai', AIRequestLog::TYPE_COMPLETION, []);

        $log1->markAsSuccess([], 1000);
        $log2->markAsFailed('error', 500);

        $successfulLogs = AIRequestLog::forUser($this->user->id)->successful()->get();

        $this->assertCount(1, $successfulLogs);
        $this->assertEquals($log1->id, $successfulLogs->first()->id);
    }

    public function test_formatted_duration(): void
    {
        $log = AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);

        $log->markAsSuccess([], 500);
        $this->assertEquals('500ms', $log->formatted_duration);

        $log->update(['duration_ms' => 2500]);
        $log->refresh();
        $this->assertEquals('2.5s', $log->formatted_duration);

        $log->update(['duration_ms' => 65000]);
        $log->refresh();
        $this->assertEquals('1m 5s', $log->formatted_duration);
    }

    public function test_total_tokens(): void
    {
        $log = AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);

        $log->markAsSuccess([], 1000, 100, 50);

        $this->assertEquals(150, $log->total_tokens);
    }

    public function test_truncated_prompt(): void
    {
        $log = AIRequestLog::createLog(
            userId: $this->user->id,
            provider: 'claude',
            requestType: AIRequestLog::TYPE_COMPLETION,
            requestData: ['prompt' => str_repeat('a', 500)],
        );

        $truncated = $log->getTruncatedPrompt(100);

        $this->assertEquals(103, strlen($truncated)); // 100 + '...'
        $this->assertStringEndsWith('...', $truncated);
    }

    public function test_get_stats_for_user(): void
    {
        $log1 = AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);
        $log2 = AIRequestLog::createLog($this->user->id, 'claude', AIRequestLog::TYPE_COMPLETION, []);
        $log3 = AIRequestLog::createLog($this->user->id, 'openai', AIRequestLog::TYPE_COMPLETION, []);

        $log1->markAsSuccess([], 1000, 100, 50);
        $log2->markAsSuccess([], 500, 80, 40);
        $log3->markAsFailed('error', 200);

        $stats = AIRequestLog::getStatsForUser($this->user->id);

        $this->assertEquals(3, $stats['total_requests']);
        $this->assertEquals(2, $stats['successful_requests']);
        $this->assertEquals(1, $stats['failed_requests']);
        $this->assertEqualsWithDelta(66.7, $stats['success_rate'], 0.1);
        $this->assertEquals(270, $stats['total_tokens']); // (100+50) + (80+40)
        $this->assertEquals(['claude' => 2, 'openai' => 1], $stats['by_provider']);
    }
}
