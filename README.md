# Arionpay Crypto Payment Gateway for WHMCS

This WHMCS gateway module lets your clients pay WHMCS invoices using **Arionpay**. It creates an Arionpay invoice via API (HMAC signed), redirects the client to the Arionpay checkout page, and then receives a webhook callback to automatically mark the WHMCS invoice as **Paid**.

## Requirements
- WHMCS 7.x / 8.x (Gateway API v1.1)
- PHP 7.4+ (recommended: 8.0+)
- PHP cURL extension enabled (`php-curl`)
- Arionpay Store API Key + Secret Key

## Installation (Upload Files)
Upload these files into your WHMCS installation exactly as shown:

- Gateway module:
  - `modules/gateways/arionpay.php`

- Callback / Webhook handler:
  - `modules/gateways/callback/arionpay.php`

> Note: The module file name is `paymentdex.php` (internal module name: `paymentdex`). Keep it as-is unless you also rename and update references.

## Activate & Configure in WHMCS
1. Log in to **WHMCS Admin**
2. Go to **System Settings → Payment Gateways**
3. Find and activate **Arionpay Crypto**
4. Configure the gateway fields:
   - **Store API Key (pk_...)**: from your Arionpay Dashboard (Store settings)
   - **Store Secret Key (sk_...)**: from your Arionpay Dashboard (Store settings)
   - **API Base URL**:  
     `https://api.arionpay.com`  
     (no trailing slash)

Save changes.

## Webhook / Callback Setup (Important)
This module listens for payment webhooks at:

`https://YOUR-WHMCS-DOMAIN.com/modules/gateways/callback/arionpay.php`

In your **Arionpay Dashboard → Store/Webhook settings**, set the webhook/callback URL to the address above.

### Webhook Headers (Required)
Arionpay must send these headers with each webhook request:
- `x-api-key: <your pk_...>`
- `x-signature: <hmac_sha256(raw_json_body, sk_...)>`

Where:
- Signature algorithm: `HMAC-SHA256`
- Message: the **raw request body** bytes (exact JSON payload as sent)
- Key: your **Store Secret Key** (`sk_...`)

## How Payment Flow Works
1. Client opens a WHMCS invoice and clicks **Pay Now**
2. WHMCS calls Arionpay API to create an invoice:  
   `POST https://api.arionpay.com/api/v1/invoices`
3. Client is redirected to Arionpay checkout:  
   `https://arionpay.com/pay/{arionpay_invoice_id}`
4. After payment, Arionpay sends a webhook to the WHMCS callback URL
5. WHMCS validates signature and marks the invoice as **Paid**

## Testing Checklist
- Create a test invoice in WHMCS
- Select **Arionpay Crypto** as the payment method
- Click **Pay Now** and confirm it redirects to Arionpay checkout
- Complete payment in Arionpay
- Confirm the WHMCS invoice becomes **Paid**
- Check WHMCS logs:
  - **Billing → Gateway Log / Transaction Log** (depends on WHMCS version)

## Troubleshooting
**Invoice page shows “Payment Gateway Error”**
- Check the error details shown (HTTP code / cURL error / API response)
- Verify:
  - API Base URL is exactly `https://api.arionpay.com`
  - API Key/Secret are correct
  - Server can reach the API (firewall/DNS)
  - Endpoint path exists: `/api/v1/invoices`

**Webhook not updating WHMCS invoice**
- Verify the callback URL is publicly reachable
- Confirm Arionpay sends `x-api-key` + `x-signature`
- Confirm WHMCS secret matches Arionpay Store Secret
- Look for callback logs in WHMCS gateway log (you may see “Missing Signature” / “Invalid Signature”)

## Security Notes
- Keep `sk_...` secret key private.
- Use HTTPS for WHMCS and Arionpay.
- Restrict access to WHMCS admin and keep WHMCS updated.

## License
MIT (or choose your preferred license)
