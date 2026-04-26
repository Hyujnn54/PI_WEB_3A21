<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SessionAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
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
            'app_login_face',
            'app_register',
            'app_forgot_password',
            'app_forgot_password_verify',
        ];

        if (in_array($route, $publicRoutes, true)) {
            return;
        }

        if ($this->security->getUser() === null) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
            return;
        }

        $isAdminRoute = $route === 'back_dashboard'
            || str_starts_with($route, 'app_admin')
            || str_starts_with($route, 'management_');

        if ($isAdminRoute && !$this->security->isGranted('ROLE_ADMIN')) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('front_home')));
        }
    }
}
