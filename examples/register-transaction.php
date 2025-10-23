<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as HttpClient;
use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\HttpOptions;
use Opayo\Http\ResponseParser;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Logger\OpayoLogger;

// Create configuration (from environment variables)
$config = Config::sandbox(
    'your_vendor_name',
    'your_encryption_password'
);

// Or load from environment:
// $config = Config::fromEnvironment();

// Create logger
$logger = new OpayoLogger('/var/log/opayo.log');

// Create HTTP client
$httpClient = new HttpClient();

// Create crypto instance
$crypto = new OpayoCrypto();

// Create request builder
$requestBuilder = new TransactionRequestBuilder($crypto, $config);

// Create response parser
$responseParser = new ResponseParser();

// Optional: Create custom HTTP options
$httpOptions = new HttpOptions(
    timeout: 30,
    connectTimeout: 10,
    verify: true
);

// Create Opayo client with all dependencies
$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser,
    null, // Use default TransactionValidator
    $httpOptions
);

// Prepare transaction data
$transactionData = [
    'Amount' => '100.00',
    'Currency' => 'GBP',
    'Description' => 'Test payment',
    'VendorTxCode' => 'ORDER-' . time(),

    // Billing details
    'BillingSurname' => 'Smith',
    'BillingFirstnames' => 'John',
    'BillingAddress1' => '123 Test Street',
    'BillingCity' => 'London',
    'BillingPostCode' => 'SW1A 1AA',
    'BillingCountry' => 'GB',

    // Delivery details (optional - can use billing details)
    'DeliverySurname' => 'Smith',
    'DeliveryFirstnames' => 'John',
    'DeliveryAddress1' => '123 Test Street',
    'DeliveryCity' => 'London',
    'DeliveryPostCode' => 'SW1A 1AA',
    'DeliveryCountry' => 'GB',

    // Customer details
    'CustomerEMail' => 'john.smith@example.com',
    'CustomerName' => 'John Smith',

    // Card details are NOT sent here - customer will enter them on Opayo's secure page
];

try {
    // Register the transaction
    $response = $client->registerTransaction($transactionData);

    if ($response->isSuccessful()) {
        echo "Transaction registered successfully!\n";
        echo "VPS Transaction ID: " . $response->getVPSTxId() . "\n";
        echo "Security Key: " . $response->getSecurityKey() . "\n";

        // Store the security key for notification validation
        // $_SESSION['tx_' . $transactionData['VendorTxCode']] = $response->getSecurityKey();

    } elseif ($response->requires3DSecure()) {
        echo "3D Secure authentication required\n";
        echo "Redirect to: " . $response->getNextURL() . "\n";

        // Redirect customer to 3D Secure page
        // header('Location: ' . $response->getNextURL());
        // exit;
    }

} catch (\Opayo\Exception\OpayoValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    print_r($e->getContext());

} catch (\Opayo\Exception\OpayoNetworkException $e) {
    echo "Network error: " . $e->getMessage() . "\n";

} catch (\Opayo\Exception\OpayoException $e) {
    echo "Opayo error: " . $e->getMessage() . "\n";
    print_r($e->getContext());
}
