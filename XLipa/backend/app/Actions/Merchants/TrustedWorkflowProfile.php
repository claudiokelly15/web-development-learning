<?php

namespace App\Actions\Merchants;

use RuntimeException;
use Throwable;

/** Isolated descriptor validation; never provisions config or replaces schema readiness. */
final class TrustedWorkflowProfile
{
    /**
     * Runtime identity and observed artifacts must come from independently trusted custody.
     * This class measures no files/store, infers no schema and caches no successful admission.
     * An operational successor release is required before any provider/source changes.
     *
     * @param  array|null  $descriptor  Exact approved descriptor, not an HTTP payload.
     * @param  array  $runtime  requested_profile, environment, store_id, generation, fence_epoch, worker_id
     * @param  array  $observed  manifests (bnc/integration/backend) and exact sources SHA-256 maps
     */
    public function select(#[\SensitiveParameter] ?array $descriptor, #[\SensitiveParameter] array $runtime, #[\SensitiveParameter] array $observed, TrustedReleaseApproval $approval, TrustedCompletionTimePolicy $time): array
    {
        try {
            $keys = ['version', 'requested_profile', 'approved_profile', 'release_id', 'manifests', 'sources', 'environment', 'store_id', 'generation', 'fence_epoch', 'worker_id', 'approval_id', 'provenance', 'not_before', 'expires_at', 'revoked'];
            $this->check($descriptor !== null && count($descriptor) === count($keys) && ! array_diff(array_keys($descriptor), $keys));
            $runtimeKeys = ['requested_profile', 'environment', 'store_id', 'generation', 'fence_epoch', 'worker_id'];
            $this->check(count($runtime) === count($runtimeKeys) && ! array_diff(array_keys($runtime), $runtimeKeys));
            $this->check($descriptor['version'] === 1 && $descriptor['revoked'] === false);
            $profiles = [WorkflowSchemaReadiness::FOUNDATION, WorkflowSchemaReadiness::C, WorkflowSchemaReadiness::SE, WorkflowSchemaReadiness::BNC];
            $this->check(in_array($descriptor['requested_profile'], $profiles, true) && $descriptor['requested_profile'] === $descriptor['approved_profile']);
            foreach ($runtimeKeys as $key) {
                $this->check($runtime[$key] === $descriptor[$key]);
            }
            foreach (['release_id', 'environment', 'store_id', 'generation', 'worker_id', 'approval_id', 'provenance'] as $key) {
                $this->check(is_string($descriptor[$key]) && preg_match('/\A[a-zA-Z0-9._-]{1,128}\z/D', $descriptor[$key]) === 1);
            }
            $this->check(is_int($descriptor['fence_epoch']) && $descriptor['fence_epoch'] > 0);
            $this->check(count($observed) === 2 && isset($observed['manifests'], $observed['sources']));
            $this->check(is_array($descriptor['manifests']) && count($descriptor['manifests']) === 3 && ! array_diff(array_keys($descriptor['manifests']), ['bnc', 'integration', 'backend']));
            foreach (['manifests', 'sources'] as $key) {
                $this->check(is_array($descriptor[$key]) && $descriptor[$key] !== [] && is_array($observed[$key]));
                $expected = $descriptor[$key];
                $actual = $observed[$key];
                foreach ($expected as $path => $digest) {
                    $this->check(is_string($path) && $path !== '' && is_string($digest) && preg_match('/\A[0-9a-f]{64}\z/D', $digest) === 1);
                }
                ksort($expected);
                ksort($actual);
                $this->check($expected === $actual);
            }
            $now = $time->milliseconds();
            $this->check($now >= 0 && $now <= 253402300799999 && is_int($descriptor['not_before']) && is_int($descriptor['expires_at']) && $descriptor['not_before'] >= 0 && $descriptor['not_before'] <= $now && $now < $descriptor['expires_at'] && $descriptor['expires_at'] <= 253402300799999);
            $this->check($approval->approves($descriptor));

            return $descriptor;
        } catch (Throwable) {
            throw new RuntimeException('Trusted workflow admission is unavailable.');
        }
    }

    private function check(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Trusted workflow admission is unavailable.');
        }
    }
}
