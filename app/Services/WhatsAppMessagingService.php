<?php

namespace App\Services;

use App\Models\WhatsappLog;

/**
 * Records outbound WhatsApp traffic and (later) dispatches to Meta WhatsApp Cloud API / BSP.
 * SMS remains separate — see SendSmsJob and SmsLog.
 */
class WhatsAppMessagingService
{
    /**
     * Persist a WhatsApp message attempt for auditing (same shape as SMS logs).
     * Call this from jobs/controllers when integrating Meta BSP or Twilio WhatsApp.
     */
    public static function recordOutbound(
        int $companyId,
        string $toNumber,
        string $message,
        ?string $trigger = null,
        string $status = 'logged',
        ?string $provider = 'meta_cloud_api',
    ): WhatsappLog {
        return WhatsappLog::query()->create([
            'company_id' => $companyId,
            'to_number' => $toNumber,
            'message' => $message,
            'trigger' => $trigger,
            'provider' => $provider,
            'status' => $status,
            'cost' => null,
        ]);
    }
}
