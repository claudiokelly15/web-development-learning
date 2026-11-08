<?php

namespace App\Actions\Merchants;

use Illuminate\Support\Facades\DB;
use Throwable;

final class VerificationReadiness
{
    public function __construct(private readonly VerificationEnvironment $environment) {}

    public function ready(): bool
    {
        try {
            if (! $this->environment->authorized() || ! in_array($this->environment->mode(), ['live', 'simulated'], true)
                || ($this->environment->mode() === 'simulated' && ! app()->environment('testing'))
                || DB::getDriverName() !== 'sqlite' || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
                return false;
            }
            $keyDigests = [];
            foreach (['phone', 'nin', 'otp', 'contact'] as $domain) {
                $id = $this->environment->keyId($domain);
                if (! preg_match('/^[a-z0-9._-]{1,64}$/D', $id) || strlen($this->environment->key($domain, $id)) !== 32) {
                    return false;
                }
                $keyDigests[] = hash('sha256', $this->environment->key($domain, $id));
            }
            if (count(array_unique($keyDigests)) !== 4) {
                return false;
            }
            $now = $this->environment->milliseconds();
            if ($now < 0 || $now > 253402300799999 || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $this->environment->namespace())) {
                return false;
            }
            $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
            foreach ($contract['create_tables'] + $contract['indexes'] + $contract['triggers'] as $name => $sql) {
                if (DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name])?->sql !== $sql) {
                    return false;
                }
            }
            if (DB::selectOne("SELECT sql FROM sqlite_master WHERE name='audit_logs' AND type='table'")?->sql !== $contract['audit']['sql']) {
                return false;
            }

            return DB::table('migrations')->where('migration', '2026_11_07_200000_activate_b32_security_contract')->count() === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
