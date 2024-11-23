# Keevault License Manager - WooCommerce Integration

This is a WordPress plugin designed to integrate Keevault License Manager with WooCommerce. It automatically assigns license keys on Keevault when WooCommerce products are purchased, allowing for license management directly from WooCommerce.

## Requirements

- [Keevault - Software License Manager and Telemetry Data Collection](https://codecanyon.net/item/keevault-license-manager-and-telemetry-data-collection/51172292) (Available on CodeCanyon)


## Features

- **API Integration**: Seamlessly integrates Keevault with WooCommerce using Keevault API keys and URL configuration.
- **Product-Specific Settings**: Customize product settings like Keevault Product ID for each product and/or variation.
- **Order Processing**: Automatically assign Keevault license keys for products purchased in WooCommerce.
- **My Account Endpoint**: Adds a new 'License Keys' section in the WooCommerce "My Account" page where customers can view their license keys.
- **Database Integration**: Contracts are stored in a custom database table and linked to WooCommerce orders.

## Installation

1. **Upload Plugin**: Upload the plugin folder to the `/wp-content/plugins/` directory.
2. **Activate the Plugin**: Go to the WordPress admin dashboard, navigate to **Plugins** > **Installed Plugins**, and activate the **Keevault License Manager - WooCommerce Integration** plugin.
3. **Configure API Settings**: After activation, go to **Keevault** in the WordPress admin menu to enter your Keevault API Key and URL.
4. **Configure Product Settings**: Edit your WooCommerce products to define Keevault-specific settings like the Keevault Product ID.

## Configuration

1. **Keevault API Configuration**:
    - **API Key**: Enter your Keevault API key.
    - **API URL**: Enter the Keevault API URL.

2. **Product Settings**:
    - **Keevault Product ID**: Define the Keevault Product ID for each product.

3. **Variation Settings**:
    - Product variations also support Keevault settings. Configure these in the product variations section.

4. **My Account Endpoint**: A new endpoint 'License Keys' will appear in the WooCommerce **My Account** page for customers to view their contract details.

## Usage

1. **Assigning License Keys**:
    - When a customer makes a purchase, the plugin will automatically assign already existing license keys or generate new ones on Keevault based on the product's configuration.
    - The assigned license keys will be associated with the WooCommerce order.

2. **Viewing License Keys**:
    - Customers can view their license keys from the **My Account** page under the **License Keys** tab.
    - The license keys list will display relevant details such as The License Key, Order ID, Status, Activation Limit, and Created At.

3. **Customizing Orders**:
    - The plugin supports both simple products and variations, allowing for the specification of different license keys parameters for each variation.


## Activating the Plugin

When the plugin is activated, it will:

- Create a custom database table for storing license keys information.
- Flush WordPress rewrite rules to ensure the new license keys endpoint is registered.
