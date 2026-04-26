<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SessionAccessSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ROUTES = [
        'app_login',
        'app_login_face',
        'app_register',
        'app_forgot_password',
        'app_forgot_password_verify',
    ];

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
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
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

        if ($this->isPublicRoute($route)) {
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

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ($route === '' || str_starts_with($route, '_') || $this->isPublicRoute($route)) {
            return;
        }

        // Prevent authenticated pages from being restored from browser history cache after logout.
        if ($this->security->getUser() === null) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }

    private function isPublicRoute(string $route): bool
    {
        return in_array($route, self::PUBLIC_ROUTES, true);
    }
}
