<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

/**
 * Extends Laragear's AttestedRequest to catch attestation validation exceptions
 * that would otherwise bubble up as unhandled 500 errors.
 *
 * The parent's passedValidation() runs the full AttestationValidator pipeline
 * (CBOR decode, RP ID check, origin check, challenge check, etc.) without
 * try/catch, so any failure there becomes a 500 instead of a user-friendly error.
 */
class SafeAttestedRequest extends AttestedRequest
{
    protected function passedValidation(): void
    {
        try {
            parent::passedValidation();
        } catch (\Throwable $e) {
            Log::warning('Passkey attestation validation failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw ValidationException::withMessages([
                'passkey' => 'Passkey verification failed. Please try again.',
            ]);
        }
    }
}
