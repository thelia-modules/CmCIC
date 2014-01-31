<?php

namespace CmCIC\Listener;

use Thelia\Action\BaseAction;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CmCIC\Model\Config;
use CmCIC\Controller\CmcicPayController;

class GotoBankService extends BaseAction implements EventSubscriberInterface {

    public function payservice() {
        $control = new CmcicPayController();
        $control->goto_paypage(new Config());
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_BEFORE_PAYMENT => array('payservice', 128)
        );
    }

}