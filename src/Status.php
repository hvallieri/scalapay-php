<?php declare(strict_types=1);

namespace Hval\Scalapay;

/**
 * The three distinct status axes of the API, gathered in one place because they are
 * easily mistaken for three spellings of the same thing. They must never be unified nor
 * compared with one another.
 *
 * None of them is an enum on purpose:
 *
 * - for the order status the documentation explicitly warns the list may change and
 *   advises against hard-coding the expected set;
 * - for the operation outcome no list is documented at all;
 * - the capture status was believed to have two values until two more turned up in the
 *   Reporting API examples, which proves the observed set is not the real one.
 *
 * A closed enum would throw, or force a null branch, exactly where Scalapay warned the
 * values would change. These constants are conveniences, not an exhaustive domain.
 */
final class Status
{
    /**
     * Order status: `GET /v2/payments/{token}` and webhooks. Lower-case.
     */
    public const ORDER_CREATED = 'created';
    public const ORDER_AUTHORIZED = 'authorized';
    public const ORDER_CHARGED = 'charged';
    public const ORDER_REFUNDED = 'refunded';
    public const ORDER_EXPIRED = 'expired';

    /**
     * Operation outcome: the `status` returned by `/delay` and `/capture`. Upper-case.
     */
    public const OPERATION_AUTHORIZED = 'AUTHORIZED';
    public const OPERATION_APPROVED = 'APPROVED';

    /**
     * Capture status: `captureStatus` in `GET /v2/payments/{token}` and in the Reporting
     * API. Lower-case. `delayed` and `voided` are only attested by Reporting examples.
     */
    public const CAPTURE_CAPTURED = 'captured';
    public const CAPTURE_PENDING = 'pending';
    public const CAPTURE_DELAYED = 'delayed';
    public const CAPTURE_VOIDED = 'voided';

    /**
     * @return list<string>
     */
    public static function knownOrderStatuses(): array
    {
        return [
            self::ORDER_CREATED,
            self::ORDER_AUTHORIZED,
            self::ORDER_CHARGED,
            self::ORDER_REFUNDED,
            self::ORDER_EXPIRED,
        ];
    }

    /**
     * No list is documented for this axis: these are the two values seen in the
     * specification examples.
     *
     * @return list<string>
     */
    public static function knownOperationOutcomes(): array
    {
        return [
            self::OPERATION_AUTHORIZED,
            self::OPERATION_APPROVED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function knownCaptureStatuses(): array
    {
        return [
            self::CAPTURE_CAPTURED,
            self::CAPTURE_PENDING,
            self::CAPTURE_DELAYED,
            self::CAPTURE_VOIDED,
        ];
    }
}
