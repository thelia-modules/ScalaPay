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

/**
 * Scalapay payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SendConfirmationEmail extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event)
    {
        // We send the order confirmation email only if the order is paid
        $order = $event->getOrder();

        if (! $order->isPaid() && $order->getPaymentModuleId() === (int) Scalapay::getModuleId()) {
            $event->stopPropagation();
        }
    }

    /**
     * Checks if order payment module is Scalapay and if order new status is paid, send an email to the customer.
     *
     * @param OrderEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateStatus(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $order = $event->getOrder();

        if ($order->isPaid() && $order->getPaymentModuleId() === Scalapay::getModuleId()) {
            // Send confirmation email.
            $dispatcher->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, $event);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS           => array("updateStatus", 128),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationEmail", 129)
        );
    }
}
