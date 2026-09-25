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

        // Reversible: runs immediately (kit.js data-kpu-post), followed by an undo toast (GUIDELINES 3.5)
        $markAll = $payload['mark_all'] ?? null;
        if (\is_array($markAll) && \count($markAll['ids'] ?? []) > 0) {
            $event->addAction('success', [
                'url' => '#',
                'title' => 'abrechnung.mark_all_visible',
                'attr' => [
                    'data-kpu-post' => $markAll['url'],
                    'data-kpu-token' => $markAll['token'],
                    'data-kpu-ids' => implode(',', $markAll['ids']),
                ],
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
