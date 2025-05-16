<?php

namespace Drupal\unb_herbarium\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginRedirectSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 35], // Run after main routing
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $session = $request->getSession();

    // Only act on master requests
    if (!$event->isMainRequest()) {
      return;
    }

    // Only redirect authenticated users
    $current_user = \Drupal::currentUser();
    if (!$current_user->isAuthenticated()) {
      return;
    }

    // Check for our redirect flag
    if ($session->has('custom_login_redirect')) {
      $redirect_path = $session->get('custom_login_redirect');
      $session->remove('custom_login_redirect');
      $event->setResponse(new RedirectResponse($redirect_path));
    }
  }
}