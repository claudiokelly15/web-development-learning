<?php

namespace Tests\Support;

use App\Actions\Merchants\DeniedSubmissionAdmission;
use App\Actions\Merchants\SubmissionAdmission;
use App\Models\Merchant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait MerchantApplicationSubmissionJourney
{
    use MerchantOnboardingJourney {
        setUp as private setUpOnboarding;
    }

    private ApplicationSubmissionHttpHarness $submission;

    protected function setUp(): void
    {
        $this->setUpOnboarding();
        $this->travelTo(Carbon::createFromTimestampMs(1794614400000, 'UTC'));
        $this->assertInstanceOf(DeniedSubmissionAdmission::class, app(SubmissionAdmission::class));
        $this->submission = new ApplicationSubmissionHttpHarness($this->harness);
        $this->submission->register();
    }

    private function preparedApplication(): array
    {
        [$owner, $merchant] = $this->journey();
        $application = $merchant->applications()->sole();
        $membership = $merchant->memberships()->where('user_id', $owner->id)->sole();
        $reference = $this->prepare($owner, $merchant);
        $this->raw($merchant, $this->body($reference))->assertOk()->assertExactJson(['status' => 'completed']);
        $this->assertSame($application->id, $merchant->applications()->sole()->id);
        $this->assertSame($membership->id, $merchant->memberships()->where('user_id', $owner->id)->sole()->id);
        $this->assertSame(2, $application->fresh()->profile_version);
        $this->assertSame(2, $owner->fresh()->email_binding_version);
        $this->get(route('merchants.applications', ['merchant' => $merchant->id]))->assertOk()->assertSee('Draft')->assertSee('Submission unavailable');
        $ref = $this->submission->prepare($owner, $merchant, session()->getId());
        $this->assertSame(1, DB::table('contact_verification_proofs')->where('state', 'available')->count());
        $this->assertSame(0, DB::table('merchant_application_submission_evidence')->count());

        return [$owner, $merchant, $application->fresh(), $ref];
    }

    private function submitApplication(Merchant $merchant, string $reference, array $overrides = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $this->post('/merchants/'.$merchant->id.'/__isolated-submission', ['_token' => session()->token(), 'context_ref' => $reference, ...$overrides], ['Accept' => 'application/json']);
    }

    private function assertUnsubmitted(): void
    {
        $this->assertSame('draft', DB::table('merchant_applications')->value('status'));
        $this->assertSame(0, DB::table('merchant_application_submission_evidence')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
    }
}
