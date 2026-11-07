<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\B32CRehearsal;
use Tests\Support\SyntheticKeys;
use Tests\Support\SyntheticVerification;
use Tests\TestCase;

/** Run the accepted B assertions unchanged against the explicitly activated schema. */
class B32CContractCompatibilityTest extends B32SyntheticContractTest
{
    protected function setUp(): void
    {
        TestCase::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($contract['tables'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $keys = new SyntheticKeys;
        $pdo = DB::connection()->getPdo();
        $service = new SyntheticVerification($pdo, $keys);
        $owner = User::factory()->create(['email' => 'c-contract-owner@example.test']);
        $merchant = app(CreateMerchant::class)->execute($owner, 'Synthetic C compatibility');
        $binding = ['merchant' => $merchant->id, 'actor' => $owner->id, 'contact' => (string) Str::ulid(), 'purpose' => 'application_phone'];
        foreach (['keys' => $keys, 'pdo' => $pdo, 'service' => $service, 'binding' => $binding, 'application' => $merchant->applications()->sole()->id] as $field => $value) {
            (new ReflectionProperty(B32SyntheticContractTest::class, $field))->setValue($this, $value);
        }
        (new ReflectionMethod(B32SyntheticContractTest::class, 'contact'))->invoke($this, $binding['contact']);
        $service->insert('merchant_onboarding_contexts', ['merchant_id' => $merchant->id, 'current_contact_id' => $binding['contact'], 'contact_actor_user_id' => $owner->id, 'contact_purpose' => 'application_phone', 'updated_at' => 1793923200000]);
    }
}
