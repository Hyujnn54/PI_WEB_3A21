<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SessionAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ($route === '' || str_starts_with($route, '_')) {
            return;
        }

        $publicRoutes = [
            'app_login',
            'app_register',
        ];

        if (in_array($route, $publicRoutes, true)) {
            return;
        }

        $session = $request->getSession();
        $userId = $session->get('user_id');
        $roles = (array) $session->get('user_roles', []);

        if (!$userId) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
            return;
        }

        $isAdminRoute = $route === 'back_dashboard'
            || str_starts_with($route, 'app_admin')
            || str_starts_with($route, 'management_');

        if ($isAdminRoute && !in_array('ROLE_ADMIN', $roles, true)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('front_home')));
        }
    }
}
