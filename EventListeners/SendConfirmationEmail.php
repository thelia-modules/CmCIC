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
use Exception;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

class SendConfirmationEmail extends BaseAction implements EventSubscriberInterface
{
    protected EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param OrderEvent $event
     *
     * @throws Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event): void
    {
        if (CmCIC::getConfigValue('send_confirmation_message_only_if_paid')) {
            // We send the order confirmation email only if the order is paid
            $order = $event->getOrder();
            if (!$order->isPaid() && $order->getPaymentModuleId() == CmCIC::getModuleId()) {
                $event->stopPropagation();
            }
        }
    }

    /**
     * @param OrderEvent $event
     *
     * @throws Exception if the message cannot be loaded.
     */
    public function sendNotificationEmail(OrderEvent $event): void
    {
        if (CmCIC::getConfigValue('send_confirmation_message_only_if_paid')) {
            // We send the order notification email only if the order is paid
            $order = $event->getOrder();
            if (!$order->isPaid() && $order->getPaymentModuleId() == CmCIC::getModuleId()) {
                $event->stopPropagation();
            }
        }
    }

    /**
     * @param OrderEvent $event
     * @throws PropelException
     */
    public function updateStatus(OrderEvent $event): void
    {
        $order = $event->getOrder();
        if ($order->isPaid() && $order->getPaymentModuleId() == CmCIC::getModuleId()) {
            // Send confirmation email if required.
            if (CmCIC::getConfigValue('send_confirmation_message_only_if_paid')) {
                $this->dispatcher->dispatch($event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);
                $this->dispatcher->dispatch($event, TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL);
            }

            Tlog::getInstance()->debug("Confirmation email sent to customer " . $order->getCustomer()->getEmail());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateStatus", 128),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationEmail", 129),
            TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => array("sendNotificationEmail", 129)
        );
    }
}
