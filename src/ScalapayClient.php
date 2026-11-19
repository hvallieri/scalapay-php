<?php declare(strict_types=1);

namespace Hval\Scalapay;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Http\Transport;
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\OrderRequest;
use Hval\Scalapay\Response\MerchantConfiguration;
use Hval\Scalapay\Response\OperationResult;
use Hval\Scalapay\Response\OrderCreated;
use Hval\Scalapay\Response\OrderDetails;
use Hval\Scalapay\Response\OrderReference;
use Hval\Scalapay\Response\OrderUpdated;
use Hval\Scalapay\Response\RefundResult;
use Hval\Scalapay\Response\VoidResult;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point for the Scalapay Order and Refund APIs.
 *
 * Every write operation accepts an optional idempotency key: the API caches the result
 * and replays it, which the returned resource reports through isIdempotentReplay().
 */
final class ScalapayClient
{
    /**
     * Default authorization validity for a delayed capture: 432000000 ms, exactly five
     * days. It is the value the specification documents as the default for a field it
     * nevertheless marks required.
     */
    public const DEFAULT_AUTHORIZATION_EXPIRY_MS = 432000000;

    /**
     * Maximum number of results `GET /v2/payments/references` returns. There is no paging
     * parameter and no truncation flag: beyond this the extra matches are silently lost.
     */
    public const REFERENCES_RESULT_LIMIT = 100;

    private readonly Transport $transport;

