<?php

namespace App\Support;

/**
 * Mother→child inter-shop transfer workflow (orders.order_status + purchases.purchase_status).
 * Regular POS orders keep legacy values: order_status "complete" / "pending".
 */
final class InterShopTransferStatus
{
    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const COMPLETED = 'COMPLETED';

    public const CANCELLED = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function workflowValues(): array
    {
        return [self::PENDING, self::APPROVED, self::COMPLETED, self::CANCELLED];
    }

    public static function isWorkflowValue(?string $status): bool
    {
        return $status !== null && in_array($status, self::workflowValues(), true);
    }

    /**
     * Human-readable status for UI. Only workflow {@see self::PENDING} is remapped.
     * Legacy lowercase "pending" must stay as stored (case-sensitive).
     */
    public static function uiPurchaseStatusLabel(?string $status): string
    {
        if ($status === self::PENDING) {
            return 'Pending For Approval';
        }

        return (string) ($status ?? '');
    }
}
