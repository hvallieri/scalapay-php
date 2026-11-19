<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * An error reported by the API.
 *
 * The documentation explicitly recommends basing error handling on `errorCode` and the
 * HTTP status, never on the message, which may change over time. `errorId` is the
 * identifier Scalapay support asks for when tracing a case.
 */
class ApiException extends ScalapayException
{
    /**
     * @param string $message
     * @param int $httpStatus
     * @param array<string, mixed> $payload the decoded error body when it was a JSON object; a bare JSON string arrives here as `message`
     * @param string $rawBody
     */
    public function __construct(
        string $message,
        int $httpStatus,
        private readonly array $payload = [],
        private readonly string $rawBody = '',
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * @param int $httpStatus
     * @param array<string, mixed> $payload
     * @param string $rawBody
     *
     * @return static
     */
    public static function fromResponse(int $httpStatus, array $payload, string $rawBody): static
    {
        return new static(self::buildMessage($httpStatus, $payload, $rawBody), $httpStatus, $payload, $rawBody);
    }

    /**
     * The error code. Always a string: official examples show it holding both a semantic
     * code (`order_amount_exceeds_maximum_limit`) and the HTTP status repeated as a
     * string (`"400"`). Treat it as opaque rather than parsing it.
     */
    public function getErrorCode(): ?string
    {
        $errorCode = $this->payload['errorCode'] ?? null;

        return is_scalar($errorCode) ? (string) $errorCode : null;
    }

    public function getErrorId(): ?string
    {
        $errorId = $this->payload['errorId'] ?? null;

        return is_scalar($errorId) ? (string) $errorId : null;
    }

    /**
     * The API `message` is not always a string: on validation 400s it is an object, on
     * 409s a string, and a 401 returns the bare string `"Unauthorized"`.
     */
    public function getApiMessage(): mixed
    {
        return $this->payload['message'] ?? null;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }

    /**
     * The decoded error body. When the API answered with a bare JSON string, as on 401,
     * that string is exposed under a synthetic `message` key; getRawBody() keeps the
     * original.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * True when the operation can be retried as it is. The 409
     * `conflicting_operation_in_progress` is the only documented case.
     */
    public function isRetryable(): bool
    {
        return $this->getCode() === 409;
    }

    /**
     * @param int $httpStatus
     * @param array<string, mixed> $payload
     * @param string $rawBody
     *
     * @return string
     */
    private static function buildMessage(int $httpStatus, array $payload, string $rawBody): string
    {
        $message = $payload['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        if (is_array($message)) {
            $flattened = self::flattenValidationMessage($message);

            if ($flattened !== '') {
                return $flattened;
            }
        }

        $errorCode = $payload['errorCode'] ?? null;

        return match (true) {
            is_scalar($errorCode) && (string) $errorCode !== '' => sprintf('Scalapay API error %s (HTTP %d).', (string) $errorCode, $httpStatus),
            trim($rawBody) !== '' => sprintf('Scalapay API error (HTTP %d): %s', $httpStatus, trim($rawBody)),
            default => sprintf('Scalapay API error (HTTP %d).', $httpStatus),
        };
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function flattenValidationMessage(array $message): string
    {
        $errors = $message['errors'] ?? null;

        if (is_array($errors) === false) {
            return '';
        }

        $parts = [];

        foreach ($errors as $error) {
            if (is_array($error) === false) {
                continue;
            }

            $field = $error['field'] ?? null;
            $field = is_array($field) ? implode('.', array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($field, is_scalar(...)))) : (is_scalar($field) ? (string) $field : '');

            $messages = $error['messages'] ?? null;
            $messages = is_array($messages) ? implode('; ', array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($messages, is_scalar(...)))) : '';

            if ($field === '' && $messages === '') {
                continue;
            }

            $parts[] = $field === '' ? $messages : sprintf('%s: %s', $field, $messages);
        }

        return implode(' | ', $parts);
    }
}
