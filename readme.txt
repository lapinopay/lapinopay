=== LapinoPay ===
Contributors: lapinopay
Tags: payment gateway, cryptocurrency, instant payment, woocommerce
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.2
WC requires at least: 5.8
WC tested up to: 9.7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept instant USD/EUR payments with USDC conversion. Support for credit cards, Apple Pay, Google Pay, and Revolut with instant payouts.

== Description ==

LapinoPay is a powerful WooCommerce payment gateway that revolutionizes how businesses handle payments by combining traditional payment methods with instant USDC payouts. Perfect for businesses seeking efficient, secure, and instant payment processing.

= Key Features =

* **Instant USDC Payouts**: Receive payments directly to your USDC wallet instantly after each successful transaction
* **Multi-Currency Support**: Accept payments in USD and EUR
* **Multiple Payment Methods**:
  * Credit/Debit Cards (Visa, Mastercard)
  * Apple Pay
  * Google Pay
  * Revolut Pay
  * Bank Transfers
* **Zero KYC Required**: Start accepting payments immediately without lengthy verification processes
* **Automatic Currency Conversion**: Seamless conversion from fiat to USDC
* **Real-Time Transaction Monitoring**: Track all your transactions in real-time
* **Secure Processing**: Enterprise-grade security with encrypted transactions
* **Automated Order Management**: Automatic order status updates and payment verification

= Benefits =

* **Instant Access**: Start accepting payments immediately after installation
* **No Chargebacks**: All payments are final and irreversible
* **Low Minimum Order**: Accept orders starting from $20 USD
* **24/7 Support**: Dedicated customer support team
* **Custom Integration**: Easily customizable to match your store's theme

= Perfect For =

* E-commerce stores
* Digital product sellers
* Service providers
* High-risk merchants
* International businesses
* Cryptocurrency enthusiasts

= Security Features =

* End-to-end encryption
* Secure payment processing
* PCI DSS compliant
* Anti-fraud protection
* Secure USDC wallet integration
* SSL/TLS encryption for all transactions
* No storage of sensitive card data
* Regular security audits

== Development ==

* Source Code: https://github.com/lapinopay/plugin
* Bug Reports: https://github.com/lapinopay/plugin/issues
* Development Documentation: https://lapinopay.com/docs/developers
* Build Process: https://lapinopay.com/docs/build

== Privacy and Security ==

This plugin processes payment information and handles sensitive data. Here's what you need to know:

= Data Collection =
* Transaction data (amount, currency, timestamp)
* Order information
* Customer billing details
* Payment method selection
* No credit card information is stored

= Data Processing =
* All data is encrypted during transmission
* Compliant with PCI DSS requirements
* Follows GDPR and CCPA guidelines
* Uses secure API endpoints
* Implements rate limiting and fraud prevention

= Third-Party Services =
* Payment processing through secure providers
* USDC conversion services
* Blockchain transaction services

For complete details about data handling and privacy practices, please review our [Privacy Policy](https://lapinopay.com/privacy).

== Compliance ==

This plugin:
* Is GPL-compatible (GPLv3)
* Uses WordPress coding standards
* Follows WooCommerce best practices
* Implements secure coding practices
* Maintains PCI DSS compliance
* Adheres to cryptocurrency regulations

== Technical Details ==

= Build Tools =
* Node.js for frontend assets
* Composer for PHP dependencies
* Webpack for asset bundling
* PHP Unit for testing

= Development Requirements =
* PHP 7.2+
* Node.js 14+
* Composer
* WordPress 5.8+
* WooCommerce 5.8+

= Contributing =
We welcome contributions! Please see our [Contributing Guidelines](https://github.com/lapinopay/plugin/CONTRIBUTING.md) for details.

== Installation ==

1. **Automatic Installation**:
   * Go to WordPress Admin > Plugins > Add New
   * Search for "LapinoPay Payment Gateway"
   * Click "Install Now" followed by "Activate"

2. **Manual Installation**:
   * Download the plugin ZIP file
   * Go to WordPress Admin > Plugins > Add New > Upload Plugin
   * Upload the ZIP file and click "Install Now"
   * Activate the plugin

3. **WordPress Settings**:
   * Go to WordPress Admin > Settings > Permalinks
   * Select "Post name" as your permalink structure
   * Save Changes

4. **Configuration**:
   * First, get your public API token from https://lapinopay.com/integration
   * Go to WooCommerce > Settings > Payments
   * Find "LapinoPay Payment Gateway" and click "Manage"
   * Enter your Public Token from the integration page
   * Save changes

5. **Integration Verification**:
   * After saving your settings, the plugin will automatically verify your API token
   * A success message will appear if the integration is correct
   * If you see any errors, double-check your API token from the integration page

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.8 or higher
* PHP 7.2 or higher
* SSL certificate installed
* WordPress Permalink structure set to "Post name"
* Active USDC wallet
* Valid LapinoPay API token

== Frequently Asked Questions ==

= How quickly do I receive my USDC payouts? =
Payouts are processed instantly after successful payment confirmation.

= Which currencies are supported? =
Currently, we support USD and EUR with automatic conversion to USDC.

= Is there a minimum transaction amount? =
Minimum amounts vary by payment method, starting from $1 USD.

= Do I need KYC verification? =
No, you can start accepting payments immediately without KYC verification.

= How secure are the transactions? =
We use enterprise-grade encryption and security measures to protect all transactions.

= Can I customize the payment interface? =
Yes, the payment interface can be customized to match your store's theme.

= What payment methods are supported? =
* Visa/Mastercard
* Apple Pay
* Google Pay
* Revolut Pay

= How do refunds work? =
All transactions are final. We recommend having a clear refund policy in place.

== Screenshots ==

1. Payment Gateway Dashboard
2. Configuration Settings
3. Payment Method Selection
4. Transaction History
5. Payout Management
6. Customer Payment Interface

== Changelog ==

= 1.1.8 =
* Added EUR/USD currency support
* Improved minimum amount validation
* Enhanced payment method detection
* Performance optimizations

[Previous versions changelog available on our website]

== Upgrade Notice ==

= 1.1.8 =
Important update: Added EUR support and improved payment processing. Please update to ensure continued smooth operation.

== Support ==

For support inquiries:
* Visit our [Support Center](https://lapinopay.com/support)
* Email: support@lapinopay.com
* Documentation: [Developer Docs](https://lapinopay.com/docs)
* GitHub Issues: [Report Bugs](https://github.com/lapinopay/plugin/issues)
* Security Reports: security@lapinopay.com

== Legal ==

* Terms of Service: https://lapinopay.com/terms
* Privacy Policy: https://lapinopay.com/privacy
* Cookie Policy: https://lapinopay.com/cookies
* GDPR Compliance: https://lapinopay.com/gdpr

This plugin is not affiliated with or endorsed by WordPress, WooCommerce, or any payment method providers (Visa, Mastercard, Apple Pay, Google Pay, Revolut).