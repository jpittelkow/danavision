<?php

use App\Services\LLM\LLMOrchestrator;
use App\Models\User;

describe('LLMOrchestrator', function () {
    
    beforeEach(function () {
        $this->orchestrator = new LLMOrchestrator();
    });

    describe('query', function () {
        it('throws exception when no providers are configured', function () {
            $user = User::factory()->create();

            expect(fn () => $this->orchestrator->query($user, 'Hello'))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('visionQuery', function () {
        it('throws exception when no vision providers are configured', function () {
            $user = User::factory()->create();

            expect(fn () => $this->orchestrator->visionQuery(
                $user, 
                'What is in this image?',
                'base64data',
                'image/jpeg'
            ))->toThrow(RuntimeException::class);
        });
    });

    describe('testProvider', function () {
        it('throws exception for unconfigured provider', function () {
            $user = User::factory()->create();

            expect(fn () => $this->orchestrator->testProvider($user, 'claude'))
                ->toThrow(RuntimeException::class);
        });
    });
});
