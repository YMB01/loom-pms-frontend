<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $stripe): Response
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $stripe->processWebhook($payload, $sig);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            report($e);

            return response('Webhook error', 500);
        }

        return response('OK', 200);
    }
}
