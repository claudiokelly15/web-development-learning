<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionContext;
use App\Actions\Merchants\BusinessNameCompletionHttpInput;
use App\Actions\Merchants\BusinessNameCompletionRequestContext;
use App\Actions\Merchants\DeniedBusinessNameCompletionAdmission;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\FakeBusinessNameCompletionRequestContext;
use Tests\TestCase;

class BusinessNameCompletionHttpPreparationTest extends TestCase
{
    private const TYPE = 'application/x-www-form-urlencoded';

    private const MERCHANT = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private function binding(): array
    {
        return ['actor_id' => 7, 'merchant_id' => self::MERCHANT, 'session_id' => 'fictional-session', 'generation' => 'fixture-generation-1'];
    }

    private function expected(): array
    {
        return ['application_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'attempt_number' => 1, 'merchant_profile_version' => 1, 'draft_profile_version' => 1, 'membership_id' => 1, 'authority_version' => 1, 'auth_version' => 1, 'email_binding_version' => 1, 'completion_request_id' => '12345678-1234-1234-1234-123456789abc'];
    }

    private function body(string $name = 'Kijani Studio'): string
    {
        return '_token=fictional-token&context_ref=opaque-reference&business_name='.rawurlencode($name);
    }

    private function fake(bool $authorized = true): FakeBusinessNameCompletionRequestContext
    {
        return new FakeBusinessNameCompletionRequestContext(fn (array $binding, string $reference): bool => $authorized);
    }

    private function prepare(FakeBusinessNameCompletionRequestContext $fake, ?array $expected = null): string
    {
        return $fake->prepare($this->binding(), $expected ?? $this->expected(), 'PRIVATE Kijani 🌿', 100, 200, 400);
    }

    private function denied(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected private context denial.');
        } catch (RuntimeException $error) {
            $this->assertSame('Private completion context is unavailable.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_minimal_requests_and_exact_name_bytes_are_preserved(): void
    {
        $input = new BusinessNameCompletionHttpInput;
        $name = "  Kijani  e\u{0301} & + =;🌿  ";
        $parsed = $input->parse(self::TYPE.'; charset="UTF-8"', $this->body($name));
        $this->assertSame(['_token' => 'fictional-token', 'context_ref' => 'opaque-reference', 'business_name' => trim($name, ' ')], $parsed);
        $this->assertSame('Kijani Studio', $input->parse(self::TYPE, '_token=x&context_ref=a&business_name=Kijani+Studio')['business_name']);
        $this->assertSame(['_token' => 'x', 'context_ref' => 'a'], $input->parse(self::TYPE, '_token=x&context_ref=a', '', true));
        $this->assertSame(str_repeat('🌿', 200), $input->parse(self::TYPE, $this->body(str_repeat('🌿', 200)))['business_name']);
    }

    public static function badBodies(): array
    {
        $base = '_token=x&context_ref=a&business_name=Kijani';

        return [
            [$base.'&business_name=Other'], [$base.'&_token=y'], [$base.'&context_ref=b'],
            ['_token=x&context_ref=a&business%5Fname=Kijani'], [$base.'&%62usiness_name=Other'],
            [$base.'&business_name[]=Other'], [$base.'&business_name%5B%5D=Other'],
            [$base.'&business.name=Other'], [$base.'&business+name=Other'],
            [$base.'&_method=PUT'], [$base.'&merchant_id=foreign'], [$base.'&auth_version=1'],
            [$base.'&unknown=x'], [$base.'&'], ['&'.$base], [str_replace('&context_ref=a', '', $base)],
            [str_replace('Kijani', '%', $base)], [str_replace('Kijani', '%2', $base)],
            [str_replace('Kijani', '%GG', $base)], [str_replace('Kijani', '%C0%AF', $base)],
            [str_replace('Kijani', '%ED%A0%80', $base)], [str_replace('Kijani', "\xff", $base)],
            [str_replace('Kijani', '%00Kijani', $base)], [str_replace('Kijani', '%09Kijani', $base)],
            [str_replace('Kijani', 'Kijani%0A', $base)], [str_replace('context_ref=a', 'context_ref=', $base)],
            [str_replace('context_ref=a', 'context_ref=%C3%A9', $base)],
            [str_replace('context_ref=a', 'context_ref=a.b', $base)],
            [str_replace('context_ref=a', 'context_ref='.str_repeat('a', 129), $base)],
            [str_replace('_token=x', '_token=', $base)], [str_replace('_token=x', '_token=%0A', $base)],
            [$base.'=ambiguous'], [$base.'&=empty'], [''],
        ];
    }

    #[DataProvider('badBodies')]
    public function test_raw_parameter_attacks_fail_with_generic_errors(string $body): void
    {
        try {
            (new BusinessNameCompletionHttpInput)->parse(self::TYPE, $body);
            $this->fail('Expected input denial.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame('Business name completion input could not be accepted.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public static function badEnvelope(): array
    {
        return [['application/json', '', false], ['multipart/form-data', '', false], ['text/plain', '', false],
            [self::TYPE.'; charset=ISO-8859-1', '', false], [self::TYPE.'; charset=UTF-8; charset=UTF-8', '', false],
            [self::TYPE, 'role=owner', false], [self::TYPE, '?', false], [self::TYPE, '', true]];
    }

    #[DataProvider('badEnvelope')]
    public function test_content_type_query_and_resolution_allowlist(string $type, string $query, bool $resolving): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BusinessNameCompletionHttpInput)->parse($type, $this->body(), $query, $resolving);
    }

    public static function badNames(): array
    {
        return [[''], ['   '], ["\tA"], ["A\n"], ["A\u{200B}B"], ["A\u{FEFF}"],
            ["A\u{2028}B"], ["A\u{2029}B"], ["\u{00A0}A"], ["A\u{2003}"], [str_repeat('a', 201)]];
    }

    #[DataProvider('badNames')]
    public function test_unicode_rejections_match_frozen_internal_contract(string $name): void
    {
        try {
            (new BusinessNameCompletionHttpInput)->parse(self::TYPE, $this->body($name));
            $this->fail('Expected strict name denial.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
        $context = new BusinessNameCompletionContext(new DeniedBusinessNameCompletionAdmission);
        try {
            $context->input(self::MERCHANT, $this->expected(), $name);
            $this->fail('Expected existing internal validator denial.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(422, $error->getStatusCode());
        }
    }

    public function test_interior_unicode_spacing_and_canonical_equivalence_are_not_normalized(): void
    {
        $input = new BusinessNameCompletionHttpInput;
        $context = new BusinessNameCompletionContext(new DeniedBusinessNameCompletionAdmission);
        foreach (["A\u{00A0}B", 'é', "e\u{0301}", '123', 'A & B + C', str_repeat('x', 200)] as $name) {
            $this->assertSame($context->input(self::MERCHANT, $this->expected(), $name), $input->parse(self::TYPE, $this->body($name))['business_name']);
        }
    }

    public function test_transport_caps_at_and_over_each_boundary(): void
    {
        $input = new BusinessNameCompletionHttpInput;
        $base = '_token=x&context_ref='.str_repeat('r', 128).'&business_name=';
        $this->assertSame('A', $input->parse(self::TYPE, $base.str_repeat('%20', 1023).'A')['business_name']);
        $this->assertSame('A', $input->parse(self::TYPE, $base.str_repeat('%20', 1023).'%41')['business_name']);
        $exact = '_token='.str_repeat('%78', 255).'&context_ref='.str_repeat('%72', 48).str_repeat('r', 80).'&business_name='.str_repeat('%20', 1023).'%41';
        $this->assertSame(4096, strlen($exact));
        $this->assertSame('A', $input->parse(self::TYPE, $exact)['business_name']);
        foreach ([$base.str_repeat('%20', 1024).'A', $base.str_repeat(' ', 1024).'A', $exact.' ', str_repeat('a', 4097)] as $body) {
            try {
                $input->parse(self::TYPE, $body);
                $this->fail('Expected transport cap denial.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public static function badExpectations(): array
    {
        return [['attempt_number', '1'], ['attempt_number', 0], ['membership_id', -1], ['auth_version', true],
            ['authority_version', 1.0], ['merchant_profile_version', '9223372036854775808'],
            ['draft_profile_version', []], ['completion_request_id', '12345678-1234-1234-1234-123456789ABC'],
            ['completion_request_id', '12345678123412341234123456789abc'], ['application_id', '81ARZ3NDEKTSV4RRFFQ69G5FAW']];
    }

    #[DataProvider('badExpectations')]
    public function test_server_expectations_are_exact_typed_and_bounded(string $field, mixed $value): void
    {
        $expected = $this->expected();
        $expected[$field] = $value;
        $this->expectException(InvalidArgumentException::class);
        $this->prepare($this->fake(), $expected);
    }

    public function test_uuid_and_integer_maxima_do_not_coerce_or_enforce_new_uuid_variant(): void
    {
        $expected = $this->expected();
        foreach (['attempt_number', 'merchant_profile_version', 'draft_profile_version', 'membership_id', 'authority_version', 'auth_version', 'email_binding_version'] as $field) {
            $expected[$field] = PHP_INT_MAX;
        }
        $expected['completion_request_id'] = '00000000-0000-0000-0000-000000000000';
        $fake = $this->fake();
        $reference = $this->prepare($fake, $expected);
        $this->assertSame($expected, $fake->dispatch($reference, $this->binding(), 110)['expected']);
        foreach ([array_diff_key($expected, ['auth_version' => true]), $expected + ['extra' => 1]] as $bad) {
            try {
                (new BusinessNameCompletionHttpInput)->expectations(self::MERCHANT, $bad);
                $this->fail('Expected strict allowlist denial.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public static function wrongBindings(): array
    {
        return [['actor_id', 8], ['actor_id', '7'], ['merchant_id', '01ARZ3NDEKTSV4RRFFQ69G5FAX'],
            ['session_id', 'other-session'], ['generation', 'fixture-generation-2']];
    }

    #[DataProvider('wrongBindings')]
    public function test_wrong_actor_tenant_session_and_generation_cannot_access_context(string $field, mixed $value): void
    {
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $binding = $this->binding();
        $binding[$field] = $value;
        $this->denied(fn () => $fake->dispatch($ref, $binding, 110));
        $this->denied(fn () => $fake->status($ref, $binding, 110));
        $private = $fake->dispatch($ref, $this->binding(), 110);
        $this->denied(fn () => $fake->finish($ref, $binding, $private['token'], ['status' => 'unchanged'], 111));
        $this->denied(fn () => $fake->beginResolution($ref, $binding, 112));
    }

    public function test_double_click_and_lost_dispatch_ack_never_replay_or_replace_uuid(): void
    {
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $private = $fake->dispatch($ref, $this->binding(), 110);
        $this->assertSame($this->expected(), $private['expected']);
        $this->assertSame('PRIVATE Kijani 🌿', $private['name']);
        $this->assertSame(['state' => 'unresolved', 'resolution' => null], $fake->status($ref, $this->binding(), 111));
        $this->denied(fn () => $fake->dispatch($ref, $this->binding(), 112));
        $fake->unresolved($ref, $this->binding(), $private['token'], 113);
        $this->denied(fn () => $fake->finish($ref, $this->binding(), $private['token'], ['status' => 'unchanged'], 114));
        $replacement = $this->expected();
        $replacement['completion_request_id'] = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $this->denied(fn () => $this->prepare($fake, $replacement));
        $resolution = $fake->beginResolution($ref, $this->binding(), 115);
        $this->assertSame($private['expected'], $resolution['expected']);
        $this->assertSame($private['name'], $resolution['name']);
    }

    public function test_expiry_and_retention_never_erase_or_discharge_uncertainty(): void
    {
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $this->denied(fn () => $fake->dispatch($ref, $this->binding(), 200));
        $other = $this->fake();
        $ref = $this->prepare($other);
        $private = $other->dispatch($ref, $this->binding(), 110);
        $other->unresolved($ref, $this->binding(), $private['token'], 210);
        $resolution = $other->beginResolution($ref, $this->binding(), 250);
        $this->assertSame($private['name'], $resolution['name']);
        $this->denied(fn () => $other->beginResolution($ref, $this->binding(), 400));
        $this->assertSame('unresolved', $other->status($ref, $this->binding(), 500)['state']);
        $this->denied(fn () => $this->prepare($other));
    }

    public static function resolutionResults(): array
    {
        return [['confirmed', 'completed'], ['absent', 'unresolved'], ['historical', 'unresolved'], ['held', 'unresolved'], ['unknown', 'unresolved']];
    }

    #[DataProvider('resolutionResults')]
    public function test_explicit_resolution_classifications_are_conservative(string $status, string $state): void
    {
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $fake->dispatch($ref, $this->binding(), 110);
        $private = $fake->beginResolution($ref, $this->binding(), 111);
        $result = ['status' => $status];
        if (in_array($status, ['confirmed', 'historical'], true)) {
            $result['completion_id'] = '01ARZ3NDEKTSV4RRFFQ69G5FAY';
        }
        if ($status === 'confirmed') {
            $result['resulting_profile_version'] = 2;
        }
        $this->assertSame(['state' => $state, 'resolution' => $status], $fake->finishResolution($ref, $this->binding(), $private['token'], $result, 112));
        $this->denied(fn () => $fake->dispatch($ref, $this->binding(), 113));
        $this->denied(fn () => $fake->finishResolution($ref, $this->binding(), $private['token'], $result, 114));
    }

    public function test_resolution_authority_is_independent_and_rechecked(): void
    {
        $allow = false;
        $fake = new FakeBusinessNameCompletionRequestContext(function () use (&$allow): bool {
            return $allow;
        });
        $ref = $this->prepare($fake);
        $fake->dispatch($ref, $this->binding(), 110);
        $this->denied(fn () => $fake->beginResolution($ref, $this->binding(), 111));
        $allow = true;
        $private = $fake->beginResolution($ref, $this->binding(), 112);
        $allow = false;
        $this->denied(fn () => $fake->finishResolution($ref, $this->binding(), $private['token'], ['status' => 'absent'], 113));
        $this->assertSame('unresolved', $fake->status($ref, $this->binding(), 114)['state']);
    }

    public function test_terminal_acknowledgements_require_exact_safe_results_and_single_use_tokens(): void
    {
        foreach (['completed', 'unchanged'] as $status) {
            $fake = $this->fake();
            $ref = $this->prepare($fake);
            $private = $fake->dispatch($ref, $this->binding(), 110);
            $result = ['status' => $status];
            if ($status === 'completed') {
                $result += ['completion_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY', 'resulting_profile_version' => 2];
            }
            $this->denied(fn () => $fake->finish($ref, $this->binding(), 'forged', $result, 111));
            $this->denied(fn () => $fake->finish($ref, $this->binding(), $private['token'], $result + ['name' => 'PRIVATE'], 112));
            $this->assertSame(['state' => $status, 'resolution' => null], $fake->finish($ref, $this->binding(), $private['token'], $result, 113));
            $this->denied(fn () => $fake->finish($ref, $this->binding(), $private['token'], $result, 114));
        }
    }

    public function test_privacy_and_unbound_contract_and_no_database_use(): void
    {
        $this->assertFalse(app()->bound(BusinessNameCompletionRequestContext::class));
        $originalPdo = DB::connection()->getRawPdo();
        $this->assertNotInstanceOf(\PDO::class, $originalPdo);
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $fake->dispatch($ref, $this->binding(), 110);
        $this->assertSame(['state' => 'unresolved', 'resolution' => null], $fake->status($ref, $this->binding(), 111));
        ob_start();
        var_dump($fake);
        $dump = ob_get_clean();
        foreach (['PRIVATE', $ref, '12345678-1234', 'fictional-session'] as $private) {
            $this->assertStringNotContainsString($private, $dump);
            $this->assertStringNotContainsString($private, json_encode($fake, JSON_THROW_ON_ERROR));
        }
        try {
            serialize($fake);
            $this->fail('Expected serialization denial.');
        } catch (LogicException $error) {
            $this->assertSame('Private completion context serialization is prohibited.', $error->getMessage());
        }
        $this->assertSame($originalPdo, DB::connection()->getRawPdo());
    }

    public function test_two_prepared_contexts_cannot_dispatch_around_an_unresolved_winner(): void
    {
        $fake = $this->fake();
        $first = $this->prepare($fake);
        $different = $this->expected();
        $different['completion_request_id'] = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $second = $this->prepare($fake, $different);
        $this->assertNotSame($first, $second);
        $fake->dispatch($first, $this->binding(), 110);
        $this->denied(fn () => $fake->dispatch($second, $this->binding(), 111));
        $this->assertSame('prepared', $fake->status($second, $this->binding(), 112)['state']);
        $this->denied(fn () => $this->prepare($fake, $different));
    }

    public function test_lost_resolution_ack_requires_fresh_explicit_resolution_and_invalidates_old_tokens(): void
    {
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $fake->dispatch($ref, $this->binding(), 110);
        $first = $fake->beginResolution($ref, $this->binding(), 111);
        $second = $fake->beginResolution($ref, $this->binding(), 112);
        $this->assertSame($first['expected'], $second['expected']);
        $this->denied(fn () => $fake->finishResolution($ref, $this->binding(), $first['token'], ['status' => 'absent'], 113));
        $this->assertSame(['state' => 'unresolved', 'resolution' => 'absent'], $fake->finishResolution($ref, $this->binding(), $second['token'], ['status' => 'absent'], 114));
    }

    public function test_trusted_callback_outage_and_regressed_time_fail_closed_without_private_exception(): void
    {
        $fake = new FakeBusinessNameCompletionRequestContext(fn () => throw new RuntimeException('PRIVATE AUTHORITY FAILURE'));
        $ref = $this->prepare($fake);
        $fake->dispatch($ref, $this->binding(), 110);
        $this->denied(fn () => $fake->beginResolution($ref, $this->binding(), 111));
        $this->denied(fn () => $fake->status($ref, $this->binding(), 109));
        $this->assertSame('unresolved', $fake->status($ref, $this->binding(), 112)['state']);
    }

    public function test_malformed_preparation_and_fabricated_outcomes_do_not_create_success(): void
    {
        foreach ([[-1, 200, 400], [100, 100, 400], [100, 200, 199], [100, 200, 253402300800000]] as [$start, $expiry, $retention]) {
            $this->denied(fn () => $this->fake()->prepare($this->binding(), $this->expected(), 'Kijani', $start, $expiry, $retention));
        }
        $fake = $this->fake();
        $ref = $this->prepare($fake);
        $this->denied(fn () => $fake->finish($ref, $this->binding(), 'forged', ['status' => 'unchanged'], 101));
        $private = $fake->dispatch($ref, $this->binding(), 110);
        $this->denied(fn () => $fake->finish($ref, $this->binding(), $private['token'], ['status' => 'completed', 'completion_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY', 'resulting_profile_version' => 3], 111));
        $this->assertSame('unresolved', $fake->status($ref, $this->binding(), 112)['state']);
    }
}
