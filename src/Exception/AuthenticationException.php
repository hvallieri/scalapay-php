<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * HTTP 401. The body is not a structured JSON object but the bare string
 * `"Unauthorized"`, so `errorCode` and `errorId` are normally absent.
 *
 * A frequent cause: the key of one environment used against the other's base URI.
 */
class AuthenticationException extends ApiException
{
}
