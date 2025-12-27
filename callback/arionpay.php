<?php
/**
 * Arionpay Callback File for WHMCS
 *
 * Handles Webhook/IPN from Arionpay API.
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// 1. Detect Module Name
$gatewayModuleName = 'ArionPay';

// 2. Fetch Gateway Configuration
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module not active
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// 3. Get Raw POST Data
$rawBody = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_LOWER);

// 4. Verify HMAC Signature
$signature = isset($headers['x-signature']) ? $headers['x-signature'] : '';
$apiKey = isset($headers['x-api-key']) ? $headers['x-api-key'] : '';

if (!$signature || !$apiKey) {
    // Log for debugging
    logTransaction($gatewayParams['name'], $rawBody, 'Missing Signature');
    http_response_code(401);
    die('Missing Signature');
}

// Calculate expected signature using the Secret from WHMCS Config
$expectedSignature = hash_hmac('sha256', $rawBody, $gatewayParams['apiSecret']);

if (!hash_equals($expectedSignature, $signature)) {
    logTransaction($gatewayParams['name'], $rawBody, 'Invalid Signature');
    http_response_code(401);
    die('Invalid Signature');
}

// 5. Decode JSON
$data = json_decode($rawBody, true);
$invoiceId = $data['orderId'];      // WHMCS Invoice ID passed during creation
$transactionId = $data['_id'];      // Arionpay Invoice ID
$amountPaid = $data['amountFiat'];  // Amount paid in Fiat
$status = $data['status'];          // 'paid', 'confirmed', 'paid_partial'

// 6. Validate Invoice ID
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

// 7. Check for Duplicate Transaction
checkCbTransID($transactionId);

// 8. Process Payment
if ($status === 'paid' || $status === 'confirmed') {
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $amountPaid,
        0,
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], $data, 'Successful');
    echo 'OK';
} else {
    logTransaction($gatewayParams['name'], $data, 'Status: ' . $status);
    echo 'Status not paid';
}
