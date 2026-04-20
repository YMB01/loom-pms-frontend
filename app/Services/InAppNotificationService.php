<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\UserRole;
use App\Models\InAppNotification;
use App\Models\User;

class InAppNotificationService
{
    public function notifyManagers(
        int $companyId,
        string $title,
        string $body,
        InAppNotificationType $type = InAppNotificationType::Info,
        array $data = []
    ): void {
        $userIds = User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', [UserRole::CompanyAdmin, UserRole::PropertyManager])
            ->pluck('id');

        foreach ($userIds as $uid) {
            InAppNotification::query()->create([
                'company_id' => $companyId,
                'user_id' => $uid,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'is_read' => false,
                'data' => $data ?: null,
            ]);
        }
    }

    public function notifyUser(
        int $companyId,
        int $userId,
        string $title,
        string $body,
        InAppNotificationType $type = InAppNotificationType::Info,
        array $data = []
    ): void {
        InAppNotification::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'is_read' => false,
            'data' => $data ?: null,
        ]);
    }

    public function broadcastToCompany(
        int $companyId,
        string $title,
        string $body,
        InAppNotificationType $type = InAppNotificationType::Info,
        array $data = []
    ): void {
        $this->notifyManagers($companyId, $title, $body, $type, $data);
    }
}
