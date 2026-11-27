<?php

namespace App\Staging;

use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Payments\CheckoutQr;
use App\Actions\Payments\PaymentOutcomeUnknown;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StagingFinancialController
{
    public function handle(Request $request): Response
    {
        $c = StagingFinancialAuthority::current();
        $service = new StagingFinancialServices;
        try {
            return $service->run(function (StagingFinancialServices $s) use ($request, $c): Response {
                $actor = $request->user();
                $merchant = $c['merchant'];
                $path = explode('/', $request->path());
                $resource = $path[4] ?? null;
                $prefix = '/merchants/'.$merchant.'/payment-products';
                switch ($c['action']) {
                    case 'r24.products.read':
                        $data = $s->links->history($actor, $merchant);
                        $key = session('r24.link_request') ?? bin2hex(random_bytes(16));
                        session(['r24.link_request' => $key]);

                        return $request->expectsJson() ? response()->json($data) : response()->view('merchants.payment-links', [...$data, 'merchantId' => $merchant, 'requestKey' => $key, 'paymentRoutePrefix' => 'r24']);
                    case 'r24.links.create':
                        $this->fields($request, ['amount_tzs', 'currency', 'description', 'request_key', 'expires_at']);
                        $request->validate(['currency' => ['required', 'string', 'in:TZS'], 'description' => ['required', 'string', 'max:200']]);
                        abort_unless(is_string($request->input('request_key')) && hash_equals((string) session('r24.link_request'), $request->input('request_key')), 409);
                        $result = $s->links->create($actor, $merchant, $this->integer($request->input('amount_tzs')), $request->input('currency'), $request->input('description'), $request->input('request_key'), $request->filled('expires_at') ? $this->integer($request->input('expires_at')) : null);
                        session()->forget('r24.link_request');

                        return $request->expectsJson() ? response()->json($result) : redirect($prefix, 303);
                    case 'r24.links.disable':
                        $this->fields($request, []);

                        return response()->json($s->links->disable($actor, $merchant, $resource));
                    case 'r24.payment.create':
                        $this->fields($request, ['amount_tzs', 'currency']);
                        $request->validate(['currency' => ['required', 'string', 'in:TZS']]);

                        return response()->json($s->payments->create($actor, $merchant, $this->integer($request->input('amount_tzs')), $request->input('currency'), $c['command']));
                    case 'r24.payment.read':
                        return response()->json($s->payments->read($actor, $merchant, $resource));
                    case 'r24.payment.query':
                        $this->fields($request, []);

                        return response()->json($s->payments->query($actor, $merchant, $resource));
                    case 'r24.mock.event':
                        $this->fields($request, []);
                        $event = StagingIdentity::read(app('staging.context')['root'].'/mock-event-'.$c['command'].'.json');
                        abort_unless(hash_equals($c['event_hash'], hash('sha256', $event['body'])) && json_decode($event['body'], true)['reference'] === $resource, 409);
                        abort_unless(($s->store->rows('SELECT merchant_id FROM payment_intents WHERE reference=?', [$resource])[0]['merchant_id'] ?? null) === $merchant, 404);

                        return response()->json(app(SubmissionTransaction::class)->run(fn (): array => $s->payments->receive($event['body'], $event['signature'])));
                    case 'r24.ledger.post':
                    case 'r24.ledger.reconcile':
                        $this->fields($request, []);

                        return response()->json($s->links->consume($actor, $merchant, $resource, $c['action'] === 'r24.ledger.reconcile'));
                    case 'r24.widgets.create':
                        $this->fields($request, ['kind', 'mode']);
                        $request->validate(['kind' => ['required', 'in:qr,button'], 'mode' => ['required', 'in:dynamic,reusable']]);

                        return response()->json($s->widgets->widget($actor, $merchant, $resource, $request->input('kind'), $request->input('mode')));
                    case 'r24.widgets.read':
                        $link = $s->widgets->resolveWidget($resource);
                        $url = $s->widgets->checkoutUrl($link['public_token']);

                        return response()->view('merchants.staging-payment-widget', ['merchantId' => $merchant, 'link' => $link, 'url' => $url, 'qr' => CheckoutQr::svg($url), 'button' => $s->widgets->button($resource)]);
                    case 'r24.transactions.read':
                        $data = ['overview' => $s->wallet->overview($actor, $merchant), ...$s->wallet->history($actor, $merchant, $request->query())];

                        return $request->expectsJson() ? response()->json($data) : response()->view('merchants.staging-transactions', ['merchantId' => $merchant, ...$data]);
                    case 'r24.checkout.read':
                    case 'r24.checkout.initiate':
                        $token = $path[1];
                        if ($request->isMethod('POST')) {
                            $this->fields($request, ['request_nonce']);
                            $request->validate(['request_nonce' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
                            $result = $s->links->initiate($token, $request->input('request_nonce'));
                        } elseif ($request->filled('attempt')) {
                            abort_unless(array_diff(array_keys($request->query()), ['attempt']) === [], 422);
                            $result = $s->links->customerStatus($token, $request->query('attempt'));
                        } else {
                            abort_unless($request->query() === [], 422);
                            $link = $s->links->open($token);
                            $nonce = session('checkout.nonces.'.$token) ?? bin2hex(random_bytes(32));
                            session(['checkout.nonces.'.$token => $nonce]);

                            return response()->view('payments.checkout', ['link' => $link, 'nonce' => $nonce, 'result' => null, 'paymentRoutePrefix' => 'r24', 'checkoutStatusRoute' => 'r24.checkout.open']);
                        }

                        return $request->expectsJson() ? response()->json($result) : response()->view('payments.checkout', ['link' => $result['link'], 'nonce' => null, 'result' => $result, 'paymentRoutePrefix' => 'r24', 'checkoutStatusRoute' => 'r24.checkout.open']);
                }
                abort(403);
            });
        } catch (PaymentOutcomeUnknown) {
            return response()->json(['status' => 'unresolved', 'message' => 'Retain this command. No automatic retry. Use separately admitted status/query or accounting reconciliation.'], 202);
        }
    }

    private function fields(Request $request, array $allowed): void
    {
        abort_unless($request->query() === [] && array_diff(array_keys($request->except('_token')), $allowed) === [], 422);
    }

    private function integer(mixed $value): int
    {
        abort_unless(is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]{0,14}$/D', $value)), 422);

        return (int) $value;
    }
}
