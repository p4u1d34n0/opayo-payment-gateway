# Opayo PHP SDK - Use Cases

This document provides comprehensive examples of how to use the Opayo PHP SDK in various real-world scenarios.

## Table of Contents

1. [Basic Transaction Processing](#use-case-1-basic-transaction-processing)
2. [E-commerce Checkout Integration](#use-case-2-e-commerce-checkout-integration)
3. [Subscription Billing](#use-case-3-subscription-billing)
4. [3D Secure Authentication Flow](#use-case-4-3d-secure-authentication-flow)
5. [Server Notification Handling](#use-case-5-server-notification-handling)
6. [Multi-Currency Support](#use-case-6-multi-currency-support)
7. [Custom Logging and Monitoring](#use-case-7-custom-logging-and-monitoring)
8. [Sandbox Testing](#use-case-8-sandbox-testing)
9. [Error Handling and Recovery](#use-case-9-error-handling-and-recovery)
10. [Production Deployment](#use-case-10-production-deployment)

---

## Use Case 1: Basic Transaction Processing

The simplest scenario: processing a single payment transaction.

### Scenario

A small business wants to process a one-time payment from a customer.

### Implementation

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

// Initialize configuration
$config = Config::sandbox(
    vendor: 'YourVendorName',
    encryptionPassword: 'YourEncryptionPassword'
);

// Set up dependencies
$logger = new OpayoLogger(__DIR__ . '/logs/opayo.log');
$httpClient = new HttpClient();
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

// Create client
$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser
);

// Prepare transaction
$transaction = [
    'Amount' => '49.99',
    'Currency' => 'GBP',
    'Description' => 'Product Purchase',
    'VendorTxCode' => 'ORDER-' . uniqid(),
    'CustomerEMail' => 'customer@example.com',
];

try {
    $response = $client->registerTransaction($transaction);

    if ($response->isSuccessful()) {
        echo "Payment successful!\n";
        echo "Transaction ID: " . $response->getVPSTxId() . "\n";
        // Redirect customer to success page
    } elseif ($response->requires3DSecure()) {
        // Redirect to 3D Secure authentication
        header('Location: ' . $response->getNextURL());
        exit;
    }
} catch (\Opayo\Exception\OpayoException $e) {
    echo "Payment failed: " . $e->getMessage() . "\n";
}
```

### Key Points

- Minimal configuration required
- Automatic encryption handling
- Clear success/failure indication
- 3D Secure redirection support

---

## Use Case 2: E-commerce Checkout Integration

Full integration with an e-commerce shopping cart.

### Scenario

An online store needs to process payments during checkout, including billing and shipping information.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

class CheckoutProcessor
{
    private Client $opayoClient;
    private Database $db;

    public function __construct()
    {
        $config = Config::sandbox(
            vendor: $_ENV['OPAYO_VENDOR'],
            encryptionPassword: $_ENV['OPAYO_PASSWORD']
        );

        $logger = new OpayoLogger($_ENV['LOG_PATH'] . '/opayo.log');
        $httpClient = new HttpClient();
        $crypto = new OpayoCrypto();
        $requestBuilder = new TransactionRequestBuilder($crypto, $config);
        $responseParser = new ResponseParser();

        $this->opayoClient = new Client(
            $config,
            $logger,
            $httpClient,
            $requestBuilder,
            $responseParser
        );

        $this->db = new Database();
    }

    public function processCheckout(array $cart, array $customer, array $billing): array
    {
        // Calculate total
        $total = $this->calculateTotal($cart);

        // Create order in database
        $orderId = $this->db->createOrder($cart, $customer, $total);

        // Prepare transaction data
        $transaction = [
            'VendorTxCode' => "ORDER-{$orderId}-" . time(),
            'Amount' => number_format($total, 2, '.', ''),
            'Currency' => 'GBP',
            'Description' => $this->buildDescription($cart),
            'CustomerEMail' => $customer['email'],
            'BillingSurname' => $billing['surname'],
            'BillingFirstnames' => $billing['firstname'],
            'BillingAddress1' => $billing['address1'],
            'BillingAddress2' => $billing['address2'] ?? '',
            'BillingCity' => $billing['city'],
            'BillingPostCode' => $billing['postcode'],
            'BillingCountry' => $billing['country'],
            'BillingPhone' => $billing['phone'],
            'DeliverySurname' => $customer['surname'],
            'DeliveryFirstnames' => $customer['firstname'],
            'DeliveryAddress1' => $customer['address1'],
            'DeliveryAddress2' => $customer['address2'] ?? '',
            'DeliveryCity' => $customer['city'],
            'DeliveryPostCode' => $customer['postcode'],
            'DeliveryCountry' => $customer['country'],
            'DeliveryPhone' => $customer['phone'],
        ];

        try {
            $response = $this->opayoClient->registerTransaction($transaction);

            // Store response in database
            $this->db->updateOrderPayment($orderId, [
                'vps_tx_id' => $response->getVPSTxId(),
                'security_key' => $response->getSecurityKey(),
                'status' => $response->getStatus(),
            ]);

            if ($response->isSuccessful()) {
                $this->db->markOrderAsPaid($orderId);
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'transaction_id' => $response->getVPSTxId(),
                ];
            } elseif ($response->requires3DSecure()) {
                // Store 3D Secure state
                $this->db->setOrder3DSecure($orderId, $response->getNextURL());
                return [
                    'requires_3ds' => true,
                    'redirect_url' => $response->getNextURL(),
                    'order_id' => $orderId,
                ];
            }
        } catch (\Opayo\Exception\OpayoValidationException $e) {
            $this->db->logPaymentError($orderId, 'validation', $e->getMessage());
            return ['error' => 'Invalid payment data: ' . $e->getMessage()];
        } catch (\Opayo\Exception\OpayoNetworkException $e) {
            $this->db->logPaymentError($orderId, 'network', $e->getMessage());
            return ['error' => 'Payment system unavailable. Please try again.'];
        } catch (\Opayo\Exception\OpayoException $e) {
            $this->db->logPaymentError($orderId, 'payment', $e->getMessage());
            return ['error' => 'Payment declined: ' . $e->getMessage()];
        }

        return ['error' => 'Unknown error occurred'];
    }

    private function calculateTotal(array $cart): float
    {
        return array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $cart));
    }

    private function buildDescription(array $cart): string
    {
        $items = array_map(fn($item) => $item['name'], $cart);
        return 'Order: ' . implode(', ', array_slice($items, 0, 3));
    }
}
```

### Key Points

- Complete customer and billing information
- Order tracking integration
- Comprehensive error handling
- Database state management

---

## Use Case 3: Subscription Billing

Processing recurring payments for subscription services.

### Scenario

A SaaS application needs to charge customers monthly for their subscription.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

class SubscriptionBilling
{
    private Client $opayoClient;
    private Database $db;
    private Mailer $mailer;

    public function __construct()
    {
        $config = Config::live(
            vendor: $_ENV['OPAYO_VENDOR'],
            encryptionPassword: $_ENV['OPAYO_PASSWORD']
        );

        $logger = new OpayoLogger($_ENV['LOG_PATH'] . '/subscription.log');
        $httpClient = new HttpClient();
        $crypto = new OpayoCrypto();
        $requestBuilder = new TransactionRequestBuilder($crypto, $config);
        $responseParser = new ResponseParser();

        $this->opayoClient = new Client(
            $config,
            $logger,
            $httpClient,
            $requestBuilder,
            $responseParser
        );

        $this->db = new Database();
        $this->mailer = new Mailer();
    }

    public function processMonthlyBilling(): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Get all active subscriptions due for billing
        $subscriptions = $this->db->getDueSubscriptions();

        foreach ($subscriptions as $subscription) {
            try {
                $customer = $this->db->getCustomer($subscription['customer_id']);

                $transaction = [
                    'VendorTxCode' => "SUB-{$subscription['id']}-" . date('Ym'),
                    'Amount' => number_format($subscription['amount'], 2, '.', ''),
                    'Currency' => $subscription['currency'],
                    'Description' => "Monthly subscription - {$subscription['plan_name']}",
                    'CustomerEMail' => $customer['email'],
                    'BillingSurname' => $customer['surname'],
                    'BillingFirstnames' => $customer['firstname'],
                    'BillingAddress1' => $customer['address1'],
                    'BillingCity' => $customer['city'],
                    'BillingPostCode' => $customer['postcode'],
                    'BillingCountry' => $customer['country'],
                ];

                $response = $this->opayoClient->registerTransaction($transaction);

                if ($response->isSuccessful()) {
                    $this->db->recordSubscriptionPayment(
                        $subscription['id'],
                        $response->getVPSTxId(),
                        $subscription['amount']
                    );

                    $this->mailer->sendPaymentConfirmation(
                        $customer['email'],
                        $subscription['amount'],
                        $response->getVPSTxId()
                    );

                    $results['successful']++;
                } else {
                    $this->handleFailedSubscription($subscription, $customer, $response);
                    $results['failed']++;
                }
            } catch (\Opayo\Exception\OpayoException $e) {
                $this->handleSubscriptionError($subscription, $customer, $e);
                $results['failed']++;
                $results['errors'][] = "Subscription {$subscription['id']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    private function handleFailedSubscription($subscription, $customer, $response): void
    {
        // Mark subscription as failed
        $this->db->markSubscriptionPaymentFailed(
            $subscription['id'],
            $response->getStatusDetail()
        );

        // Send failure notification
        $this->mailer->sendPaymentFailure(
            $customer['email'],
            $subscription['plan_name'],
            $response->getStatusDetail()
        );

        // Check if should suspend account
        $failureCount = $this->db->getSubscriptionFailureCount($subscription['id']);
        if ($failureCount >= 3) {
            $this->db->suspendSubscription($subscription['id']);
            $this->mailer->sendSubscriptionSuspended($customer['email']);
        }
    }

    private function handleSubscriptionError($subscription, $customer, $exception): void
    {
        $this->db->logSubscriptionError(
            $subscription['id'],
            $exception->getMessage()
        );

        $this->mailer->sendPaymentSystemError(
            $customer['email'],
            $subscription['plan_name']
        );
    }
}
```

### Key Points

- Batch processing of subscriptions
- Retry logic and failure handling
- Customer notification system
- Account suspension after multiple failures

---

## Use Case 4: 3D Secure Authentication Flow

Handling 3D Secure (3DS) authentication for enhanced security.

### Scenario

Process payments with 3D Secure authentication when required by the card issuer.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

// Step 1: Initial transaction registration
function initiatePayment(array $paymentData): array
{
    $config = Config::sandbox(
        vendor: $_ENV['OPAYO_VENDOR'],
        encryptionPassword: $_ENV['OPAYO_PASSWORD']
    );

    $logger = new OpayoLogger(__DIR__ . '/logs/opayo.log');
    $httpClient = new HttpClient();
    $crypto = new OpayoCrypto();
    $requestBuilder = new TransactionRequestBuilder($crypto, $config);
    $responseParser = new ResponseParser();

    $client = new Client(
        $config,
        $logger,
        $httpClient,
        $requestBuilder,
        $responseParser
    );

    $transaction = [
        'VendorTxCode' => $paymentData['order_id'] . '-' . time(),
        'Amount' => $paymentData['amount'],
        'Currency' => 'GBP',
        'Description' => $paymentData['description'],
        'CustomerEMail' => $paymentData['email'],
        'BillingSurname' => $paymentData['surname'],
        'BillingFirstnames' => $paymentData['firstname'],
        'BillingAddress1' => $paymentData['address1'],
        'BillingCity' => $paymentData['city'],
        'BillingPostCode' => $paymentData['postcode'],
        'BillingCountry' => $paymentData['country'],
    ];

    try {
        $response = $client->registerTransaction($transaction);

        if ($response->isSuccessful()) {
            return [
                'status' => 'success',
                'transaction_id' => $response->getVPSTxId(),
                'security_key' => $response->getSecurityKey(),
            ];
        } elseif ($response->requires3DSecure()) {
            // Store transaction details in session for callback
            $_SESSION['pending_transaction'] = [
                'vendor_tx_code' => $transaction['VendorTxCode'],
                'vps_tx_id' => $response->getVPSTxId(),
                'security_key' => $response->getSecurityKey(),
                'amount' => $paymentData['amount'],
                'order_id' => $paymentData['order_id'],
            ];

            return [
                'status' => '3ds_required',
                'redirect_url' => $response->getNextURL(),
            ];
        } else {
            return [
                'status' => 'failed',
                'message' => $response->getStatusDetail(),
            ];
        }
    } catch (\Opayo\Exception\OpayoException $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
    }
}

// Step 2: Handle 3DS callback
function handle3DSecureCallback(): array
{
    if (!isset($_SESSION['pending_transaction'])) {
        return ['status' => 'error', 'message' => 'No pending transaction'];
    }

    $pendingTx = $_SESSION['pending_transaction'];

    // Opayo sends back MD and PaRes parameters after 3DS authentication
    $md = $_POST['MD'] ?? '';
    $paRes = $_POST['PaRes'] ?? '';

    if (empty($md) || empty($paRes)) {
        return ['status' => 'error', 'message' => 'Invalid 3DS callback'];
    }

    // In a real implementation, you would send these to Opayo's 3DS completion endpoint
    // For this example, we'll assume the transaction completed successfully

    // Clear session
    unset($_SESSION['pending_transaction']);

    return [
        'status' => 'success',
        'transaction_id' => $pendingTx['vps_tx_id'],
        'order_id' => $pendingTx['order_id'],
        'amount' => $pendingTx['amount'],
    ];
}

// Example usage in controller
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment'])) {
    $result = initiatePayment($_POST['payment']);

    if ($result['status'] === '3ds_required') {
        // Redirect to 3DS authentication page
        header('Location: ' . $result['redirect_url']);
        exit;
    } elseif ($result['status'] === 'success') {
        // Payment successful without 3DS
        header('Location: /success?tx=' . $result['transaction_id']);
        exit;
    } else {
        // Payment failed
        header('Location: /failed?error=' . urlencode($result['message']));
        exit;
    }
}

// Handle 3DS callback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['MD'])) {
    $result = handle3DSecureCallback();

    if ($result['status'] === 'success') {
        header('Location: /success?tx=' . $result['transaction_id']);
        exit;
    } else {
        header('Location: /failed?error=' . urlencode($result['message']));
        exit;
    }
}
```

### Key Points

- Session management for 3DS flow
- Proper callback handling
- State preservation during redirect
- Graceful fallback for failures

---

## Use Case 5: Server Notification Handling

Processing Opayo server notifications for transaction status updates.

### Scenario

Set up a webhook endpoint to receive and process Opayo server notifications.

### Implementation

```php
<?php

use Opayo\NotificationHandler;
use Opayo\NotificationResponse;
use Opayo\Logger\OpayoLogger;

// notification-endpoint.php
require_once __DIR__ . '/vendor/autoload.php';

// Initialize logger
$logger = new OpayoLogger(__DIR__ . '/logs/notifications.log');

// Create notification handler
// IMPORTANT: vendorName is required for correct signature verification
$handler = new NotificationHandler(
    encryptionPassword: $_ENV['OPAYO_PASSWORD'],
    logger: $logger,
    baseURL: 'https://yourdomain.com',
    vendorName: $_ENV['OPAYO_VENDOR']
);

// Get notification data
$notificationData = $_POST;

// Process notification
$response = $handler->handle(
    data: $notificationData,

    // Get security key callback
    getKey: function(string $vendorTxCode): string {
        $db = new Database();
        $order = $db->getOrderByVendorTxCode($vendorTxCode);

        if (!$order) {
            throw new Exception("Order not found: {$vendorTxCode}");
        }

        return $order['security_key'];
    },

    // Check if already processed callback
    checkProcessed: function(string $vpsTxId): bool {
        $db = new Database();
        return $db->isTransactionProcessed($vpsTxId);
    },

    // Get redirect URL callback
    getRedirectURL: function(string $vendorTxCode): string {
        $db = new Database();
        $order = $db->getOrderByVendorTxCode($vendorTxCode);
        return "/order/success/{$order['id']}";
    },

    // Success callback
    onSuccess: function(string $vendorTxCode, array $data): void {
        $db = new Database();
        $mailer = new Mailer();

        // Update order status
        $order = $db->getOrderByVendorTxCode($vendorTxCode);
        $db->markOrderAsPaid($order['id'], $data['VPSTxId']);

        // Send confirmation email
        $customer = $db->getCustomer($order['customer_id']);
        $mailer->sendOrderConfirmation($customer['email'], $order);

        // Trigger fulfillment
        $fulfillment = new FulfillmentService();
        $fulfillment->processOrder($order['id']);
    },

    // Failure callback
    onFailure: function(string $vendorTxCode, array $data): void {
        $db = new Database();
        $mailer = new Mailer();

        // Update order status
        $order = $db->getOrderByVendorTxCode($vendorTxCode);
        $db->markOrderAsFailed(
            $order['id'],
            $data['Status'],
            $data['StatusDetail']
        );

        // Notify customer
        $customer = $db->getCustomer($order['customer_id']);
        $mailer->sendPaymentFailure(
            $customer['email'],
            $data['StatusDetail']
        );
    },

    // Repeat notification callback
    onRepeat: function(string $vendorTxCode): void {
        $logger = new OpayoLogger(__DIR__ . '/logs/notifications.log');
        $logger->info("Duplicate notification received for: {$vendorTxCode}");
    }
);

// Send response back to Opayo
header('Content-Type: text/plain');
echo $response->format();
exit;
```

### Key Points

- **Webhook endpoint implementation**: Complete notification handling workflow
- **Signature verification**: Uses MD5 signature with 21 fields in exact order per Opayo Server Protocol 3.00
- **Duplicate detection**: Prevents double-processing of notifications
- **Async processing support**: Can queue heavy operations for background processing
- **Proper response formatting**: Returns correctly formatted response to Opayo

### Security Considerations

**CRITICAL**: Always implement IP whitelisting for notification endpoints:

```php
// At the top of notification-endpoint.php
$allowedIPs = [
    '195.170.169.0/24',  // Opayo Test Server IPs (example)
    '46.229.226.0/24',   // Opayo Live Server IPs (example)
];

$clientIP = $_SERVER['REMOTE_ADDR'];
$allowed = false;

foreach ($allowedIPs as $range) {
    if (ipInRange($clientIP, $range)) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

function ipInRange($ip, $range) {
    // Implementation of CIDR range check
    // Use a library like symfony/http-foundation for production
}
```

**Signature Verification**: The library automatically verifies signatures using:
- All 21 fields from Opayo Server Protocol 3.00
- Vendor name (lowercase)
- Security key (embedded in hash)
- URL decoding of all values
- Timing-safe comparison

**Debug Signature Issues** (development only):
```php
$debug = $handler->debugSignature($_POST, $securityKey);
print_r($debug);
// Shows: signature_string, field_values, expected_signature, received_signature, match
```

---

## Use Case 6: Multi-Currency Support

Handle transactions in multiple currencies.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

class MultiCurrencyPayment
{
    private Client $opayoClient;
    private array $supportedCurrencies = ['GBP', 'EUR', 'USD'];

    public function __construct()
    {
        $config = Config::sandbox(
            vendor: $_ENV['OPAYO_VENDOR'],
            encryptionPassword: $_ENV['OPAYO_PASSWORD']
        );

        $logger = new OpayoLogger(__DIR__ . '/logs/opayo.log');
        $httpClient = new HttpClient();
        $crypto = new OpayoCrypto();
        $requestBuilder = new TransactionRequestBuilder($crypto, $config);
        $responseParser = new ResponseParser();

        $this->opayoClient = new Client(
            $config,
            $logger,
            $httpClient,
            $requestBuilder,
            $responseParser
        );
    }

    public function processPayment(array $data): array
    {
        // Validate currency
        if (!in_array($data['currency'], $this->supportedCurrencies)) {
            return ['error' => 'Unsupported currency'];
        }

        // Format amount based on currency
        $amount = $this->formatAmount($data['amount'], $data['currency']);

        $transaction = [
            'VendorTxCode' => 'ORDER-' . uniqid(),
            'Amount' => $amount,
            'Currency' => $data['currency'],
            'Description' => $data['description'],
            'CustomerEMail' => $data['email'],
        ];

        try {
            $response = $this->opayoClient->registerTransaction($transaction);

            if ($response->isAccepted()) {
                return [
                    'success' => true,
                    'transaction_id' => $response->getVPSTxId(),
                    'amount' => $amount,
                    'currency' => $data['currency'],
                ];
            }
        } catch (\Opayo\Exception\OpayoException $e) {
            return ['error' => $e->getMessage()];
        }

        return ['error' => 'Payment failed'];
    }

    private function formatAmount(float $amount, string $currency): string
    {
        // All currencies use 2 decimal places for Opayo
        return number_format($amount, 2, '.', '');
    }
}
```

---

## Use Case 7: Custom Logging and Monitoring

Implement custom logging with PSR-3 compatible logger.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SlackWebhookHandler;
use GuzzleHttp\Client as HttpClient;

// Set up Monolog
$logger = new Logger('opayo');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/opayo.log', Logger::DEBUG));
$logger->pushHandler(new SlackWebhookHandler(
    $_ENV['SLACK_WEBHOOK'],
    '#payments',
    'Opayo',
    Logger::ERROR
));

$config = Config::live(
    vendor: $_ENV['OPAYO_VENDOR'],
    encryptionPassword: $_ENV['OPAYO_PASSWORD']
);

$httpClient = new HttpClient();
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

$client = new Client(
    $config,
    $logger, // Monolog logger
    $httpClient,
    $requestBuilder,
    $responseParser
);
```

---

## Use Case 8: Sandbox Testing

Test integration safely using sandbox mode.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

// Sandbox configuration
$config = Config::sandbox(
    vendor: 'sandbox',
    encryptionPassword: 'sandbox123456789'
);

$logger = new OpayoLogger(__DIR__ . '/logs/sandbox.log');
$httpClient = new HttpClient();
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

$client = new Client($config, $logger, $httpClient, $requestBuilder, $responseParser);

// Test successful payment
$testTransaction = [
    'VendorTxCode' => 'TEST-' . time(),
    'Amount' => '10.00',
    'Currency' => 'GBP',
    'Description' => 'Sandbox test',
    'CustomerEMail' => 'test@example.com',
];

$response = $client->registerTransaction($testTransaction);
echo "Test result: " . $response->getStatus() . "\n";
```

---

## Use Case 9: Error Handling and Recovery

Comprehensive error handling strategy.

### Implementation

```php
<?php

use Opayo\Exception\OpayoValidationException;
use Opayo\Exception\OpayoNetworkException;
use Opayo\Exception\OpayoException;

try {
    $response = $client->registerTransaction($transaction);

    if ($response->isSuccessful()) {
        // Process success
    }
} catch (OpayoValidationException $e) {
    // Invalid data - fix and retry
    error_log("Validation error: " . $e->getMessage());
    // Show user-friendly error message
} catch (OpayoNetworkException $e) {
    // Network issue - retry with exponential backoff
    error_log("Network error: " . $e->getMessage());
    // Queue for retry
} catch (OpayoException $e) {
    // Payment declined or other error
    error_log("Payment error: " . $e->getMessage());
    // Log and notify customer
}
```

---

## Use Case 10: Production Deployment

Production-ready configuration with security best practices.

### Implementation

```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\HttpOptions;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

// Load from secure environment variables
$config = Config::live(
    vendor: $_ENV['OPAYO_VENDOR'],
    encryptionPassword: $_ENV['OPAYO_PASSWORD']
);

// Production logging with rotation
$logger = new OpayoLogger(
    logFile: $_ENV['LOG_PATH'] . '/opayo-' . date('Y-m-d') . '.log',
    minLevel: 'info' // Don't log debug in production
);

// HTTP options with appropriate timeouts
$httpOptions = new HttpOptions(
    timeout: 30,
    connectTimeout: 10,
    verify: true // Always verify SSL in production
);

$httpClient = new HttpClient();
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser,
    null, // Use default validator
    $httpOptions
);
```

---

## Summary

These use cases demonstrate:

- Basic to advanced payment scenarios
- Integration patterns
- Error handling strategies
- Security best practices
- Production deployment
- Testing approaches

For more information, see:
- [Architecture Documentation](ARCHITECTURE.md)
- [Migration Guide](MIGRATION.md)
- [API Reference](../README.md)
