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

namespace CmCIC\EventListeners;

use CmCIC\CmCIC;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;

class SendConfirmationEmail implements EventSubscriberInterface
{
    /**
     * @var MailerFactory
     */
    protected $mailer;

    /** @var  EventDispatcherInterface */
    protected $dispatcher;

    /**
     * SendConfirmationEmail constructor.
     * @param MailerFactory $mailer
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(MailerFactory $mailer, EventDispatcherInterface $dispatcher)
    {
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
    }
    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event)
    {
        if (CmCIC::getConfigValue('send_confirmation_message_only_if_paid')) {
            // We send the order confirmation email only if the order is paid
            $order = $event->getOrder();
            if (! $order->isPaid() && $order->getPaymentModuleId() == CmCIC::getModuleId()) {
                $event->stopPropagation();
            }
        }
    }

    /**
     * @params OrderEvent $order
     * Checks if order payment module is paypal and if order new status is paid, send an email to the customer.
     * @param OrderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();
        if ($order->isPaid() && $order->getPaymentModuleId() == CmCIC::getModuleId()) {
            // Send confirmation email if required.
            if (CmCIC::getConfigValue('send_confirmation_message_only_if_paid')) {
                $this->dispatcher->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, $event);
            }

            Tlog::getInstance()->debug("Confirmation email sent to customer " . $order->getCustomer()->getEmail());
        }
    }
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS           => array("updateStatus", 100),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationEmail", 129)
        );
    }
}
