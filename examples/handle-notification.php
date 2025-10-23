<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Opayo\NotificationHandler;
use Opayo\Logger\OpayoLogger;

// Create logger
$logger = new OpayoLogger('/var/log/opayo.log');

// Your base URL
$baseURL = 'https://yourdomain.com';

// Get your encryption password and vendor name from environment or config
$encryptionPassword = 'your_encryption_password';
$vendorName = 'your_vendor_name'; // Your Opayo vendor name (required for signature verification)

// Create notification handler
// IMPORTANT: Vendor name is required for correct signature verification
$handler = new NotificationHandler($encryptionPassword, $logger, $baseURL, $vendorName);

// SECURITY WARNING: Always validate that requests come from Opayo's IP addresses
// Opayo notification servers use specific IP ranges - configure IP whitelisting
// at your firewall/web server level or check here:
//
// Example IP check (replace with actual Opayo IPs):
// $opayoIPs = ['195.170.169.0/24', '46.229.226.0/24'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $opayoIPs)) {
//     http_response_code(403);
//     exit('Forbidden');
// }

// Get notification data from Opayo (typically from $_POST)
$notificationData = $_POST; // Opayo sends data via POST

// Define callback to get security key for a transaction
$getKey = function (string $vendorTxCode): string {
    // Retrieve security key from your database/session
    // This was stored when you registered the transaction

    // Example using session:
    // return $_SESSION['tx_' . $vendorTxCode] ?? '';

    // Example using database:
    // $pdo = new PDO(...);
    // $stmt = $pdo->prepare('SELECT security_key FROM transactions WHERE vendor_tx_code = ?');
    // $stmt->execute([$vendorTxCode]);
    // return $stmt->fetchColumn();

    return 'example_security_key'; // Replace with actual retrieval
};

// Define callback to check if transaction was already processed
$checkProcessed = function (string $vpsTxId): bool {
    // Check your database if this VPSTxId was already processed

    // Example:
    // $pdo = new PDO(...);
    // $stmt = $pdo->prepare('SELECT COUNT(*) FROM processed_transactions WHERE vps_tx_id = ?');
    // $stmt->execute([$vpsTxId]);
    // return $stmt->fetchColumn() > 0;

    return false; // Replace with actual check
};

// Define callback to get redirect URL
$getRedirectURL = function (string $vendorTxCode): string {
    // Return the path (relative to baseURL) where customer should be redirected
    return '/payment/success?order=' . urlencode($vendorTxCode);
};

// Define callback for successful payment
$onSuccess = function (string $vendorTxCode, array $data): void {
    // Update your database: mark order as paid
    // Send confirmation email
    // Release goods/services

    // Example:
    // $pdo = new PDO(...);
    // $pdo->prepare('UPDATE orders SET status = ? WHERE vendor_tx_code = ?')
    //     ->execute(['paid', $vendorTxCode]);

    error_log("Payment successful for: $vendorTxCode");
};

// Define callback for failed payment
$onFailure = function (string $vendorTxCode, array $data): void {
    // Update your database: mark order as failed
    // Send failure notification

    // Example:
    // $pdo = new PDO(...);
    // $pdo->prepare('UPDATE orders SET status = ? WHERE vendor_tx_code = ?')
    //     ->execute(['failed', $vendorTxCode]);

    error_log("Payment failed for: $vendorTxCode - " . ($data['StatusDetail'] ?? 'Unknown'));
};

// Define callback for repeat notification
$onRepeat = function (string $vendorTxCode): void {
    // Log the duplicate notification
    error_log("Duplicate notification for: $vendorTxCode");
};

// Handle the notification
$response = $handler->handle(
    $notificationData,
    $getKey,
    $checkProcessed,
    $getRedirectURL,
    $onSuccess,
    $onFailure,
    $onRepeat
);

// Send response back to Opayo
$response->send(); // This will output the response and exit
