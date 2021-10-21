<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Scalapay\EventListener;

use Scalapay\Scalapay;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

/**
 * Scalapay payment module
 *
 * @author Franck Allimant <fallimant@openstudio.fr>
 */
class SendConfirmationEmail extends BaseAction implements EventSubscriberInterface
{
    /**
     * Prevent sending email notifications if the order is not paid.
     *
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationOrNotificationEmail(OrderEvent $event)
    {
        // We send the order confirmation email only if the order is paid
        $order = $event->getOrder();

        if (! $order->isPaid() && $order->getPaymentModuleId() === (int) Scalapay::getModuleId()) {
            $event->stopPropagation();
        }
    }

    /**
     * @params OrderEvent $order
     * If we're the payment module for this order and if the order status is "paid",
     * send the notification email to the customer and the shop manager.
     *
     * @param OrderEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateStatus(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $order = $event->getOrder();

        if ($order->isPaid() && $order->getPaymentModuleId() === (int) Scalapay::getModuleId()) {
            $dispatcher->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, $event);
            $dispatcher->dispatch(TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL, $event);

            Tlog::getInstance()->debug("Confirmation email sent to customer " . $order->getCustomer()->getEmail());
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS           => array("updateStatus", 128),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationOrNotificationEmail", 129),
            TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => array("sendConfirmationOrNotificationEmail", 129)
        );
    }
}
