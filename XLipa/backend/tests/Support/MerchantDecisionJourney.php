<?php

namespace Tests\Support;

use App\Models\Merchant;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait MerchantDecisionJourney
{
    use MerchantApplicationSubmissionJourney;

    private ApplicationDecisionHttpHarness $decisions;

    private function submitted(): array
    {
        [$owner, $merchant, $application, $ref] = $this->preparedApplication();
        $this->submitApplication($merchant, $ref)->assertOk()->assertExactJson(['status' => 'submitted']);
        $this->decisions = new ApplicationDecisionHttpHarness($this->harness, $this->submission);
        $this->decisions->register();

        return [$owner, $merchant, $application->fresh()];
    }

    private function chief(): User
    {
        $chief = User::factory()->create()->fresh();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $this->signIn($chief);

        return $chief;
    }

    private function requestDecision(Merchant $merchant, string $application, string $reference, array $extra = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $this->post('/__isolated-decisions/'.$merchant->id.'/'.$application, ['_token' => session()->token(), 'context_ref' => $reference, ...$extra], ['Accept' => 'application/json']);
    }

    private function assertHeld(string $application): void
    {
        $row = DB::table('merchant_applications')->where('id', $application)->sole();
        $this->assertSame('pending_review', $row->status);
        $this->assertNull($row->reviewed_at);
        $this->assertNull($row->reviewed_by_user_id);
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'application.reviewed')->count());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }
}
