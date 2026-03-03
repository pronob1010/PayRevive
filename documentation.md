# PayRevive: WooCommerce Smart Payment Recovery + WhatsApp Reminder

PayRevive is a powerful, modern, and professional WooCommerce extension designed to recover failed payments automatically. By combining smart retry logic with multi-channel notifications (Email & WhatsApp), PayRevive helps you reclaim lost revenue and improve customer retention.

---

## Table of Contents
1. [Installation](#1-installation)
2. [Quick Start Guide](#2-quick-start-guide)
3. [Key Features](#3-key-features)
    - [Smart Retry Logic](#smart-retry-logic)
    - [Multi-Channel Notifications](#multi-channel-notifications)
    - [Advanced Analytics Dashboard](#advanced-analytics-dashboard)
4. [Settings Configuration](#4-settings-configuration)
    - [Retry Logic](#retry-logic-settings)
    - [Email Templates](#email-template-settings)
    - [WhatsApp Integration](#whatsapp-settings)
5. [Developer Hooks & Customization](#5-developer-hooks--customization)
6. [FAQ & Troubleshooting](#6-faq--troubleshooting)

---

## 1. Installation

1.  **Upload the Plugin**: Download the `payrevive` folder and upload it to your WordPress installation's `/wp-content/plugins/` directory, or upload the ZIP via **Plugins > Add New**.
2.  **Activate**: Navigate to the **Plugins** menu in your WordPress admin and click **Activate** on PayRevive.
3.  **Requirements**: PayRevive requires **WooCommerce** (v5.0+) to be installed and active. It is fully compatible with **High-Performance Order Storage (HPOS)**.

---

## 2. Quick Start Guide

Once activated, you can access PayRevive via the new **PayRevive** menu in your WordPress sidebar.

1.  **Check the Dashboard**: View your recovery performance at a glance.
2.  **Configure Settings**: Go to **PayRevive > Settings** to set your recovery rules.
3.  **Enable Notifications**: Ensure "Enable Email Reminders" is checked in the Email Template tab.
4.  **Automated Recovery**: PayRevive will now automatically detect failed payments and schedule recovery attempts based on your settings.

---

## 3. Key Features

### Smart Retry Logic
PayRevive doesn't just retry; it retries *intelligently*. 
- **Configurable Attempts**: Set between 1 and 5 retry attempts.
- **Base Intervals**: Choose the delay (in hours) before the first recovery attempt.
- **Smart Backoff**: When enabled, the interval between retries increases exponentially (e.g., 24h, 48h, 96h). This avoids overwhelming your customers while keeping the recovery process active.

### Multi-Channel Notifications
Reach your customers where they are most active.
- **Email Reminders**: Professional, template-based emails sent automatically.
- **WhatsApp Reminders**: Integrated support for WhatsApp Cloud API/Twilio to send direct recovery links to customers' phones.
- **Dynamic Tags**: Use tags like `{customer_name}`, `{order_number}`, and `{checkout_url}` to personalize your messages.

### Advanced Analytics Dashboard
A modern, Tailwind-powered dashboard provides actionable insights:
- **Core Metrics**: Total Failed, Recovered, Recovery Rate (%), and Revenue Saved.
- **Trend Charts**: 7-day visualization of recovery performance.
- **Recent Activity**: A live feed of recent recovery events and order statuses.
- **Data Export**: Download your recovery history as a CSV for external reporting.

---

## 4. Settings Configuration

### Retry Logic Settings
- **Retry Attempts**: The total number of times the system will attempt to recover a payment.
- **Base Interval**: The initial delay before the first recovery action.
- **Enable Smart Backoff**: Recommended. Increases the delay between subsequent retries.
- **Backoff Multiplier**: The factor by which the interval increases (e.g., a multiplier of 2 doubles the delay each time).

### Email Template Settings
- **Enable Email Reminders**: Toggle the automated email system.
- **Email Subject**: Customize the subject line. Supports dynamic tags.
- **Email Body**: The content of your recovery email. Be sure to include the `{checkout_url}` tag so customers can pay with one click.

### WhatsApp Settings
- **Enable WhatsApp Reminders**: Toggle WhatsApp notifications.
- **API Key**: Enter your WhatsApp Cloud API or Twilio credentials.
- **Message Template**: The message sent via WhatsApp. Keep it concise and include the `{checkout_url}`.

---

## 5. Developer Hooks & Customization

PayRevive is built to be extensible. Developers can hook into the recovery process:

- **Action**: `payrevive_retry_payment` - Triggered when a retry attempt is initiated.
- **Filter**: `payrevive_settings` - Modify settings programmatically.
- **Order Meta**: 
    - `_payrevive_retry_count`: Current number of retries performed.
    - `_payrevive_retry_scheduled`: Status of the recovery process (`yes`, `no`, or `recovered`).

---

## 6. FAQ & Troubleshooting

**Q: Does PayRevive work with all payment gateways?**
A: Yes. PayRevive hooks into WooCommerce's core status changes. While it cannot "force" a charge on a failed card (unless handled by the gateway/subscriptions), it provides the customer with a direct link to complete the payment using any available method.

**Q: Why are WhatsApp messages not being sent?**
A: Ensure you have entered a valid API Key in the settings and that the customer has provided a valid billing phone number in international format.

**Q: Is the plugin compatible with WooCommerce Subscriptions?**
A: Yes! PayRevive includes specific logic to detect subscription renewals and log them appropriately in the recovery process.

**Q: Where can I see the logs for a specific order?**
A: Check the **Order Notes** section within a specific WooCommerce Order. PayRevive logs every step of the recovery process there.

---
*Developed with ❤️ for WooCommerce Store Owners.*
