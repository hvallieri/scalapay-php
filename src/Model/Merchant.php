<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

/**
 * Return URIs from the Scalapay checkout. Both are required.
 *
 * Note: no page of the documentation states which parameters Scalapay appends to these
 * URIs when the customer returns. This library therefore makes no assumption: keep the
 * order token on the merchant side at creation time.
 */
final class Merchant implements ModelInterface
{
    public const SANDBOX_CONFIRM_URL = 'https://portal.integration.scalapay.com/success-url';
    public const SANDBOX_CANCEL_URL = 'https://portal.integration.scalapay.com/failure-url';

    public function __construct(
        private readonly string $redirectConfirmUrl,
        private readonly string $redirectCancelUrl,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $confirm = $data['redirectConfirmUrl'] ?? null;
        $cancel = $data['redirectCancelUrl'] ?? null;

        return new self(
            is_scalar($confirm) ? (string) $confirm : '',
            is_scalar($cancel) ? (string) $cancel : ''
        );
    }

    public function getRedirectConfirmUrl(): string
    {
        return $this->redirectConfirmUrl;
    }

    public function getRedirectCancelUrl(): string
    {
        return $this->redirectCancelUrl;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'redirectConfirmUrl' => $this->redirectConfirmUrl,
            'redirectCancelUrl' => $this->redirectCancelUrl,
        ];
    }
}
