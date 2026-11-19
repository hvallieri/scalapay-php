<?php declare(strict_types=1);

namespace Hval\Scalapay\Webhook;

use Hval\Scalapay\Exception\WebhookSignatureException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies and decodes incoming Scalapay webhooks.
 *
 * The signature is computed over the string `{version}:{timestamp}:{payload}`, where the
 * payload is the **raw request body**. It must never be re-encoded: signing depends on the
 * exact serialisation, so decoding and re-encoding would invalidate it.
 *
 * Answering the notification: reply with exactly `200`. The documentation names that
 * status, not the 2XX class, and a `204` may well be read as a failed delivery and trigger
 * retries. Reply quickly and process afterwards, since no maximum response time is
 * documented.
 *
 * Retries use exponential backoff and, the documentation warns explicitly, notifications
 * may not arrive in order. A handler must therefore be idempotent and must not be a state
 * machine that rejects out-of-order transitions; when the order matters, reconcile with
 * `GET /v2/payments/{token}` rather than trusting the last notification.
 */
final class WebhookHandler
{
    public const HEADER_SIGNATURE = 'x-scalapay-hmac-v1';
    public const HEADER_TIMESTAMP = 'x-scalapay-timestamp';

    public const SIGNATURE_VERSION = 'V1';

    private const ALGORITHM = 'sha256';

    /**
     * @param string $apiKey
     * @param int|null $toleranceSeconds when set, rejects notifications whose timestamp is
     *                                   further away than this from now. Scalapay documents
     *                                   no tolerance and no replay protection, so this is a
     *                                   choice of this library, disabled by default.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly ?int $toleranceSeconds = null,
    ) {
    }

    /**
     * Verifies the signature and returns the decoded notification.
     *
     * @param string $rawBody
     * @param array<string, mixed> $headers header names in any case; they are normalised
     *
     * @throws WebhookSignatureException when the signature is missing, malformed or invalid
     *
     * @return WebhookPayload
     */
    public function handle(string $rawBody, array $headers): WebhookPayload
    {
        $normalised = $this->normaliseHeaders($headers);

        $signature = $normalised[self::HEADER_SIGNATURE] ?? null;
        $timestamp = $normalised[self::HEADER_TIMESTAMP] ?? null;

        if ($signature === null || $signature === '') {
            throw new WebhookSignatureException('The webhook is missing the ' . self::HEADER_SIGNATURE . ' header.');
        }

        if ($timestamp === null || $timestamp === '') {
            throw new WebhookSignatureException('The webhook is missing the ' . self::HEADER_TIMESTAMP . ' header.');
        }

        if ($this->isSignatureValid($rawBody, $timestamp, $signature) === false) {
            throw new WebhookSignatureException('The webhook signature does not match the payload.');
        }

        $this->assertFreshness($timestamp);

        return new WebhookPayload($this->decode($rawBody), $rawBody);
    }

    /**
     * Convenience wrapper for PSR-7 server requests.
     *
     * @param ServerRequestInterface $request
     *
     * @throws WebhookSignatureException
     *
     * @return WebhookPayload
     */
    public function handleRequest(ServerRequestInterface $request): WebhookPayload
    {
        $body = $request->getBody();

        if ($body->isSeekable() === true) {
            $body->rewind();
        }

        $headers = array_map(static fn (array $values): string => $values[0] ?? '', $request->getHeaders());

        return $this->handle((string) $body, $headers);
    }

    /**
     * Computes the signature of a payload, mainly useful in tests.
     */
    public function sign(string $rawBody, string $timestamp): string
    {
        return hash_hmac(
            self::ALGORITHM,
            sprintf('%s:%s:%s', self::SIGNATURE_VERSION, $timestamp, $rawBody),
            $this->apiKey
        );
    }

    public function isSignatureValid(string $rawBody, string $timestamp, string $signature): bool
    {
        return hash_equals($this->sign($rawBody, $timestamp), $signature);
    }

    /**
     * @param string $timestamp
     *
     * @throws WebhookSignatureException
     */
    private function assertFreshness(string $timestamp): void
    {
        if ($this->toleranceSeconds === null) {
            return;
        }

        if (is_numeric($timestamp) === false) {
            throw new WebhookSignatureException('The webhook timestamp is not numeric.');
        }

        // The header carries milliseconds; a retried notification is re-signed, so its
        // timestamp is that of the new attempt rather than of the original event.
        $ageSeconds = abs(time() - (int) ((float) $timestamp / 1000));

        if ($ageSeconds > $this->toleranceSeconds) {
            throw new WebhookSignatureException(sprintf('The webhook timestamp is %d seconds away from now, beyond the %d seconds tolerance.', $ageSeconds, $this->toleranceSeconds));
        }
    }

    /**
     * @throws WebhookSignatureException
     *
     * @return array<array-key, mixed>
     */
    private function decode(string $rawBody): array
    {
        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new WebhookSignatureException('The webhook body is not valid JSON.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Header names are lower-cased before lookup: the documentation warns that some
     * languages alter their capitalisation.
     *
     * @param array<string, mixed> $headers
     *
     * @return array<string, string>
     */
    private function normaliseHeaders(array $headers): array
    {
        $normalised = [];

        foreach ($headers as $name => $value) {
            if (is_array($value) === true) {
                $value = $value[0] ?? '';
            }

            $normalised[strtolower((string) $name)] = is_scalar($value) ? (string) $value : '';
        }

        return $normalised;
    }
}
