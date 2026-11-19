<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * HTTP 422, `errorCode` `invalid_token`. Documented only on `GET /v2/payments/{token}`,
 * the one endpoint that does not use the 400/401/409 family.
 */
class UnprocessableEntityException extends ApiException
{
}
