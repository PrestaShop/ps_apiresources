<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

/**
 * Helper class to create test data fixtures for Order API tests.
 * Ensures that required PrestaShop entities exist in the test environment.
 */
class TestDataBuilder
{
    private static array $createdData = [];

    /**
     * Ensure a payment method exists in the test environment.
     */
    public static function ensurePaymentMethodExists(string $moduleName = 'ps_wirepayment'): bool
    {
        if (isset(self::$createdData['payment_method'][$moduleName])) {
            return self::$createdData['payment_method'][$moduleName];
        }

        $success = false;

        try {
            // Use PrestaShop Module class which is more reliable
            if (!\Module::isInstalled($moduleName)) {
                $module = \Module::getInstanceByName($moduleName);
                if ($module) {
                    $success = $module->install();
                }
            } else {
                $success = true;
            }

            if ($success && !\Module::isEnabled($moduleName)) {
                $module = \Module::getInstanceByName($moduleName);
                if ($module && method_exists($module, 'enable')) {
                    $success = $module->enable();
                }
            }

            self::$createdData['payment_method'][$moduleName] = $success;
        } catch (\Exception $e) {
            // If we can't create the payment method, at least log it
            error_log('TestDataBuilder: Could not ensure payment method ' . $moduleName . ': ' . $e->getMessage());
            self::$createdData['payment_method'][$moduleName] = false;
        }

        return self::$createdData['payment_method'][$moduleName];
    }

