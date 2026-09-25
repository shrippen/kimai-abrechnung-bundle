<?php

namespace KimaiPlugin\AbrechnungBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page actions of the billing page (icon buttons in the page header, "…" on mobile).
 */
final class AbrechnungActionsSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'abrechnung';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        // Reversible: runs immediately via the bulk bar (page JS), followed by an undo toast
        if (($payload['mark_all'] ?? false) === true) {
            $event->addAction('success', [
                'url' => '#',
                'class' => 'abrechnung-mark-all',
                'title' => 'abrechnung.mark_all_visible',
            ]);
        }

        if ($this->isGranted('create_export')) {
            $event->addAction('export', [
                'url' => $this->path('export', $payload['export_params'] ?? []),
                'title' => 'abrechnung.open_export',
            ]);
        }
    }
}
