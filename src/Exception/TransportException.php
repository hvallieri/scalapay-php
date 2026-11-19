<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * A transport-level failure: unreachable network, unbuildable request, undecodable
 * response. Distinct from ApiException, which represents an application error reported
 * by Scalapay.
 */
class TransportException extends ScalapayException
{
}
