<?php

namespace App\Actions\Merchants;

interface TrustedReleaseApproval
{
    /**
     * Independent approval/revocation authority, outside source and workflow-store custody.
     * Bind the entire immutable release, exact source/manifest maps, profile, environment,
     * store/generation/fence, worker and approval provenance. Outage MUST deny or throw.
     * Observed local hashes or descriptor assertions alone never establish authenticity.
     * No signing service, config adapter or operational implementation is supplied here.
     */
    public function approves(#[\SensitiveParameter] array $descriptor): bool;
}