    /**
     * @param Config $config
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface&StreamFactoryInterface $httpFactory a PSR-17 factory providing both
     */
    public function __construct(
        private readonly Config $config,
        ClientInterface $httpClient,
        RequestFactoryInterface&StreamFactoryInterface $httpFactory,
    ) {
        $this->transport = new Transport($this->config, $httpClient, $httpFactory);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * `POST /v2/orders` — creates an order and returns the token plus the checkout URI to
     * redirect the customer to.
     */
    public function createOrder(OrderRequest $order, ?string $idempotencyKey = null): OrderCreated
    {
        return OrderCreated::fromResponse(
            $this->transport->post('/v2/orders', $order->toArray(), $idempotencyKey)
        );
    }

    /**
     * `POST /v2/orders/{token}` — replaces the merchant references of an existing order.
     *
     * This endpoint has replace semantics, not merge semantics: the documentation states
     * "Omit the field to remove the value". Both references are therefore required
     * parameters without defaults, so that clearing one is always a deliberate act — pass
     * null explicitly to clear, and re-send the current value to keep it.
     *
     * The response cannot tell "cleared" from "unchanged", so do not infer state from it.
     */
    public function updateOrderReferences(
        string $token,
        ?string $merchantReference,
        ?string $merchantProcessorReference,
        ?string $idempotencyKey = null,
    ): OrderUpdated {
        $body = [];

        if ($merchantReference !== null) {
            $body['merchantReference'] = $merchantReference;
        }

        if ($merchantProcessorReference !== null) {
            $body['merchantProcessorReference'] = $merchantProcessorReference;
        }

        return OrderUpdated::fromResponse(
            $this->transport->post('/v2/orders/' . rawurlencode($token), $body, $idempotencyKey)
        );
    }

    /**
     * `GET /v2/payments/{token}` — returns the current state of an order.
     *
     * The documentation strongly recommends calling this between the customer's return
     * and the capture: only authorized orders can be captured.
     */
    public function getOrderDetails(string $token): OrderDetails
    {
        return OrderDetails::fromResponse(
            $this->transport->get('/v2/payments/' . rawurlencode($token))
        );
    }

    /**
     * `GET /v2/payments/references` — searches orders by known reference values.
     *
     * At least one criterion is required; several are combined with AND. That constraint
     * lives only in the prose of the specification, not in the schema, so it is enforced
     * here to fail before the request rather than after a 400.
     *
     * At most REFERENCES_RESULT_LIMIT entries are returned, newest first, with no way to
     * page or to detect truncation: narrow the search instead.
     *
     * @return list<OrderReference>
     */
    public function getOrdersByReference(
        ?string $orderToken = null,
        ?string $merchantOrderReference = null,
        ?string $merchantProcessorReference = null,
    ): array {
        $query = array_filter([
            'orderToken' => $orderToken,
            'merchantOrderReference' => $merchantOrderReference,
            'merchantProcessorReference' => $merchantProcessorReference,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        if ($query === []) {
            throw new ScalapayException('Searching orders by reference requires at least one criterion.');
        }

        return OrderReference::listFromResponse($this->transport->get('/v2/payments/references', $query));
    }

    /**
     * `GET /v3/configurations` — lists the type/product combinations enabled on the account,
     * with their frequency and amount limits.
     *
     * Undocumented: absent from the official API reference, but called by the official SDK
     * and answered by the sandbox. See MerchantConfiguration.
     */
    public function getMerchantConfiguration(): MerchantConfiguration
    {
        return MerchantConfiguration::fromResponse($this->transport->get('/v3/configurations'));
    }

    /**
     * `POST /v2/payments/{token}/delay` — announces a delayed capture.
     *
     * The instalment plan is created but nothing is settled until a capture call. An
     * authorization left uncaptured is voided automatically once
     * `authorizationExpiryMilliseconds` elapses.
     */
    public function delayCapture(
        string $token,
        int $authorizationExpiryMilliseconds = self::DEFAULT_AUTHORIZATION_EXPIRY_MS,
        ?string $merchantReference = null,
        ?string $idempotencyKey = null,
    ): OperationResult {
        $body = ['authorizationExpiryMilliseconds' => $authorizationExpiryMilliseconds];

        if ($merchantReference !== null) {
            $body['merchantReference'] = $merchantReference;
        }

        return OperationResult::fromResponse(
            $this->transport->post('/v2/payments/' . rawurlencode($token) . '/delay', $body, $idempotencyKey)
        );
    }

    /**
     * `POST /v2/payments/capture` — captures the payment and charges the first instalment.
     *
     * The only payment operation carrying the token in the body rather than in the path.
     *
     * Passing an amount captures partially, up to the order total; omitting it captures
     * the full amount. What happens to the remainder of a partial capture is not
     * documented. Passing a merchant reference updates it on the order, which the
     * documentation describes as an intended feature of this endpoint.
     */
    public function capture(
        string $token,
        ?Amount $amount = null,
        ?string $merchantReference = null,
        ?string $idempotencyKey = null,
    ): OperationResult {
        $body = ['token' => $token];

        if ($merchantReference !== null) {
            $body['merchantReference'] = $merchantReference;
        }

        if ($amount !== null) {
            $body['amount'] = $amount->toArray();
        }

        return OperationResult::fromResponse(
            $this->transport->post('/v2/payments/capture', $body, $idempotencyKey)
        );
    }

    /**
     * `POST /v2/payments/{token}/void` — releases the authorization and cancels the order.
     *
     * The customer is told the order could not be fulfilled. The body is optional, which
     * is why the merchant reference defaults to null here. Unlike capture, the reference
     * is not documented as updating the order, so do not rely on that.
     *
     * No webhook is documented for a void.
     */
    public function voidOrder(string $token, ?string $merchantReference = null, ?string $idempotencyKey = null): VoidResult
    {
        $body = $merchantReference === null ? null : ['merchantReference' => $merchantReference];

        return VoidResult::fromResponse(
            $this->transport->post('/v2/payments/' . rawurlencode($token) . '/void', $body, $idempotencyKey)
        );
    }

    /**
     * `POST /v2/payments/{token}/refund` — reverses funds to the customer.
     *
     * The amount is always explicit: there is no implicit "refund everything", unlike the
     * optional amount of capture.
     *
     * Do not send a merchant reference here: the official SDK does, but the field does not
     * exist in the schema. Use `merchantRefundReference` to track the refund instead.
     */
    public function refund(
        string $token,
        Amount $refundAmount,
        ?string $merchantRefundReference = null,
        ?string $idempotencyKey = null,
    ): RefundResult {
        $body = ['refundAmount' => $refundAmount->toArray()];

        if ($merchantRefundReference !== null) {
            $body['merchantRefundReference'] = $merchantRefundReference;
        }

        return RefundResult::fromResponse(
            $this->transport->post('/v2/payments/' . rawurlencode($token) . '/refund', $body, $idempotencyKey)
        );
    }
}
