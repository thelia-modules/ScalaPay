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

namespace Scalapay\Controller;

use Scalapay\Exception\PaymentException;
use Scalapay\Scalapay;
use Scalapay\Scalapay\Factory\Api;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;
use Thelia\Tools\URL;

/**
 * Scalapay payment module. Processing of Scalapay return
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class NotificationController extends BasePaymentModuleController
{
    /**
     * Traiter la notification reçue du serveur Scalapay
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function notificationAction()
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        $order = null;

        try {
            // The token is in the orderToken request parameter
            if (null === $token = $this->getRequest()->get('orderToken')) {
                Tlog::getInstance()->error("Notification Scalapay appelée sans token. Réponse:".print_r($this->getRequest(), 1));

                throw new TheliaProcessException(
                    $this->getTranslator()->trans("Erreur technique: le token Scalapay est absent.", [], Scalapay::DOMAIN_NAME)
                );
            }

            $order = $this->getOrderFromToken($token);

            $this->processScalapayReturn($order, $token);

            // Succès: redirection vers la page order-placed
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.placed",
                        ["order_id" => $order->getId()],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        } catch (PaymentException $ex) {
            // Echec du paiement: redirection vers order-failed
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.failed",
                        [
                            "order_id" => $ex->getOrder()->getId(),
                            "message" => $ex->getMessage()
                        ],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        } catch (\Exception $ex) {
            // Ici on n'a pas pu retrouver la commande : pas possible donc d'afficher order-failed
            // on redirige vers la page d'erreur générique.
            $orderId = $order !== null ? $order->getId() : 0;

            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.failed",
                        [
                            "order_id" => $orderId,
                            "message" => Scalapay::sanitizeApiErrorResponse($ex->getMessage())
                        ],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        }
    }

    /**
     * @param Order $order
     * @param string $token
     * @throws PaymentException
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function processScalapayReturn(Order $order, string $token): void
    {
        if (null !== $status = $this->getRequest()->get('status')) {
            if ('SUCCESS' !== $status) {
                throw new PaymentException(
                    $order,
                    $this->getTranslator()->trans("Désolé, voitre paiement a échoué.", [], Scalapay::DOMAIN_NAME)
                );
            }
        }

        $scalapayApi = new Api();

        $response = $scalapayApi->capture(Scalapay::getApiAuthorization(), $token);

        Tlog::getInstance()->error("Scalapay capture response:" . print_r($response, 1));

        // Si la commande est déjà payée on ne fait rien.
        if (!$order->isPaid()) {
            $this->checkPaymentResult($response, $order);
        }
    }

    /**
     * @throws PaymentException
     * @throws \Exception
     */
    protected function checkPaymentResult($response, Order $order): void
    {
        $message = $this->getTranslator()->trans(
            "Erreur technique: ID de transaction absent",
            [],
            Scalapay::DOMAIN_NAME
        );

        if ($response === 'APPROVED') {
            Tlog::getInstance()->info("Paiement de la commande " . $order->getRef() . " confirmé, transaction ID ".$order->getTransactionRef(), [], Scalapay::DOMAIN_NAME);

            $this->confirmPayment($order->getId());

            return;
        }

        Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef().", raison: $response");

        $message = $this->getTranslator()->trans(
            "Votre paiement a été refusé (raison: %raison)",
            [
                '%raison' => $response
            ],
            Scalapay::DOMAIN_NAME
        );

        Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef(). ": $message");

        // Cancel the order
        $event = (new OrderEvent($order))
            ->setStatus(OrderStatusQuery::getCancelledStatus()->getId());

        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

        throw new PaymentException($order, $message);
    }

    /**
     * @param object $response
     * @return Order
     * @throws TheliaProcessException
     */
    protected function getOrderFromToken($token): Order
    {
        if (null === $order = OrderQuery::create()->findOneByTransactionRef($token)) {
            Tlog::getInstance()->error("Pas de commande trouvée pour la transaction.: ". $token);
            throw new TheliaProcessException(
                $this->getTranslator()->trans("Echec du paiement: la commande n'a pas pu être retrouvée", [], Scalapay::DOMAIN_NAME)
            );
        }

        return $order;
    }

    protected function getModuleCode(): string
    {
        return 'Scalapay';
    }
}
