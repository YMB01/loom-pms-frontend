<?php

namespace App\Jobs;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\SmsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Twilio\Rest\Client as TwilioClient;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public string $to,
        public string $message,
        public string $trigger,
        public int $companyId
    ) {}

    public function handle(): void
    {
        $to = $this->normalizePhone($this->to);

        if ($this->canUseAfricaTalking()) {
            try {
                $this->sendViaAfricasTalking($to);

                return;
            } catch (Throwable $e) {
                Log::warning('SMS: Africa\'s Talking failed', [
                    'to' => $to,
                    'trigger' => $this->trigger,
                    'error' => $e->getMessage(),
                ]);
                $this->logAttempt($to, 'africastalking', 'failed');
            }
        }

        if ($this->canUseTwilio()) {
            try {
                $this->sendViaTwilio($to);

                return;
            } catch (Throwable $e) {
                Log::warning('SMS: Twilio failed', [
                    'to' => $to,
                    'trigger' => $this->trigger,
                    'error' => $e->getMessage(),
                ]);
                $this->logAttempt($to, 'twilio', 'failed');
            }
        }

        if (! $this->canUseAfricaTalking() && ! $this->canUseTwilio()) {
            $this->logAttempt($to, 'none', 'failed');
            Log::error('SMS: no provider configured', ['trigger' => $this->trigger, 'company_id' => $this->companyId]);
        }
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? $phone;

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $phone = ltrim($phone, '0');
        $code = (string) config('services.sms.default_country_code', '251');

        return '+'.$code.$phone;
    }

    protected function canUseAfricaTalking(): bool
    {
        return filled(config('services.africastalking.username'))
            && filled(config('services.africastalking.api_key'));
    }

    protected function canUseTwilio(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.from'));
    }

    protected function sendViaAfricasTalking(string $to): void
    {
        $username = config('services.africastalking.username');
        $apiKey = config('services.africastalking.api_key');

        $at = new AfricasTalking($username, $apiKey);
        $sms = $at->sms();

        $options = [
            'to' => [$to],
            'message' => $this->message,
        ];

        if (filled(config('services.africastalking.from'))) {
            $options['from'] = config('services.africastalking.from');
        }

        $result = $sms->send($options);

        if (($result['status'] ?? '') === 'error') {
            throw new RuntimeException(json_encode($result['data'] ?? 'Unknown Africa\'s Talking error'));
        }

        $data = $result['data'] ?? null;
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        $recipient = is_array($data) ? ($data['SMSMessageData']['Recipients'][0] ?? null) : null;
        if (is_array($recipient) && ($recipient['status'] ?? '') !== 'Success') {
            throw new RuntimeException($recipient['status'] ?? 'Africa\'s Talking recipient failed');
        }

        $this->logAttempt($to, 'africastalking', 'sent');
    }

    protected function sendViaTwilio(string $to): void
    {
        $client = new TwilioClient(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $message = $client->messages->create($to, [
            'from' => config('services.twilio.from'),
            'body' => $this->message,
        ]);

        $cost = null;
        if ($message->price !== null) {
            $cost = (float) abs((float) $message->price);
        }

        $this->logAttempt($to, 'twilio', 'sent', $cost);
    }

    protected function logAttempt(string $to, string $provider, string $status, ?float $cost = null): void
    {
        SmsLog::query()->create([
            'company_id' => $this->companyId,
            'to_number' => $to,
            'message' => $this->message,
            'trigger' => $this->trigger,
            'provider' => $provider,
            'status' => $status,
            'cost' => $cost,
        ]);
    }
}