    /**
     * Ensure a carrier exists and return its ID.
     */
    public static function ensureCarrierExists(): int
    {
        if (isset(self::$createdData['carrier'])) {
            return self::$createdData['carrier'];
        }

        try {
            // Use PrestaShop Carrier class
            $carriers = \Carrier::getCarriers(1, true, false, false, null, \Carrier::PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

            if (!empty($carriers)) {
                $carrierId = (int) $carriers[0]['id_carrier'];
            } else {
                // Try to get any carrier
                $carrierId = 1; // Default carrier ID - should exist in most PrestaShop installations
            }

            self::$createdData['carrier'] = $carrierId;

            return $carrierId;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure carrier exists: ' . $e->getMessage());

            return 1; // Fallback carrier ID
        }
    }

    /**
     * Ensure an order state exists.
     */
    public static function ensureOrderStateExists(int $stateId = 2): void
    {
        if (isset(self::$createdData['order_state'][$stateId])) {
            return;
        }

        try {
            // Use PrestaShop OrderState class
            $orderState = new \OrderState($stateId);

            if (!\Validate::isLoadedObject($orderState)) {
                // If state doesn't exist, just use a default one that should exist
                $states = \OrderState::getOrderStates(1);
                if (!empty($states)) {
                    $stateId = (int) $states[0]['id_order_state'];
                }
            }

            self::$createdData['order_state'][$stateId] = true;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure order state ' . $stateId . ': ' . $e->getMessage());
        }
    }

    /**
     * Ensure a customer exists and return its ID.
     */
    public static function ensureCustomerExists(): int
    {
        if (isset(self::$createdData['customer'])) {
            return self::$createdData['customer'];
        }

        try {
            // Check if test customer exists using PrestaShop Customer class
            $customers = \Customer::getCustomersByEmail('test@prestashop.com');
            $customerId = !empty($customers) ? (int) $customers[0]['id_customer'] : 0;

            if (!$customerId) {
                // Use PrestaShop Customer class to create test customer
                $customer = new \Customer();
                $customer->id_shop = 1;
                $customer->id_shop_group = 1;
                $customer->id_default_group = 3;
                $customer->id_lang = 1;
                $customer->firstname = 'John';
                $customer->lastname = 'Doe';
                $customer->email = 'test@prestashop.com';
                $customer->passwd = md5('test123');
                $customer->secure_key = md5(uniqid());
                $customer->active = 1;
                $customer->is_guest = 0;
                $customer->deleted = 0;

                if ($customer->add()) {
                    $customerId = (int) $customer->id;
                } else {
                    $customerId = 1; // Fallback
                }
            }

            self::$createdData['customer'] = $customerId;

            return $customerId;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure customer exists: ' . $e->getMessage());

            return 1; // Fallback customer ID
        }
    }

    /**
     * Ensure a product exists and return its ID.
     */
    public static function ensureProductExists(): int
    {
        if (isset(self::$createdData['product'])) {
            return self::$createdData['product'];
        }

        try {
            // Check if test product exists using PrestaShop search by reference
            $productId = (int) \Product::getIdByReference('TEST-PRODUCT');

            if (!$productId) {
                // Use PrestaShop Product class to create test product
                $product = new \Product();
                $product->name = ['Test Product'];
                $product->description = ['Test product description'];
                $product->description_short = ['Test product'];
                $product->link_rewrite = ['test-product'];
                $product->reference = 'TEST-PRODUCT';
                $product->price = 10.00;
                $product->wholesale_price = 5.00;
                $product->quantity = 100;
                $product->minimal_quantity = 1;
                $product->id_category_default = 2;
                $product->id_shop_default = 1;
                $product->active = 1;
                $product->available_for_order = 1;
                $product->show_price = 1;
                $product->visibility = 'both';
                $product->condition = 'new';
                $product->out_of_stock = 2;

                if ($product->add()) {
                    $productId = (int) $product->id;
                } else {
                    $productId = 1; // Fallback
                }
            }

            self::$createdData['product'] = $productId;

            return $productId;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure product exists: ' . $e->getMessage());

            return 1; // Fallback product ID
        }
    }

    /**
     * Ensure an address exists and return its ID.
     */
    public static function ensureAddressExists(?int $customerId = null): int
    {
        $customerId = $customerId ?: self::ensureCustomerExists();

        if (isset(self::$createdData['address'][$customerId])) {
            return self::$createdData['address'][$customerId];
        }

        try {
            // Check if address exists for customer using PrestaShop Address class
            $addresses = \Address::getAddresses(1, $customerId);
            $addressId = !empty($addresses) ? (int) $addresses[0]['id_address'] : 0;

            if (!$addressId) {
                // Use PrestaShop Address class to create test address
                $address = new \Address();
                $address->id_customer = $customerId;
                $address->id_country = 8; // France
                $address->id_state = 0;
                $address->alias = 'Home';
                $address->firstname = 'John';
                $address->lastname = 'Doe';
                $address->address1 = '123 Test Street';
                $address->postcode = '12345';
                $address->city = 'Test City';
                $address->active = 1;
                $address->deleted = 0;

                if ($address->add()) {
                    $addressId = (int) $address->id;
                } else {
                    $addressId = 1; // Fallback
                }
            }

            self::$createdData['address'][$customerId] = $addressId;

            return $addressId;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure address exists: ' . $e->getMessage());

            return 1; // Fallback address ID
        }
    }

    /**
     * Get a working payment module name from the environment.
     * Ensures the payment method exists and is properly configured for tests.
     */
    public static function getWorkingPaymentMethod(): string
    {
        try {
            // Try common payment modules in order of preference
            $paymentMethods = ['ps_wirepayment', 'ps_checkpayment', 'ps_cashondelivery'];

            foreach ($paymentMethods as $method) {
                // First ensure the module exists
                if (self::ensurePaymentMethodExists($method)) {
                    return $method;
                }
            }

            // If none of the common ones work, try to find any active payment module
            $modules = \Module::getPaymentModules();
            if (!empty($modules)) {
                $firstModule = $modules[0]['name'];
                if (self::ensurePaymentMethodExists($firstModule)) {
                    return $firstModule;
                }
            }

            // Last resort - ensure ps_wirepayment exists as fallback
            self::ensurePaymentMethodExists('ps_wirepayment');

            return 'ps_wirepayment';
        } catch (\Exception $e) {
            // Ensure fallback exists even if there are errors
            self::ensurePaymentMethodExists('ps_wirepayment');

            return 'ps_wirepayment';
        }
    }

    /**
     * Check if a specific command class exists and is available.
     */
    public static function checkCommandClassExists(string $commandClass): bool
    {
        if (isset(self::$createdData['command_class'][$commandClass])) {
            return self::$createdData['command_class'][$commandClass];
        }

        $exists = class_exists($commandClass);
        self::$createdData['command_class'][$commandClass] = $exists;

        return $exists;
    }

    /**
     * Get list of available command classes for Order operations.
     */
    public static function getAvailableOrderCommandClasses(): array
    {
        $commandClasses = [
            'GetOrderForViewing' => \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            'AddOrderFromBackOffice' => \PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand::class,
            'UpdateOrderStatus' => \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand::class,
            'UpdateOrderShippingDetails' => \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderShippingDetailsCommand::class,
            'CancelOrderProduct' => \PrestaShop\PrestaShop\Core\Domain\Order\Command\CancelOrderProductCommand::class,
            'IssueStandardRefund' => \PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand::class,
            'GetOrderStatusIdByCode' => \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderStatusIdByCode::class,
        ];

        $available = [];
        foreach ($commandClasses as $key => $class) {
            if (self::checkCommandClassExists($class)) {
                $available[$key] = $class;
            }
        }

        return $available;
    }

    /**
     * Check if discount/cart rule commands are available.
     */
    public static function getAvailableDiscountCommandClasses(): array
    {
        $commandClasses = [
            'AddDiscount' => \PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand::class,
            'AddOrderCartRule' => \PrestaShop\PrestaShop\Core\Domain\CartRule\Command\AddOrderCartRuleCommand::class,
        ];

        $available = [];
        foreach ($commandClasses as $key => $class) {
            if (self::checkCommandClassExists($class)) {
                $available[$key] = $class;
            }
        }

        return $available;
    }

    /**
     * Ensure an order exists and return its ID.
     */
    public static function ensureOrderExists(): int
    {
        if (isset(self::$createdData['order'])) {
            return self::$createdData['order'];
        }

        try {
            // Check if order with ID 1 exists
            $order = new \Order(1);
            if (\Validate::isLoadedObject($order)) {
                self::$createdData['order'] = 1;

                return 1;
            }

            // Create a test order if it doesn't exist
            $customerId = self::ensureCustomerExists();
            $productId = self::ensureProductExists();
            $carrierId = self::ensureCarrierExists();
            $addressId = self::ensureAddressExists($customerId);
            $paymentMethod = self::getWorkingPaymentMethod();

            // Create order using PrestaShop's Order class
            $order = new \Order();
            $order->id_address_delivery = $addressId;
            $order->id_address_invoice = $addressId;
            $order->id_cart = 0; // We'll create a cart too
            $order->id_currency = 1;
            $order->id_lang = 1;
            $order->id_customer = $customerId;
            $order->id_carrier = $carrierId;
            $order->current_state = 2; // Payment accepted
            $order->secure_key = md5(uniqid());
            $order->payment = $paymentMethod;
            $order->total_paid = 10.00;
            $order->total_paid_tax_incl = 10.00;
            $order->total_paid_tax_excl = 10.00;
            $order->total_products = 10.00;
            $order->total_products_wt = 10.00;
            $order->total_shipping = 0.00;
            $order->total_shipping_tax_incl = 0.00;
            $order->total_shipping_tax_excl = 0.00;
            $order->carrier_tax_rate = 0.00;
            $order->module = $paymentMethod;
            $order->conversion_rate = 1.0;
            $order->reference = 'TEST-ORDER-001';
            $order->date_add = date('Y-m-d H:i:s');
            $order->date_upd = date('Y-m-d H:i:s');

            if ($order->add()) {
                $orderId = (int) $order->id;

                // Create order detail (product line)
                $orderDetail = new \OrderDetail();
                $orderDetail->id_order = $orderId;
                $orderDetail->product_id = $productId;
                $orderDetail->product_attribute_id = 0;
                $orderDetail->product_name = 'Test Product';
                $orderDetail->product_quantity = 1;
                $orderDetail->product_price = 10.00;
                $orderDetail->unit_price_tax_incl = 10.00;
                $orderDetail->unit_price_tax_excl = 10.00;
                $orderDetail->total_price_tax_incl = 10.00;
                $orderDetail->total_price_tax_excl = 10.00;
                $orderDetail->product_reference = 'TEST-PRODUCT';
                $orderDetail->add();

                // Create order history
                $orderHistory = new \OrderHistory();
                $orderHistory->id_order = $orderId;
                $orderHistory->id_order_state = 2;
                $orderHistory->id_employee = 1;
                $orderHistory->date_add = date('Y-m-d H:i:s');
                $orderHistory->add();

                self::$createdData['order'] = $orderId;

                return $orderId;
            } else {
                return 1; // Fallback order ID
            }
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not ensure order exists: ' . $e->getMessage());

            return 1; // Fallback order ID
        }
    }

    /**
     * Enable product returns in the test environment.
     */
    public static function enableProductReturns(): void
    {
        if (isset(self::$createdData['product_returns_enabled'])) {
            return;
        }

        try {
            // Enable product returns in configuration
            \Configuration::updateValue('PS_ORDER_RETURN', 1);
            \Configuration::updateValue('PS_ORDER_RETURN_NB_DAYS', 30);

            self::$createdData['product_returns_enabled'] = true;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not enable product returns: ' . $e->getMessage());
        }
    }

    /**
     * Enable order cancellations in the test environment.
     */
    public static function enableOrderCancellations(): void
    {
        if (isset(self::$createdData['order_cancellations_enabled'])) {
            return;
        }

        try {
            // Enable order cancellations
            \Configuration::updateValue('PS_ORDER_CANCELLATION', 1);

            self::$createdData['order_cancellations_enabled'] = true;
        } catch (\Exception $e) {
            error_log('TestDataBuilder: Could not enable order cancellations: ' . $e->getMessage());
        }
    }

    /**
     * Reset created data cache (useful for testing).
     */
    public static function reset(): void
    {
        self::$createdData = [];
    }
}
