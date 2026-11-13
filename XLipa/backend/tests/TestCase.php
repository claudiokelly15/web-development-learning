<?php

namespace Tests;

use App\Actions\Merchants\BusinessNameCompletionSchema;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\B32CRehearsal;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->useEnvironmentPath(__DIR__)->loadEnvironmentFrom('__no_real_environment');
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();
        if (! $app->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url') || config('mail.default') !== 'array') {
            throw new \LogicException('Synthetic environment isolation failed.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['workflow_schema.expected_profile' => function (): string {
            foreach ([
                'bnc1.selected' => [BusinessNameCompletionSchema::artifact(), WorkflowSchemaReadiness::BNC],
                'se1.selected' => [SubmissionEvidenceSchema::artifact(), WorkflowSchemaReadiness::SE],
                'b32c.selected' => [B32CRehearsal::artifact(), WorkflowSchemaReadiness::C],
            ] as $key => [$artifact, $profile]) {
                if (config($key) !== null) {
                    return config($key) === realpath($artifact) ? $profile : 'invalid-fixture-selection';
                }
            }

            return WorkflowSchemaReadiness::FOUNDATION;
        }]);
    }
}
