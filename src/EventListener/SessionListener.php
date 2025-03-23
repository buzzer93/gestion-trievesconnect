<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use App\Service\DatabaseResetService;

class SessionListener implements EventSubscriberInterface
{
    private DatabaseResetService $databaseResetService;

    public function __construct(DatabaseResetService $databaseResetService)
    {
        $this->databaseResetService = $databaseResetService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        if ($session && !$session->has('database_reset')) {
            $session->start();
            $this->databaseResetService->resetDatabase();
            $session->set('database_reset', true);
        }
    }
}