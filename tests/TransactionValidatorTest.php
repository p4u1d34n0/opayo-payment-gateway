<?php

namespace Opayo\Tests;

use Opayo\Exception\OpayoValidationException;
use Opayo\Validator\TransactionValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TransactionValidator class
 */
class TransactionValidatorTest extends TestCase
{
    private TransactionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TransactionValidator();
    }

    public function testValidateValidTransaction(): void
    {
        $fields = [
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test transaction',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true); // If no exception is thrown, validation passed
    }

    public function testValidateMissingAmount(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::MISSING_REQUIRED_FIELD);
        $this->expectExceptionMessage("Required field 'Amount' is missing");

        $this->validator->validate([
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateMissingCurrency(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::MISSING_REQUIRED_FIELD);
        $this->expectExceptionMessage("Required field 'Currency' is missing");

        $this->validator->validate([
            'Amount' => '10.00',
            'Description' => 'Test',
        ]);
    }

    public function testValidateMissingDescription(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::MISSING_REQUIRED_FIELD);
        $this->expectExceptionMessage("Required field 'Description' is missing");

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
        ]);
    }

    public function testValidateEmptyAmount(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::MISSING_REQUIRED_FIELD);

        $this->validator->validate([
            'Amount' => '',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateNonNumericAmount(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_AMOUNT);
        $this->expectExceptionMessage('Amount must be numeric');

        $this->validator->validate([
            'Amount' => 'invalid',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateNegativeAmount(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_AMOUNT);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->validator->validate([
            'Amount' => '-10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateZeroAmount(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_AMOUNT);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->validator->validate([
            'Amount' => '0',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateAmountWithTooManyDecimalPlaces(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_AMOUNT);
        $this->expectExceptionMessage('Amount cannot have more than 2 decimal places');

        $this->validator->validate([
            'Amount' => '10.999',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);
    }

    public function testValidateAmountWithTwoDecimalPlaces(): void
    {
        $fields = [
            'Amount' => '10.99',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }

    public function testValidateAmountWithOneDecimalPlace(): void
    {
        $fields = [
            'Amount' => '10.5',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }

    public function testValidateAmountWithoutDecimal(): void
    {
        $fields = [
            'Amount' => '10',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }

    public function testValidateInvalidCurrencyFormat(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_CURRENCY);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO 4217 code');

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GB',
            'Description' => 'Test',
        ]);
    }

    public function testValidateLowercaseCurrency(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_CURRENCY);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO 4217 code');

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'gbp',
            'Description' => 'Test',
        ]);
    }

    public function testValidateCurrencyWithNumbers(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_CURRENCY);

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GB1',
            'Description' => 'Test',
        ]);
    }

    public function testValidateVariousCurrencies(): void
    {
        $currencies = ['GBP', 'USD', 'EUR', 'JPY', 'AUD'];

        foreach ($currencies as $currency) {
            $fields = [
                'Amount' => '10.00',
                'Currency' => $currency,
                'Description' => 'Test',
            ];

            $this->validator->validate($fields);
        }

        $this->assertTrue(true);
    }

    public function testValidateInvalidEmail(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::INVALID_FIELD_FORMAT);
        $this->expectExceptionMessage('CustomerEMail must be a valid email address');

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'CustomerEMail' => 'invalid-email',
        ]);
    }

    public function testValidateValidEmail(): void
    {
        $fields = [
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'CustomerEMail' => 'customer@example.com',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }

    public function testValidateDescriptionTooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);
        $this->expectExceptionMessage("Field 'Description' exceeds maximum length of 100 characters");

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => str_repeat('a', 101),
        ]);
    }

    public function testValidateVendorTxCodeTooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'VendorTxCode' => str_repeat('a', 41),
        ]);
    }

    public function testValidateBillingSurnameTooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'BillingSurname' => str_repeat('a', 21),
        ]);
    }

    public function testValidateBillingAddress1TooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'BillingAddress1' => str_repeat('a', 101),
        ]);
    }

    public function testValidateBillingPostCodeTooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'BillingPostCode' => str_repeat('a', 11),
        ]);
    }

    public function testValidateCustomerEmailTooLong(): void
    {
        $this->expectException(OpayoValidationException::class);
        $this->expectExceptionCode(OpayoValidationException::FIELD_TOO_LONG);

        $email = str_repeat('a', 246) . '@example.com'; // 256 characters

        $this->validator->validate([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
            'CustomerEMail' => $email,
        ]);
    }

    public function testValidateCompleteTransactionData(): void
    {
        $fields = [
            'Amount' => '99.99',
            'Currency' => 'GBP',
            'Description' => 'Complete test transaction',
            'VendorTxCode' => 'TX-12345',
            'CustomerEMail' => 'customer@example.com',
            'BillingSurname' => 'Smith',
            'BillingFirstnames' => 'John',
            'BillingAddress1' => '123 Test Street',
            'BillingCity' => 'London',
            'BillingPostCode' => 'SW1A 1AA',
            'BillingCountry' => 'GB',
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }

    public function testValidateFieldLengthsAtBoundary(): void
    {
        $fields = [
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => str_repeat('a', 100), // Exactly 100 characters
            'VendorTxCode' => str_repeat('b', 40), // Exactly 40 characters
            'BillingSurname' => str_repeat('c', 20), // Exactly 20 characters
        ];

        $this->validator->validate($fields);
        $this->assertTrue(true);
    }
}
