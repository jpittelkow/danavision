<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\UserOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get the current user's onboarding status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = UserOnboarding::forUser($user);

        return $this->dataResponse([
            'wizard_completed' => $onboarding->isWizardCompleted(),
            'wizard_dismissed' => $onboarding->isWizardDismissed(),
            'wizard_completed_at' => $onboarding->wizard_completed_at?->toISOString(),
            'wizard_dismissed_at' => $onboarding->wizard_dismissed_at?->toISOString(),
            'steps_completed' => $onboarding->steps_completed ?? [],
            'show_wizard' => !$onboarding->isWizardCompleted() && !$onboarding->isWizardDismissed(),
        ]);
    }

    /**
     * Mark the wizard as complete.
     */
    public function completeWizard(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = UserOnboarding::forUser($user);
        $onboarding->completeWizard();

        return $this->successResponse('Wizard completed successfully');
    }

    /**
     * Dismiss the wizard without completing it.
     */
    public function dismissWizard(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = UserOnboarding::forUser($user);
        $onboarding->dismissWizard();

        return $this->successResponse('Wizard dismissed');
    }

    /**
     * Mark a specific step as complete.
     */
    public function completeStep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $onboarding = UserOnboarding::forUser($user);
        $onboarding->markStepComplete($validated['step']);

        return $this->dataResponse([
            'steps_completed' => $onboarding->steps_completed,
        ]);
    }

    /**
     * Reset the wizard to show it again.
     */
    public function resetWizard(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = UserOnboarding::forUser($user);
        $onboarding->resetWizard();

        return $this->successResponse('Wizard reset successfully');
    }
}
