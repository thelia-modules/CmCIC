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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CmCIC\Controller;

use CmCIC\CmCIC;
use CmCIC\Model\Config;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
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
    public function payfail($order_id)
    {
        $url = $this->getRouteFromRouter(
            'router.front',
            'order.failed',
            [
                'order_id' => $order_id,
                'message' => $this->getTranslator()->trans("Your payment was rejected", [], CmCIC::DOMAIN_NAME)
            ]
        );
    
        return $this->generateRedirect($url);
    }

    /**
     * @throws \Exception
     */
    public function receiveResponse(EventDispatcherInterface $eventDispatcher)
    {
        $request = $this->getRequest();
        $order_id = $request->get('reference');

        if (is_numeric($order_id)) {
            $order_id = (int)$order_id;
        }

        /*
         * Configure log output
         */
        $log = Tlog::getInstance();
        $log->setDestinations("\\Thelia\\Log\\Destination\\TlogDestinationFile");
        $log->setConfig("\\Thelia\\Log\\Destination\\TlogDestinationFile", 0, THELIA_LOG_DIR . "log-cmcic.txt");
        $log->info("Reception confirmation paiement CB : ". json_encode($request->request->all()));

        $order = OrderQuery::create()->findPk($order_id);

        /*
         * Retrieve HMac for CGI2
         */
        $config = Config::read(CmCIC::JSON_CONFIG_PATH);
		
		$vars = $request->request->all();
		
		unset($vars['MAC']);
		
		$hashable = CmCIC::getHashable($vars);
		
        $computed_mac = CmCIC::computeHmac(
            $hashable,
            CmCIC::getUsableKey($config["CMCIC_KEY"])
        );
        $response=CmCIC::CMCIC_CGI2_MACNOTOK.$hashable;
		
		$request_mac = strtolower($request->get('MAC'));

        if ($computed_mac == $request_mac) {
            $code = $request->get("code-retour");
            $msg = null;

            $status = OrderStatusQuery::create()
                ->findOneByCode(OrderStatus::CODE_PAID);

            $event = new OrderEvent($order);
            $event->setStatus($status->getId());
			
            switch ($code) {
                case "payetest":
                    $msg = "The test payment of the order ".$order->getRef()." has been successfully released. ";
                    $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS );
                    break;
                case "paiement":
                    $msg = "The payment of the order ".$order->getRef()." has been successfully released. ";
                    $eventDispatcher->dispatch($event,TheliaEvents::ORDER_UPDATE_STATUS );
                    break;
                case "Annulation":
                    $msg = "Error during the paiement: ".$this->getRequest()->get("motifrefus");
                    break;
                default:
                    $log->error("Error while receiving response from CMCIC: code-retour not valid $code");
                    throw new \Exception(
                        $this->getTranslator()->trans("An error occured, no valid code-retour $code", [], CmCIC::DOMAIN_NAME)
                    );
            }

            if (!empty($msg)) {
                $log->info($msg);
            }

            $response= CmCIC::CMCIC_CGI2_MACOK;
        } else {
			$log->error("MAC could not be validated. Received : $request_mac, computed : $computed_mac");
		}

        /*
         * Get log back to previous state
         */
        $log->setDestinations("\\Thelia\\Log\\Destination\\TlogDestinationRotatingFile");

        return new Response(
            sprintf(CmCIC::CMCIC_CGI2_RECEIPT, $response),
            200,
            array(
                "Content-type"=> "text/plain",
                "Pragma"=> "nocache"
            )
        );
    }
}
