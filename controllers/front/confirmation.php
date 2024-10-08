<?php
/**
 * Checkout.com
 * Authorised and regulated as an electronic money institution
 * by the UK Financial Conduct Authority (FCA) under number 900816.
 *
 * PrestaShop v1.7
 *
 * @category  prestashop-module
 * @package   Checkout.com
 * @author    Platforms Development Team <platforms@checkout.com>
 * @copyright 2010-2020 Checkout.com
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://docs.checkout.com/
 */

use Checkout\Models\Response;
use CheckoutCom\PrestaShop\Helpers\Utilities;
use CheckoutCom\PrestaShop\Classes\CheckoutApiHandler;
use Checkout\Library\Exceptions\CheckoutHttpException;
use CheckoutCom\PrestaShop\Classes\CheckoutcomCustomerCard;
use CheckoutCom\PrestaShop\Models\Payments\Method;

class CheckoutcomConfirmationModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false) || (Tools::isSubmit('secure_key') == false)) {
            return false;
        }

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $flagged = false;
        $transaction_id = '';
        $status = 'Pending';
        $source_type = Tools::getValue('source');

        //Recurring literals
        $literal_order= '&id_order=';
        $literal_confirmation = 'index.php?controller=order-confirmation&id_cart=';
        $literal_module = '&id_module=';
        $literal_key = '&key=';

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        // Check if an order has already been placed using this cart by webhook fallback
        $existingOrder = Order::getOrderByCartId($cart_id);
        if ($existingOrder) {
             $this->module->logger->info(
                    'Channel Confirmation -- Existing order. Redirecting to thank you :',
                    array('obj' => $existingOrder)
                );
             $module_id = $this->module->id;
             Tools::redirect($literal_confirmation . (int) $cart->id . $literal_module . $module_id . $literal_order . $existingOrder . $literal_key . $secure_key);
           
        }

        if (Tools::isSubmit('cko-session-id') || strpos($secure_key, '?cko-session-id=') !== false) {

            if (Tools::isSubmit('cko-session-id')) {
                $cko_session_id = $_REQUEST['cko-session-id'];
            }else{
                $separated_url = explode('?cko-session-id=', $secure_key);
                $secure_key = $separated_url[0];
                $cko_session_id = $separated_url[1];
            }

            $response = $this->_verifySession($cko_session_id);
            $this->module->logger->info(
                'Channel Confirmation -- Response :',
                array('obj' => $response)
            );
            if ($response->isSuccessful() && !$response->isPending()) {
                $suffix = '';
                if($source_type === 'apple'){
                    $suffix = '-apay';
                }
                else if ($response->source['type'] === 'card') {
                    $suffix = '-card';
                }
                $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
                $paid = (float) Method::fixAmount($response -> amount, $response -> currency, true);

                $this->module->logger->info(
                    'Channel Confirmation -- Total :',
                    array('obj' => $total)
                );

                $this->module->logger->info(
                    'Channel Confirmation -- Paid :',
                    array('obj' => $paid)
                );
                
                if ($this->module->validateOrder(
                                                    $cart->id,
                                                    _PS_OS_PAYMENT_,
                                                    $paid,
                                                    $this->module->displayName.$suffix,
                                                    '',
                                                    array(),
                                                    (int) $cart->id_currency,
                                                    false,
                                                    $customer->secure_key
                                                )
                ) {
                    $this->context->order = new Order($this->module->currentOrder); // Add order to context. Experimental.
                } else {
					$this->module->logger->error(sprintf('Channel Confirmation -- Failed to create order %s ', $cart_id));
                    \PrestaShopLogger::addLog("Failed to create order.", 2, 0, 'Cart' , $cart_id, true);
                    // Set error message
                    $this->context->controller->errors[] = $this->module->l('Payment method not supported. (0003)');
                    // Redirect to cartcontext
                    $this->redirectWithNotifications('index.php?controller=order&step=1&key=' . $customer->secure_key . '&id_cart=' . $cart->id);
                }

                $flagged = $response->isFlagged();
                $threeDS = $response->getValue(array('threeDs', 'enrolled')) === 'Y';

                $transaction_id = $response->id;
                $status = $response->status;

                $context = \Context::getContext();

                if($context->cookie->__isset('save-card-checkbox') ){
                    CheckoutcomCustomerCard::saveCard($response,$context->customer->id);
                    $context->cookie->__unset('save-card-checkbox');
                }
            }
        } else {
            // Set error message
			$this->module->logger->error('Channel Confirmation -- An error has occured while processing your transaction.');
            $this->context->controller->errors[] = $this->trans('An error has occured while processing your transaction.', [], 'Shop.Notifications.Error');
            // Redirect to cart
            $this->redirectWithNotifications(__PS_BASE_URI__ . 'index.php?controller=order&step=1&key=' . $secure_key . '&id_cart='
                . (int) $cart_id);
        }

        // $message = null; // You can add a comment directly into the order so the merchant will see it in the BO.

        /**
         * If the order has been validated we try to retrieve it
         */
        $order_id = Order::getOrderByCartId((int) $cart->id);

        if ($order_id && ($secure_key == $customer->secure_key)) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */
            $module_id = $this->module->id;
			$this->module->logger->info('Channel Confirmation -- Order has been placed.');

            /**
             * load order payment and set cko action id as order transaction id
             */

            $order = new Order($order_id);
            $payments = $order->getOrderPaymentCollection();
            $payments[0]->transaction_id = $transaction_id;
            $payments[0]->update();

            $this->module->logger->info('Channel Confirmation -- Order payment mapped:'.$payments[0]->transaction_id);

            /**
             * Load the order history, change the status and send email confirmation
             */
            $this->module->logger->info('Channel Confirmation -- Order payment state:'.$order->getCurrentOrderState()->id );
            $this->module->logger->info('Channel Confirmation -- Order payment error state:'._PS_OS_ERROR_ );

            //Added check to make sure history is not deleted when validation marks the order as error
            if((int)$order->getCurrentOrderState()->id !== (int)_PS_OS_ERROR_){
                $orderStatus = $status === 'Captured' ? \Configuration::get('CHECKOUTCOM_CAPTURE_ORDER_STATUS') : \Configuration::get('CHECKOUTCOM_AUTH_ORDER_STATUS');

                // Reset order history
                $sql = 'DELETE FROM `'._DB_PREFIX_.'order_history` WHERE `id_order`='.$order_id;
                Db::getInstance()->execute($sql);

                $history = new OrderHistory();
                $history->id_order = $order_id;
                $history->changeIdOrderState($orderStatus, $order_id, true);
                $history->add();
                $this->module->logger->info('Channel Confirmation -- New order status : ' . $order_id);
                

                // Flag Order
                if($flagged && $threeDS && !Utilities::addMessageToOrder($this->trans('⚠️ This order is flagged as a potential fraud. We have proceeded with the payment, but we recommend you do additional checks before shipping the order.', [], 'Modules.Checkoutcom.Confirmation.php'), $order)) {
                    \PrestaShopLogger::addLog('Failed to add payment flag note to order.', 2, 0, 'CheckoutcomPlaceorderModuleFrontController' , $order->id, true);
                }

                Tools::redirect($literal_confirmation . (int) $cart->id . $literal_module . $module_id . $literal_order . $order_id . $literal_key. $secure_key);
            }
            else{
                //Add warning message on mismatching amounts

                Utilities::addMessageToOrder($this->trans('⚠️ Total amount paid does not match the cart total. We recommend you do additional checks before shipping the order.', [], 'Modules.Checkoutcom.Confirmation.php'), $order);
                $this->context->controller->errors[] = $this->trans('Only '.$response -> currency.' '.$paid.' has been paid towards the order. Please contact support.', [], 'Shop.Notifications.Error');
                $this->redirectWithNotifications(__PS_BASE_URI__ . $literal_confirmation . (int) $cart->id . $literal_module . $module_id . $literal_order . $order_id . $literal_key . $secure_key);
                
            }
        } else {
			$this->module->logger->error(sprintf('Channel Confirmation -- Cart %s didn\'t match any order.', $cart_id));
            \PrestaShopLogger::addLog("Cart {$cart->id} didn't match any order.", 2, 0, 'Cart' , $cart_id, true);

            /*
             * An error occured and is shown on a new page.
             */
            $this->context->controller->errors[] = $this->trans('An error has occured while processing your transaction.', [], 'Shop.Notifications.Error');
            // Redirect to cart
            $this->redirectWithNotifications(__PS_BASE_URI__ . 'index.php?controller=order&step=1&key=' . $secure_key . '&id_cart='
                . (int) $cart_id);
        }
    }

    private function _verifySession($session_id)
    {
        $response = new Response();

        try {
            // Get payment response
            $response = CheckoutApiHandler::api()->payments()->details($session_id);
        } catch (CheckoutHttpException $ex) {
            $response->http_code = $ex->getCode();
            $response->message = $ex->getMessage();
            $response->errors = $ex->getErrors();
        }
        return $response;
    }

}
