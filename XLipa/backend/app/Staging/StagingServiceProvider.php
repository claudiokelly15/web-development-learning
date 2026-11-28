<?php

namespace App\Staging;

use App\Actions\Merchants\ApplicationDecisionAdmission;
use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\SubmissionAdmission;
use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use App\Actions\Merchants\VerificationEnvironment;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        StagingIdentity::check($this->app->bound('staging.context'));
        $this->app->bind(EnsureAuthenticationReady::class, StagingAuthenticationReady::class);
    }

    public function boot(): void
    {
        if (($this->app->make('staging.context')['financial_program'] ?? null) === StagingFinancialAuthority::VERSION) {
            $this->app->make('router')->pushMiddlewareToGroup('web', StagingFinancialMiddleware::class);
        }
        if (($this->app->make('staging.context')['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION) {
            $this->app->bind(BusinessNameCompletionAdmission::class, StagingBusinessNameAdmission::class);
            $this->app->bind(VerificationEnvironment::class, StagingVerificationEnvironment::class);
            $this->app->bind(SubmissionEvidenceEnvironment::class, StagingEvidenceEnvironment::class);
            $this->app->bind(SubmissionAdmission::class, StagingSubmissionAdmission::class);
            $this->app->bind(ApplicationDecisionAdmission::class, StagingDecisionAdmission::class);
            $this->app->make('router')->pushMiddlewareToGroup('web', StagingWorkflowMiddleware::class);
        }
        Http::globalMiddleware(function (): never {
            throw new \LogicException('External communication is denied in staging.');
        });
        Event::listen(NotificationSending::class, function (NotificationSending $event): bool {
            return $event->channel === 'mail' && ($event->notification instanceof VerifyEmail || $event->notification instanceof ResetPassword)
                && str_ends_with($event->notifiable->email, '@example.test');
        });
        $this->app->make('router')->pushMiddlewareToGroup('web', StagingReadAdmission::class);
        $this->app->booted(function (): void {
            if ((app('staging.context')['financial_program'] ?? null) === StagingFinancialAuthority::VERSION) {
                Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class, 'throttle:60,1'])
                    ->prefix('/merchants/{merchant}/payment-products')->whereUlid('merchant')->group(function (): void {
                        Route::get('/', [StagingFinancialController::class, 'handle'])->name('r24.links.index');
                        Route::post('/links', [StagingFinancialController::class, 'handle'])->name('r24.links.create');
                        Route::post('/payments', [StagingFinancialController::class, 'handle']);
                        Route::get('/transactions', [StagingFinancialController::class, 'handle'])->name('r24.transactions');
                        Route::post('/links/{link}/disable', [StagingFinancialController::class, 'handle'])->whereUuid('link')->name('r24.links.disable');
                        Route::post('/links/{link}/widgets', [StagingFinancialController::class, 'handle'])->whereUuid('link');
                        Route::get('/widgets/{widget}', [StagingFinancialController::class, 'handle'])->whereUuid('widget');
                        Route::get('/payments/{payment}', [StagingFinancialController::class, 'handle'])->whereUuid('payment');
                        foreach (['query', 'mock-confirmation'] as $operation) {
                            Route::post('/payments/{payment}/'.$operation, [StagingFinancialController::class, 'handle'])->whereUuid('payment');
                        }
                        Route::post('/attempts/{attempt}/post', [StagingFinancialController::class, 'handle'])->whereUuid('attempt')->name('r24.links.consume');
                        Route::post('/attempts/{attempt}/reconcile', [StagingFinancialController::class, 'handle'])->whereUuid('attempt')->name('r24.links.reconcile');
                    });
                Route::middleware(['web', 'guest', 'throttle:20,1'])->prefix('/fictional-checkout/{token}')->where(['token' => '[a-f0-9]{64}'])->group(function (): void {
                    Route::get('/', [StagingFinancialController::class, 'handle'])->name('r24.checkout.open');
                    Route::post('/', [StagingFinancialController::class, 'handle'])->name('r24.checkout.initiate');
                });
            }
            if ((app('staging.context')['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION) {
                Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
                    ->prefix('/merchants/{merchant}/applications/integrated')->whereUlid('merchant')->group(function (): void {
                        Route::get('/', [StagingApplicationController::class, 'show'])->name('staging.application.form');
                        Route::post('/submit', [StagingApplicationController::class, 'submit'])->name('staging.application.submit');
                        Route::post('/resolve', [StagingApplicationController::class, 'submit'])->name('staging.application.resolve');
                    });
                Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
                    ->prefix('/admin/applications/{application}/decision')->whereUlid('application')->group(function (): void {
                        Route::get('/', [StagingApplicationController::class, 'review'])->name('staging.application.review');
                        Route::post('/', [StagingApplicationController::class, 'decide'])->name('staging.application.decision');
                    });
                Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
                    ->prefix('/merchants/{merchant}/onboarding')->whereUlid('merchant')->group(function (): void {
                        Route::get('/complete', [StagingBusinessNameController::class, 'show'])->name('staging.onboarding.form');
                        Route::post('/complete', [StagingBusinessNameController::class, 'store'])->name('staging.onboarding.name');
                        Route::post('/complete/resolve', [StagingBusinessNameController::class, 'store'])->name('staging.onboarding.resolve');
                    });
            }
            Route::middleware('web')->get('/staging/status', fn () => response()->json([
                'namespace' => 'xlipa-fictional-r21', 'runtime' => app('staging.context')['runtime'],
                'sapi' => PHP_SAPI, 'console' => app()->runningInConsole(), 'testing' => app()->runningUnitTests(),
                'profile' => 'foundation', 'financial' => 'denied',
                'financial_schema' => app('staging.context')['financial_profile'] ?? 'unprovisioned-r22',
                'schema_ready' => (app('staging.context')['financial_profile'] ?? null) === FinancialSchema::VERSION,
            ]));
            Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
                ->get('/staging/admission', fn () => response()->json(['assurance' => 'fictional-only', 'action' => 'foundation.probe']));
            Route::middleware(['web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
                ->post('/staging/command', fn () => response()->json(['consumed' => true, 'financial_effect' => false]));
        });
    }
}
