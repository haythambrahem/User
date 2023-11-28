<?php

namespace App\EventListener;

use App\Entity\Personne;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();
        $personne = $token->getUser();
        $token = $event->getAuthenticatedToken();

        if ($personne instanceof Personne && $personne-> isIsbanned()) {
            $response = new RedirectResponse($this->urlGenerator->generate('app_banned'));
        } 
        elseif (in_array("ROLE_ADMIN", $personne->getRoles())) {
            $response = new RedirectResponse($this->urlGenerator->generate('front_personne'));
        }
        else{
            $response = new RedirectResponse($this->urlGenerator->generate('front_personne'));
        }

        $event->setResponse($response);
    }
}
