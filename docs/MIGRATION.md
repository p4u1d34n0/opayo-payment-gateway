# Migration Guide - Opayo PHP SDK

This guide helps you upgrade from the old version to the new SOLID-refactored version of the Opayo PHP SDK.

## Table of Contents

1. [What Changed](#what-changed)
2. [Breaking Changes](#breaking-changes)
3. [Step-by-Step Migration](#step-by-step-migration)
4. [Before and After Examples](#before-and-after-examples)
5. [Troubleshooting](#troubleshooting)

---

## What Changed

### Major Changes

1. **NotificationHandler (CRITICAL)**: Now requires `vendorName` parameter - fixes signature verification bug
2. **Client Constructor**: Now requires more dependencies for better testability
3. **Crypto Class**: Changed from static to instance-based with interface
4. **New HTTP Classes**: Added `HttpOptions`, `TransactionRequestBuilder`, `ResponseParser`
5. **TransactionResponse**: Added constants and new `isAccepted()` method
6. **NotificationHandler**: Added helper methods for URL building and `debugSignature()` method

### Why These Changes?

- **Better Testability**: All dependencies can be mocked
- **SOLID Principles**: Each class has a single responsibility
- **Type Safety**: Full PHP 8+ type hints throughout
- **Extensibility**: Easy to add new features without breaking existing code
- **Maintainability**: Clear separation of concerns

---

## Breaking Changes

### CRITICAL: NotificationHandler Constructor (SECURITY FIX)

**⚠️ IMPORTANT**: This change fixes a critical bug where 100% of notification signatures would fail verification.

**Old:**
```php
$handler = new NotificationHandler(
    $encryptionPassword,
    $logger,
    $baseURL
);
```

**New:**
```php
$handler = new NotificationHandler(
    $encryptionPassword,
    $logger,
    $baseURL,
    $vendorName  // NEW REQUIRED PARAMETER
);
```

**What was fixed:**
- ✅ Signature now uses all 21 fields (was using only 15)
- ✅ VendorName now included in signature (was missing)
- ✅ SecurityKey now embedded in signature (was only appended)
- ✅ URL decoding implemented (was missing)
- ✅ Correct field order per Opayo Server Protocol 3.00

**Migration:**
```php
// Before
$handler = new NotificationHandler(
    $_ENV['OPAYO_PASSWORD'],
    $logger,
    'https://yourdomain.com'
);

// After
$handler = new NotificationHandler(
    $_ENV['OPAYO_PASSWORD'],
    $logger,
    'https://yourdomain.com',
    $_ENV['OPAYO_VENDOR']  // Add your vendor name
);
```

**Testing:** Use the debug helper to verify signatures work:
```php
$debug = $handler->debugSignature($_POST, $securityKey);
if (!$debug['match']) {
    error_log('Signature mismatch details: ' . print_r($debug, true));
}
```

---

### 1. Client Constructor

**Old:**
```php
$client = new Client($config, $logger, $httpClient);
```

**New:**
```php
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser,
    $validator,  // optional
    $httpOptions // optional
);
```

### 2. Crypto Class

**Old:**
```php
use Opayo\Crypto;

$encrypted = Crypto::encrypt($data, $key);
$decrypted = Crypto::decrypt($encrypted, $key);
```

**New:**
```php
use Opayo\Crypto\OpayoCrypto;

$crypto = new OpayoCrypto();
$encrypted = $crypto->encrypt($data, $key);
$decrypted = $crypto->decrypt($encrypted, $key);
```

### 3. TransactionResponse

**Old:**
```php
if (!$response->isSuccessful() && !$response->requires3DSecure()) {
    throw new Exception('Transaction failed');
}
```

**New:**
```php
if (!$response->isAccepted()) {
    throw new Exception('Transaction failed');
}

// Or use constants
if ($response->getStatus() === TransactionResponse::STATUS_OK) {
    // Success
}
```

---

## Step-by-Step Migration

### Step 1: Update Dependencies in composer.json

No changes needed - the package version handles this automatically.

### Step 2: Update Your Client Initialization

Find all places where you create a `Client` instance and update them.

**Before:**
```php
$config = Config::sandbox('vendor', 'password');
$logger = new OpayoLogger('/var/log/opayo.log');
$httpClient = new GuzzleHttp\Client();

$client = new Client($config, $logger, $httpClient);
```

**After:**
```php
$config = Config::sandbox('vendor', 'password');
$logger = new OpayoLogger('/var/log/opayo.log');
$httpClient = new GuzzleHttp\Client();

// Add new dependencies
$crypto = new Opayo\Crypto\OpayoCrypto();
$requestBuilder = new Opayo\Http\TransactionRequestBuilder($crypto, $config);
$responseParser = new Opayo\Http\ResponseParser();

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser
);
```

### Step 3: Update Crypto Usage

Find all direct uses of `Crypto::` and convert to instance-based:

**Before:**
```php
$encrypted = Crypto::encrypt($data, $key);
```

**After:**
```php
$crypto = new OpayoCrypto();
$encrypted = $crypto->encrypt($data, $key);
```

### Step 4: Update Response Checking

Replace double-negative checks with `isAccepted()`:

**Before:**
```php
if (!$response->isSuccessful() && !$response->requires3DSecure()) {
    // Handle failure
}
```

**After:**
```php
if (!$response->isAccepted()) {
    // Handle failure
}
```

### Step 5: Update Tests

If you have tests that mock the Client, update them:

**Before:**
```php
$client = new Client($config, $logger, $httpClient);
```

**After:**
```php
$requestBuilder = $this->createMock(TransactionRequestBuilder::class);
$responseParser = $this->createMock(ResponseParser::class);

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser
);
```

---

## Before and After Examples

### Example 1: Basic Payment Processing

**Before:**
```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

$config = Config::sandbox('MyVendor', 'MyPassword123456');
$logger = new OpayoLogger('/var/log/opayo.log');
$httpClient = new HttpClient();

$client = new Client($config, $logger, $httpClient);

$transaction = [
    'Amount' => '99.99',
    'Currency' => 'GBP',
    'Description' => 'Test transaction',
];

try {
    $response = $client->registerTransaction($transaction);

    if ($response->isSuccessful()) {
        echo "Success: " . $response->getVPSTxId();
    } elseif ($response->requires3DSecure()) {
        header('Location: ' . $response->getNextURL());
    } else {
        echo "Failed: " . $response->getStatusDetail();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

**After:**
```php
<?php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

$config = Config::sandbox('MyVendor', 'MyPassword123456');
$logger = new OpayoLogger('/var/log/opayo.log');
$httpClient = new HttpClient();

// New dependencies
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
    'Amount' => '99.99',
    'Currency' => 'GBP',
    'Description' => 'Test transaction',
];

try {
    $response = $client->registerTransaction($transaction);

    if ($response->isSuccessful()) {
        echo "Success: " . $response->getVPSTxId();
    } elseif ($response->requires3DSecure()) {
        header('Location: ' . $response->getNextURL());
    } else {
        echo "Failed: " . $response->getStatusDetail();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Example 2: Custom Encryption (New Capability)

**Before:**
Not possible - encryption was hardcoded.

**After:**
```php
// Create custom crypto implementation
class CustomCrypto implements CryptoInterface
{
    public function encrypt(string $data, string $key): string
    {
        // Your custom encryption
    }

    public function decrypt(string $crypt, string $key): string
    {
        // Your custom decryption
    }
}

// Use it
$crypto = new CustomCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser
);
```

### Example 3: Using HttpOptions

**Before:**
HTTP options were hardcoded in Client.

**After:**
```php
use Opayo\Http\HttpOptions;

// Custom HTTP options
$httpOptions = new HttpOptions(
    timeout: 60,
    connectTimeout: 20,
    verify: true
);

$client = new Client(
    $config,
    $logger,
    $httpClient,
    $requestBuilder,
    $responseParser,
    null, // use default validator
    $httpOptions
);
```

### Example 4: With Dependency Injection Container

**Before:**
Manual instantiation everywhere.

**After:**
```php
// config/services.php
use DI\ContainerBuilder;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Crypto\CryptoInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface as HttpClientInterface;

return (new ContainerBuilder())
    ->addDefinitions([
        // Bindings
        CryptoInterface::class => fn() => new OpayoCrypto(),
        LoggerInterface::class => fn() => new OpayoLogger('/var/log/opayo.log'),
        HttpClientInterface::class => fn() => new GuzzleHttp\Client(),

        // Config
        Config::class => fn() => Config::fromEnvironment(),

        // Client is automatically resolved
        Client::class => fn($container) => new Client(
            $container->get(Config::class),
            $container->get(LoggerInterface::class),
            $container->get(HttpClientInterface::class),
            $container->get(TransactionRequestBuilder::class),
            $container->get(ResponseParser::class)
        ),
    ])
    ->build();

// Usage
$container = require 'config/services.php';
$client = $container->get(Client::class);
```

---

## Helper Script for Migration

Create a helper function to simplify client creation:

```php
<?php
// helpers/opayo.php

use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Http\ResponseParser;
use Opayo\Http\HttpOptions;
use Opayo\Logger\OpayoLogger;
use GuzzleHttp\Client as HttpClient;

function createOpayoClient(
    Config $config,
    ?LoggerInterface $logger = null,
    ?HttpOptions $httpOptions = null
): Client {
    $logger = $logger ?? new OpayoLogger('/var/log/opayo.log');
    $httpClient = new HttpClient();
    $crypto = new OpayoCrypto();
    $requestBuilder = new TransactionRequestBuilder($crypto, $config);
    $responseParser = new ResponseParser();

    return new Client(
        $config,
        $logger,
        $httpClient,
        $requestBuilder,
        $responseParser,
        null,
        $httpOptions
    );
}

// Usage:
$config = Config::sandbox('vendor', 'password');
$client = createOpayoClient($config);
```

---

## Troubleshooting

### Error: "Too few arguments to function Client::__construct()"

**Problem:** Old code using 3-parameter constructor

**Solution:** Add the missing dependencies:
```php
$crypto = new OpayoCrypto();
$requestBuilder = new TransactionRequestBuilder($crypto, $config);
$responseParser = new ResponseParser();

$client = new Client($config, $logger, $httpClient, $requestBuilder, $responseParser);
```

### Error: "Class 'Opayo\Crypto' not found"

**Problem:** Using old static Crypto class

**Solution:** Change to instance-based:
```php
use Opayo\Crypto\OpayoCrypto;

$crypto = new OpayoCrypto();
$encrypted = $crypto->encrypt($data, $key);
```

### Error: "Call to undefined method isAccepted()"

**Problem:** Using outdated TransactionResponse

**Solution:** Update your SDK to the latest version, the method is available in the new version.

### Tests Failing After Upgrade

**Problem:** Test mocks don't match new constructor

**Solution:** Update test setup:
```php
protected function setUp(): void
{
    $this->requestBuilder = $this->createMock(TransactionRequestBuilder::class);
    $this->responseParser = $this->createMock(ResponseParser::class);

    $this->client = new Client(
        $this->config,
        $this->logger,
        $this->httpClient,
        $this->requestBuilder,
        $this->responseParser
    );
}
```

---

## Migration Checklist

- [ ] Review breaking changes list
- [ ] Update all Client instantiations
- [ ] Convert Crypto static calls to instance-based
- [ ] Update TransactionResponse checks to use isAccepted()
- [ ] Update tests and mocks
- [ ] Run test suite to verify
- [ ] Test in staging environment
- [ ] Deploy to production

---

## Getting Help

If you encounter issues during migration:

1. Check this guide for common problems
2. Review the [Architecture Documentation](ARCHITECTURE.md)
3. See [Use Cases](USE_CASES.md) for working examples
4. Check the test files for usage examples
5. Open an issue on GitHub

---

## Benefits After Migration

Once migrated, you'll benefit from:

- **Better Testability**: All dependencies can be mocked
- **Type Safety**: Full PHP 8+ type hints prevent errors
- **Extensibility**: Easy to add custom implementations
- **Maintainability**: Clear code structure
- **Modern PHP**: Leverages latest PHP features
- **SOLID Principles**: Industry best practices applied

The migration effort is worthwhile for long-term codebase health!
