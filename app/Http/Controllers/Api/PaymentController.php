<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsPaginatedResponses;
use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payment\IndexPaymentRequest;
use App\Http\Requests\Api\Payment\StorePaymentRequest;
use App\Http\Requests\Api\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InAppNotificationService;
use App\Services\SMSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use FormatsPaginatedResponses;
    use InteractsWithCompany;

    public function index(IndexPaymentRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $query = Payment::query()
            ->forCompany($companyId)
            ->with(['invoice.lease.unit.property', 'tenant'])
            ->orderByDesc('created_at');

        if ($request->filled('property_id')) {
            $propertyId = (int) $request->validated('property_id');
            $query->whereHas('invoice.lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }

        if ($request->filled('search')) {
            $raw = trim((string) $request->validated('search'));
            $term = '%'.addcslashes($raw, '%_\\').'%';
            $query->where(function ($q) use ($term, $raw): void {
                $q->whereHas('tenant', fn ($t) => $t->where('name', 'like', $term))
                    ->orWhere('method', 'like', $term)
                    ->orWhere('reference', 'like', $term);
                if (ctype_digit($raw)) {
                    $q->orWhere('id', (int) $raw);
                }
            });
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $this->paginatedPayload($paginator, PaymentResource::class),
            ''
        );
    }

    public function store(StorePaymentRequest $request, SMSService $sms, InAppNotificationService $notify): JsonResponse
    {
        $companyId = $this->companyId();

        $paymentModel = DB::transaction(function () use ($request, $companyId, $sms, $notify) {
            $payment = Payment::query()->create($request->validated());

            $invoice = Invoice::query()
                ->forCompany($companyId)
                ->whereKey($payment->invoice_id)
                ->firstOrFail();

            $invoice->syncStatusFromPayments();

            $payment->load(['invoice', 'tenant']);
            if ($payment->tenant) {
                $sms->sendPaymentReceived($payment->tenant, $payment);
            }

            $notify->notifyManagers(
                $companyId,
                'Payment received',
                'Payment of '.$payment->amount.' recorded for invoice #'.$invoice->id.'.',
            );

            return $payment;
        });

        return ApiResponse::success(
            [
                'payment' => PaymentResource::make($paymentModel)->resolve(),
                'sms_queued' => (bool) ($paymentModel->tenant && filled($paymentModel->tenant->phone)),
            ],
            'Payment recorded.',
            201
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['invoice', 'tenant']);

        return ApiResponse::success(
            ['payment' => PaymentResource::make($payment)->resolve()],
            ''
        );
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $companyId = $this->companyId();

        DB::transaction(function () use ($request, $payment, $companyId): void {
            $previousInvoiceId = $payment->invoice_id;

            $payment->update($request->validated());
            $payment->refresh();

            $invoiceIds = array_unique([$previousInvoiceId, $payment->invoice_id]);

            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::query()
                    ->forCompany($companyId)
                    ->whereKey($invoiceId)
                    ->first();

                $invoice?->syncStatusFromPayments();
            }
        });

        $payment->load(['invoice', 'tenant']);

        return ApiResponse::success(
            ['payment' => PaymentResource::make($payment->fresh())->resolve()],
            'Payment updated.'
        );
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $companyId = $this->companyId();

        DB::transaction(function () use ($payment, $companyId): void {
            $invoice = Invoice::query()
                ->forCompany($companyId)
                ->whereKey($payment->invoice_id)
                ->firstOrFail();

            $payment->delete();
            $invoice->syncStatusFromPayments();
        });

        return ApiResponse::success([], 'Payment deleted.');
    }
}
