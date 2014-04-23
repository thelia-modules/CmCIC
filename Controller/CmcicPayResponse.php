<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace CmCIC\Controller;

use CmCIC\CmCIC;
use CmCIC\Model\Config;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Class CmcicPayResponse
 * @package CmCIC\Controller
 * author Thelia <info@thelia.net>
 */
class CmcicPayResponse extends BaseFrontController
{
    /**
     * @param  int                                  $order_id
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function payfail($order_id)
    {
        /*
         * Empty cart
         */
        $event = new CartEvent($this->getSession()->getCart());
        $this->dispatch(TheliaEvents::CART_CLEAR, $event);

        return $this->render("badresponse",
            array(
                "order_id" => $order_id,
                "msg" => Translator::getInstance()->trans("A problem occured during the paiement of the order:")
            )
        );
    }

    /**
     * @throws \Exception
     */
    public function receiveResponse()
    {
        $request = $this->getRequest();
        $order_id = $request->get('reference');

        if(is_numeric($order_id))
            $order_id=(int) $order_id;

        /*
         * Configure log output
         */
        $log = Tlog::getInstance();
        $log->setDestinations("\\Thelia\\Log\\Destination\\TlogDestinationFile");
        $log->setConfig("\\Thelia\\Log\\Destination\\TlogDestinationFile", 0, THELIA_ROOT."log".DS."log-cmcic.txt");
        $log->info("accessed");

        $order = OrderQuery::create()->findPk($order_id);

        /*
         * Retrieve HMac for CGI2
         */
        $config = Config::read(CmCIC::JSON_CONFIG_PATH);

        $hashable = sprintf(
            CmCIC::CMCIC_CGI2_FIELDS,
            $config['CMCIC_TPE'],
            $request->get('date'),
            $request->get('montant'),
            $request->get('reference'),
            $request->get('texte-libre'),
            $config['CMCIC_VERSION'],
            $request->get('code-retour'),
            $request->get('cvx'),
            $request->get('vld'),
            $request->get('brand'),
            $request->get('status3ds'),
            $request->get('numauto'),
            $request->get('motifrefus'),
            $request->get('originecb'),
            $request->get('bincb'),
            $request->get('hpancb'),
            $request->get('ipclient'),
            $request->get('originetr'),
            $request->get('veres'),
            $request->get('pares')
        );

        $mac = CmCIC::computeHmac(
            $hashable,
            CmCIC::getUsableKey($config["CMCIC_KEY"])
        );
        $response=CmCIC::CMCIC_CGI2_MACNOTOK.$hashable;

        if ($mac === strtolower($request->get('MAC'))) {

            $code = $request->get("code-retour");
            $msg = null;

            $status = OrderStatusQuery::create()
                ->findOneByCode(OrderStatus::CODE_PAID);

            $event = new OrderEvent($order);
            $event->setStatus($status->getId());

            switch ($code) {
                case "payetest":
                    $msg = "The test payment of the order ".$order->getRef()." has been successfully released. ";
                    $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
                    break;
                case "paiement":
                    $msg = "The payment of the order ".$order->getRef()." has been successfully released. ";
                    $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
                    break;
                case "Annulation":
                    $msg = "Error during the paiement: ".$this->getRequest()->get("motifrefus");
                    break;
                default:
                    $log->error("Error while receiving response from CMCIC: code-retour not valid");
                    throw new \Exception(Translator::getInstance()->trans("An error occured, no valid code-retour"));
            }

            if (!empty($msg)) {
                $log->info($msg);
            }

            $response= CmCIC::CMCIC_CGI2_MACOK;
        }
        /*
         * Get log back to previous state
         */
        $log->setDestinations("\\Thelia\\Log\\Destination\\TlogDestinationRotatingFile");

        return Response::create(
            sprintf(CmCIC::CMCIC_CGI2_RECEIPT,$response),
            200,
            array(
                "Content-type"=> "text/plain",
                "Pragma"=> "nocache"
            )
        );
    }
}
