<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Private merchant verification | XLipa</title><link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}"><link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}"><link rel="stylesheet" href="{{ asset('assets/xlipa-dashboard.css') }}"><link rel="stylesheet" href="{{ asset('assets/xlipa-verification.css') }}"></head>
<body class="merchant-dashboard verification-workspace">
<a class="dash-skip" href="#verification-main">Skip to verification</a>
<header class="verification-header"><a class="dash-brand" href="{{ route('merchants.dashboard',['merchant'=>$merchant->id]) }}"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="36" height="36"><span>XLipa.</span></a><a href="{{ route('merchants.dashboard',['merchant'=>$merchant->id]) }}">Back to dashboard</a></header>
<main id="verification-main" class="verification-main">
<div class="dash-section-heading"><div><p class="dash-eyebrow">PRIVATE ONBOARDING</p><h1>Merchant verification.</h1><p>{{ $merchant->businessNameLabel() }}</p></div><span class="dash-badge">TZS only</span></div>
@if(session('status'))<p class="verification-notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="verification-notice" role="alert">Check the required fields and your password. Sensitive values have not been retained.</p>@endif
@if(!$verification['available'])<section class="verification-notice" role="status"><h2>Capture and delivery are unavailable</h2><p>Operational authorization, protected keys, approved policies and database capabilities must be ready before private information can be entered. Do not enter identity information here yet.</p></section>
@elseif(!$verification['canChange'])<section class="verification-notice" role="status"><h2>Verification changes are on hold</h2><p>Changes require an active merchant, a current draft application and no pending audit obligation. Your saved status is shown below.</p></section>@endif
<div class="verification-grid">
<section class="dash-panel" aria-labelledby="contact-heading"><p class="dash-eyebrow">EXACT APPLICATION CONTACT</p><h2 id="contact-heading">Phone verification</h2>
<dl class="dash-status-list"><div><dt>Private contact</dt><dd>{{ $verification['contact'] }}</dd></div><div><dt>Possession proof</dt><dd>{{ $verification['phoneProof'] }}</dd></div><div><dt>Account email</dt><dd>Verified for this signed-in account</dd></div></dl>
<p class="dash-panel-note">Phone possession does not verify legal identity, approve a merchant or enable payments. Changing the contact invalidates its prior proofs. Codes expire after five minutes; resends and failed attempts are limited.</p>
<form method="post" action="{{ route('merchants.verification.contact',['merchant'=>$merchant->id]) }}" autocomplete="off">@csrf
<fieldset @disabled(!$verification['canChange'])><legend>Record or replace your application phone</legend><input type="hidden" name="context_version" value="{{ $verification['contextVersion'] }}">
<label for="phone">Tanzania mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" maxlength="20" autocomplete="off" required aria-describedby="phone-help"><p id="phone-help">Use your exact application contact. Saved numbers are never filled back into this form.</p>
<label for="contact-password">Confirm your current password</label><input id="contact-password" name="password" type="password" autocomplete="current-password" required>
<button class="dash-button" type="submit">Save private contact</button></fieldset></form>
<form method="post" action="{{ route('merchants.verification.request',['merchant'=>$merchant->id]) }}">@csrf<input type="hidden" name="request_id" value="{{ $requestId }}"><button class="dash-button dash-button-outline" type="submit" @disabled(!$verification['canChange'] || $verification['contact']==='Not recorded')>Request a verification code</button></form>
<form method="post" action="{{ route('merchants.verification.verify',['merchant'=>$merchant->id]) }}" autocomplete="off">@csrf
<fieldset @disabled(!$verification['canChange'] || !$verification['challenge'])><legend>Submit your code</legend><input type="hidden" name="challenge" value="{{ $verification['challenge'] }}"><label for="code">Six-digit verification code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button class="dash-button" type="submit">Check code</button></fieldset></form>
</section>
<section class="dash-panel" aria-labelledby="identity-heading"><p class="dash-eyebrow">WRITE-ONLY DECLARATION</p><h2 id="identity-heading">Private identity</h2><p>{{ $verification['identity'] }}</p>
<p class="dash-panel-note">A declaration and syntax check are separate from independent identity verification. This page never displays a saved identity number. Optional documents and business registration fields are not required by this form.</p>
@if(!$verification['identityAvailable'])<p class="verification-notice">Identity entry is unavailable until the NIN syntax, declaration, privacy notice and processing policy are approved.</p>@endif
<form method="post" action="{{ route('merchants.verification.identity',['merchant'=>$merchant->id]) }}" autocomplete="off">@csrf
<fieldset @disabled(!$verification['canChange'] || !$verification['identityAvailable'])><legend>Record your declaration</legend><input type="hidden" name="context_version" value="{{ $verification['contextVersion'] }}">
<label for="nin">NIDA identification number</label><input id="nin" name="nin" type="password" maxlength="256" autocomplete="off" required>
<label for="subject_claim">Declaration capacity</label><select id="subject_claim" name="subject_claim" required><option value="">Choose your capacity</option><option value="individual_owner">Individual owner</option><option value="authorized_representative">Authorized representative</option></select>
@if($verification['policyText'])<p>{{ $verification['policyText'] }}</p>@endif
<label class="verification-check"><input name="declaration" type="checkbox" value="1" required><span>I confirm the approved declaration and privacy notice shown above.</span></label>
<label for="identity-password">Confirm your current password</label><input id="identity-password" name="password" type="password" autocomplete="current-password" required>
<button class="dash-button" type="submit">Save private declaration</button></fieldset></form>
</section></div>
<footer class="dash-footer"><span>XLipa · Private merchant onboarding</span><span>Submission, approval and financial operations remain unavailable.</span></footer>
</main></body></html>
