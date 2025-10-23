<?php

namespace Opayo\Validator;

use Opayo\Exception\OpayoValidationException;

/**
 * Validates transaction data before submission to Opayo
 */
class TransactionValidator
{
    /** @var array<string> */
    private const REQUIRED_FIELDS = [
        'Amount',
        'Currency',
        'Description',
    ];

    /** @var array<string, int> */
    private const MAX_LENGTHS = [
        'VendorTxCode' => 40,
        'Description' => 100,
        'BillingSurname' => 20,
        'BillingFirstnames' => 20,
        'BillingAddress1' => 100,
        'BillingAddress2' => 100,
        'BillingCity' => 40,
        'BillingPostCode' => 10,
        'BillingCountry' => 2,
        'DeliverySurname' => 20,
        'DeliveryFirstnames' => 20,
        'DeliveryAddress1' => 100,
        'DeliveryAddress2' => 100,
        'DeliveryCity' => 40,
        'DeliveryPostCode' => 10,
        'DeliveryCountry' => 2,
        'CustomerEMail' => 255,
    ];

    /**
     * Validate transaction fields
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    public function validate(array $fields): void
    {
        $this->validateRequiredFields($fields);
        $this->validateAmount($fields);
        $this->validateCurrency($fields);
        $this->validateFieldLengths($fields);
        $this->validateEmail($fields);
    }

    /**
     * Validate that required fields are present
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    private function validateRequiredFields(array $fields): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($fields[$field]) || $fields[$field] === '') {
                throw new OpayoValidationException(
                    "Required field '$field' is missing",
                    OpayoValidationException::MISSING_REQUIRED_FIELD,
                    null,
                    ['field' => $field]
                );
            }
        }
    }

    /**
     * Validate amount field
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    private function validateAmount(array $fields): void
    {
        if (!isset($fields['Amount'])) {
            return;
        }

        $amount = $fields['Amount'];

        if (!is_numeric($amount)) {
            throw new OpayoValidationException(
                'Amount must be numeric',
                OpayoValidationException::INVALID_AMOUNT,
                null,
                ['amount' => $amount]
            );
        }

        $numericAmount = (float)$amount;

        if ($numericAmount <= 0) {
            throw new OpayoValidationException(
                'Amount must be greater than zero',
                OpayoValidationException::INVALID_AMOUNT,
                null,
                ['amount' => $amount]
            );
        }

        // Check decimal places (max 2 for most currencies)
        if (preg_match('/\.\d{3,}/', (string)$amount)) {
            throw new OpayoValidationException(
                'Amount cannot have more than 2 decimal places',
                OpayoValidationException::INVALID_AMOUNT,
                null,
                ['amount' => $amount]
            );
        }
    }

    /**
     * Validate currency field
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    private function validateCurrency(array $fields): void
    {
        if (!isset($fields['Currency'])) {
            return;
        }

        $currency = $fields['Currency'];

        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new OpayoValidationException(
                'Currency must be a 3-letter ISO 4217 code (e.g., GBP, USD, EUR)',
                OpayoValidationException::INVALID_CURRENCY,
                null,
                ['currency' => $currency]
            );
        }
    }

    /**
     * Validate field lengths
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    private function validateFieldLengths(array $fields): void
    {
        foreach (self::MAX_LENGTHS as $field => $maxLength) {
            if (!isset($fields[$field])) {
                continue;
            }

            $value = (string)$fields[$field];
            $length = mb_strlen($value);

            if ($length > $maxLength) {
                throw new OpayoValidationException(
                    "Field '$field' exceeds maximum length of $maxLength characters (got $length)",
                    OpayoValidationException::FIELD_TOO_LONG,
                    null,
                    ['field' => $field, 'max_length' => $maxLength, 'actual_length' => $length]
                );
            }
        }
    }

    /**
     * Validate email field format
     *
     * @param array<string, mixed> $fields
     * @return void
     * @throws OpayoValidationException
     */
    private function validateEmail(array $fields): void
    {
        if (!isset($fields['CustomerEMail'])) {
            return;
        }

        $email = $fields['CustomerEMail'];

        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new OpayoValidationException(
                'CustomerEMail must be a valid email address',
                OpayoValidationException::INVALID_FIELD_FORMAT,
                null,
                ['field' => 'CustomerEMail', 'value' => $email]
            );
        }
    }
}
