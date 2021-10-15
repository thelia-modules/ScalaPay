<?php
/*************************************************************************************/
/*      Copyright (c) OpenStudio                                                     */
/*      web : https://www.openstudio.fr                                              */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: tmic
 * Date: 17/09/2021
 */

namespace Scalapay\Exception;

use Thelia\Model\Order;

class PaymentException extends \Exception
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @param Order $order
     */
    public function __construct(Order $order, string $message)
    {
        $this->order = $order;

        parent::__construct($message);
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }
}
