<?php

namespace App\CentralLogics;

/**
 * Proxy for the AI module's PersonalizationService.
 * Delegates all calls to Modules\AI when the module is active.
 * Returns unchanged queries/data when the module is disabled.
 */
class PersonalizationService
{
    private static function service(): ?string
    {
        if (!class_exists(\Modules\AI\app\Services\Personalization\PersonalizationService::class)) return null;
        if (!\Modules\AI\app\Core\AiModule::isPersonalizationActive()) return null;
        return \Modules\AI\app\Services\Personalization\PersonalizationService::class;
    }

    // --- Recording methods (write to preferences table) ---

    public static function recordItemAction(int $userId, int $itemId, string $signal): void
    {
        $svc = self::service();
        if (!$svc) return;

        try {
            $svc::recordItemAction($userId, $itemId, $signal);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function recordStoreAction(int $userId, int $storeId, string $signal): void
    {
        $svc = self::service();
        if (!$svc) return;

        try {
            $svc::recordStoreAction($userId, $storeId, $signal);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function recordSearchAction(int $userId, string $keyword, ?int $moduleId): void
    {
        $svc = self::service();
        if (!$svc) return;

        try {
            $svc::recordSearchAction($userId, $keyword, $moduleId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function recordServiceAction(int $userId, int $serviceId, string $signal): void
    {
        $svc = self::service();
        if (!$svc) return;

        try {
            $svc::recordServiceAction($userId, $serviceId, $signal);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function recordVehicleAction(int $userId, int $vehicleId, string $signal, ?int $moduleId): void
    {
        $svc = self::service();
        if (!$svc) return;

        try {
            $svc::recordVehicleAction($userId, $vehicleId, $signal, $moduleId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // --- Apply methods (modify queries) ---

    public static function applyItemPersonalization($query, ?int $userId, $filter = null)
    {
        $svc = self::service();
        return $svc ? $svc::applyItemPersonalization($query, $userId, $filter) : $query;
    }

    public static function applyStorePersonalization($query, ?int $userId, $filter = null)
    {
        $svc = self::service();
        return $svc ? $svc::applyStorePersonalization($query, $userId, $filter) : $query;
    }

    public static function applyCategoryPersonalization($query, ?int $userId)
    {
        $svc = self::service();
        return $svc ? $svc::applyCategoryPersonalization($query, $userId) : $query;
    }

    public static function applyCampaignPersonalization($query, ?int $userId)
    {
        $svc = self::service();
        return $svc ? $svc::applyCampaignPersonalization($query, $userId) : $query;
    }

    public static function reorderByPreference($collection, ?int $userId, string $matchField, string $preferenceType)
    {
        $svc = self::service();
        return $svc ? $svc::reorderByPreference($collection, $userId, $matchField, $preferenceType) : $collection;
    }

    // --- Rebuild methods (called from job/command) ---

    public static function rebuildSummary(int $userId, ?int $moduleId): void
    {
        $svc = self::service();
        if ($svc) $svc::rebuildSummary($userId, $moduleId);
    }

    public const REBUILD_THRESHOLD = 5;
}
