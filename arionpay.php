<?php
/**
 * Arionpay Gateway Module for WHMCS
 *
 * This module connects WHMCS to the Arionpay API using HMAC Authentication.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module metadata.
 */
function paymentdex_MetaData()
{
    return array(
        'DisplayName' => 'Arionpay Crypto',
        'APIVersion' => '1.1', // Use 1.1 for modern WHMCS versions
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define configuration fields for WHMCS Admin.
 */
function paymentdex_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Arionpay (Crypto)',
        ),
        'apiKey' => array(
            'FriendlyName' => 'Store API Key (pk_...)',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Found in your Arionpay Dashboard > Stores',
        ),
        'apiSecret' => array(
            'FriendlyName' => 'Store Secret Key (sk_...)',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Keep this secret!',
        ),
        'baseUrl' => array(
            'FriendlyName' => 'API Base URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://api.arionpay.com',
            'Description' => 'No trailing slash (e.g. https://api.arionpay.com)',
        ),
    );
}

/**
 * Generate the "Pay Now" link.
 * This function sends a request to Arionpay to create an invoice
 * and redirects the user to the Payment Page.
 */
function paymentdex_link($params)
{
    // 1. Get Configuration
    $apiKey = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $baseUrl = rtrim($params['baseUrl'], '/');

    // 2. Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];

    // System URLs
    $systemUrl = rtrim($params['systemurl'], '/');
    $returnUrl = $params['returnurl']; // Page to return to after payment
    $callbackUrl = $systemUrl . '/modules/gateways/callback/arionpay.php';

    // 3. Prepare Payload
    $payload = array(
        'amount' => $amount,
        'currency' => $currency,
        'orderId' => (string)$invoiceId,
        'chain' => 'BTC', // Default chain
        'returnUrl' => $returnUrl,
        'callbackUrl' => $callbackUrl,
        'metadata' => array(
            'source' => 'whmcs',
            'clientId' => $params['clientdetails']['id'],
            'invoiceId' => $invoiceId
        )
    );

    $jsonPayload = json_encode($payload);

    // 4. Generate HMAC Signature
    // Signature = HMAC_SHA256(JSON_BODY, SECRET_KEY)
    $signature = hash_hmac('sha256', $jsonPayload, $apiSecret);

    // 5. Send Request to Arionpay API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/invoices');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'x-signature: ' . $signature
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 6. Handle Response (DEBUG MODE)
    if ($curlError || $httpCode !== 200) {
        $debugMsg = "HTTP Code: " . $httpCode . "<br>";
        $debugMsg .= "CURL Error: " . ($curlError ?: 'None') . "<br>";
        $debugMsg .= "Response: " . htmlspecialchars($response);

        return '<div class="alert alert-danger"><strong>Payment Gateway Error:</strong><br>' . $debugMsg . '</div>';
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return '<div class="alert alert-danger">Payment Error: ' . htmlspecialchars($data['error']) . '</div>';
    }

    // Assuming your API returns { "invoice": { "_id": "...", ... } } or just the invoice object
    $invData = isset($data['invoice']) ? $data['invoice'] : $data;

    // ✅ Arionpay Checkout URL (force to main site domain)
    $paymentUrl = 'https://arionpay.com/pay/' . $invData['_id'];

    // 7. Return HTML Form (Redirect Button)
    $html = '
    <form action="' . htmlspecialchars($paymentUrl) . '" method="GET">
        <input type="submit" class="btn btn-primary" value="Pay with Crypto (Arionpay)" />
    </form>
    ';

    return $html;
}
