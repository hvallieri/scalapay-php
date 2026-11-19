<?php declare(strict_types=1);

namespace Hval\Scalapay\Exception;

/**
 * HTTP 400. On validation 400s (`errorCode` `api_validationerror`) the API `message` is
 * an object listing the rejected fields.
 */
class InvalidRequestException extends ApiException
{
    /**
     * Per-field validation errors, in the shape returned by the API: `field` (an array of path segments),
     * `location`, `messages`, `types`.
     *
     * @return list<array<string, mixed>>
     */
    public function getValidationErrors(): array
    {
        $message = $this->getApiMessage();

        if (is_array($message) === false) {
            return [];
        }

        $errors = $message['errors'] ?? null;

        if (is_array($errors) === false) {
            return [];
        }

        return array_values(array_filter($errors, is_array(...)));
    }

    /**
     * Paths of the rejected fields, joined with a dot (e.g. `totalAmount.amount`).
     *
     * @return list<string>
     */
    public function getInvalidFields(): array
    {
        $fields = [];

        foreach ($this->getValidationErrors() as $error) {
            $field = $error['field'] ?? null;

            if (is_array($field)) {
                $fields[] = implode('.', array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($field, is_scalar(...))));

                continue;
            }

            if (is_scalar($field)) {
                $fields[] = (string) $field;
            }
        }

        return $fields;
    }
}
