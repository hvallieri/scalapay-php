<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * HTTP 409, `errorCode` `conflicting_operation_in_progress`. The only error the
 * documentation declares retryable: another operation is in progress on the same order.
 * Typical between capture and void, which compete for the same authorization.
 */
class ConflictException extends ApiException
{
}
