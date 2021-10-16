<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Scalapay;

use Scalapay\Scalapay\Factory\Api;
use Scalapay\Scalapay\Model\Merchant\Authorization;
use Scalapay\Scalapay\Model\Merchant\Consumer;
use Scalapay\Scalapay\Model\Merchant\Contact;
use Scalapay\Scalapay\Model\Merchant\Discount;
use Scalapay\Scalapay\Model\Merchant\Item;
use Scalapay\Scalapay\Model\Merchant\MerchantOptions;
use Scalapay\Scalapay\Model\Merchant\Money;
use Scalapay\Scalapay\Model\Merchant\OrderDetails;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class Scalapay extends AbstractPaymentModule
{
    /** @var string */
    const DOMAIN_NAME = 'scalapay';

    const ACCESS_KEY = 'access_key';
    const MODE = 'run_mode';
    const ALLOWED_IP_LIST = 'allowed_ip_list';
    const MINIMUM_AMOUNT = 'minimum_amount';
    const MAXIMUM_AMOUNT = 'maximum_amount';

    public function pay(Order $order)
    {
        if (!$this->checkValidConfiguration()) {
            return new RedirectResponse(
                $this->getPaymentFailurePageUrl(
                    $order->getId(),
                    $doWebPaymentResponse->result['longMessage'] ?? "Erreur de configuration"
                )
            );
        }

        $customer = $order->getCustomer();
        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $deliveryAddress = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

        $phoneNumber = $invoiceAddress->getCellphone() ?: $invoiceAddress->getPhone();

        $consumer = new Consumer();
        $consumer
            ->setEmail($customer->getEmail())
            ->setGivenNames($customer->getFirstname())
            ->setSurname($customer->getLastname())
            ->setPhoneNumber($phoneNumber ?: null)
        ;

        $billing = new Contact();
        $billing
            ->setName($invoiceAddress->getFirstname() . ' ' . $invoiceAddress->getLastname())
            ->setLine1($invoiceAddress->getAddress1())
            ->setLine2($invoiceAddress->getAddress2())
            ->setSuburb($invoiceAddress->getCity())
            ->setPostcode($invoiceAddress->getZipcode())
            ->setCountryCode($invoiceAddress->getCountry()->getIsoalpha2())
            ->setPhoneNumber($phoneNumber ?: null)
        ;

        $shipping = new Contact();
        $shipping
            ->setName($deliveryAddress->getFirstname() . ' ' . $deliveryAddress->getLastname())
            ->setLine1($deliveryAddress->getAddress1())
            ->setLine2($deliveryAddress->getAddress2())
            ->setSuburb($deliveryAddress->getCity())
            ->setPostcode($deliveryAddress->getZipcode())
            ->setCountryCode($deliveryAddress->getCountry()->getIsoalpha2())
            ->setPhoneNumber($deliveryAddress->getCellphone() ?: $deliveryAddress->getPhone())
        ;

        $itemList = [];
        foreach ($order->getOrderProducts() as $product) {
            $itemPrice = new Money();
            $itemPrice
                ->setAmount($product->getPrice())
                ->setCurrency($order->getCurrency()->getCode());

            $item = new Item();
            $item
                ->setName($product->getTitle())
                ->setSku($product->getProductSaleElementsRef())
                ->setQuantity($product->getQuantity())
                ->setPrice($itemPrice);

            $itemList[] = $item;
        }

        $merchantOptions = new MerchantOptions();
        $merchantOptions
            ->setRedirectConfirmUrl(URL::getInstance()->absoluteUrl('/scalapay/notification'))
            ->setRedirectCancelUrl($this->getPaymentFailurePageUrl($order->getId(), "Vous avez annulé le paiement"));

        $totalAmount = new Money();
        $totalAmount
            ->setAmount($order->getTotalAmount($tax))
            ->setCurrency($order->getCurrency()->getCode());

        $taxAmount = new Money();
        $taxAmount
            ->setAmount($tax)
            ->setCurrency($order->getCurrency()->getCode());

        $shippingAmount = new Money();
        $shippingAmount
            ->setAmount($order->getPostage())
            ->setCurrency($order->getCurrency()->getCode());

        $discountAmount = new Money();
        $discountAmount
            ->setAmount($order->getDiscount())
            ->setCurrency($order->getCurrency()->getCode());

        $discount = new Discount();
        $discount
            ->setDisplayName('Discount')
            ->setAmount($discountAmount);

        $orderDetails = new OrderDetails();
        $orderDetails
            ->setConsumer($consumer)
            ->setBilling($billing)
            ->setShipping($shipping)
            ->setMerchant($merchantOptions)
            ->setItems($itemList)
            ->setTotalAmount($totalAmount)
            ->setShippingAmount($shippingAmount)
            ->setTaxAmount($taxAmount)
            ->setDiscounts([$discount])
            ->setMerchantReference($order->getRef())
        ;

        $scalapayApi = new Api();

        try {
            $apiResponse  = $scalapayApi->createOrder(self::getApiAuthorization(), $orderDetails);

            // Store toke on order transaction ID field
            $order->setTransactionRef($apiResponse->getToken())->save();

            // Redirect to payment page
            return new RedirectResponse($apiResponse->getCheckoutUrl());
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        return new RedirectResponse(
            $this->getPaymentFailurePageUrl(
                $order->getId(),
                $errorMessage ?: "Erreur indeterminée"
            )
        );
    }

    public function isValidPayment()
    {
        $mode = self::getConfigValue(self::MODE);
        $valid = true;
        if ($mode === 'TEST') {
            $raw_ips = explode("\n", self::getConfigValue(self::ALLOWED_IP_LIST, ''));
            $allowed_client_ips = array();

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = in_array($client_ip, $allowed_client_ips) || in_array('*', $allowed_client_ips);
        }

        if ($valid) {
            // Check if total order amount is in the module's limits
            $valid = $this->checkMinMaxAmount(self::MINIMUM_AMOUNT, self::MAXIMUM_AMOUNT);
        }

        return $valid;
    }

    protected function checkValidConfiguration()
    {
        return (
            self::getConfigValue(self::ACCESS_KEY) !== null
        );
    }

    protected function checkMinMaxAmount($min, $max)
    {
        $order_total = $this->getCurrentOrderTotalAmount();

        $min_amount = self::getConfigValue($min, 0);
        $max_amount = self::getConfigValue($max, 0);

        return $order_total > 0 && ($min_amount <= 0 || $order_total >= $min_amount) && ($max_amount <= 0 || $order_total <= $max_amount);
    }

    public static function getApiAuthorization()
    {
        $apiKey = self::getConfigValue(self::ACCESS_KEY);

        $apiUri = Authorization::PRODUCTION_URI;
        if (self::getConfigValue(self::MODE) === "TEST") {
            $apiUri = Authorization::SANDBOX_URI;
        }

        return new Authorization($apiUri, $apiKey);
    }
}
