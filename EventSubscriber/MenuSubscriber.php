<?php

namespace KimaiPlugin\AbrechnungBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMainMenu', -10],
        ];
    }

    public function onMainMenu(ConfigureMainMenuEvent $event): void
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        if (!$this->security->isGranted('view_invoice')) {
            return;
        }

        $invoice = $event->getInvoiceMenu();
        if ($invoice === null) {
            return;
        }

        if ($invoice->getChild('abrechnung') === null) {
            $item = new MenuItemModel('abrechnung', 'menu.abrechnung', 'abrechnung_index', [], 'invoice');
            $item->setTranslationDomain('messages');
            $item->setChildRoutes(['abrechnung_index', 'abrechnung_mark']);
            $invoice->addChild($item);
        }
    }
}
