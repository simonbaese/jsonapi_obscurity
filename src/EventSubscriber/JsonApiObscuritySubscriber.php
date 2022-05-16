<?php

namespace Drupal\jsonapi_obscurity\EventSubscriber;

use Drupal\Core\Language\LanguageManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that handles the JSON:API obscurity prefix.
 */
class JsonApiObscuritySubscriber implements EventSubscriberInterface {

  /**
   * Creates a new JsonApiObscuritySubscriber object.
   *
   * @param string $jsonApiBasePath
   *   The JSON:API base path.
   * @param string $obscurityPrefix
   *   The JSON:API obscurity prefix.
   */
  public function __construct(
    protected string $jsonApiBasePath,
    protected string $obscurityPrefix
  ) {}

  /**
   * Handles incoming JSON:API requests with an obscurity prefix.
   */
  public function handle(RequestEvent $event): void {
    $request = $event->getRequest();
    if ($this->applies($request)) {
      $this->validatePrefix($request);
      $this->reinitializeRequestWithoutPrefix($request);
    }
  }

  /**
   * Decides whether obscurity prefix handling applies.
   */
  protected function applies(Request $request): bool {
    return !empty($this->obscurityPrefix) &&
      str_contains($request->getPathInfo(), $this->jsonApiBasePath . '/');
  }

  /**
   * Validates obscurity prefix in the requested path.
   */
  protected function validatePrefix(Request $request): void {
    $this->obscurityPrefix = '/' . ltrim($this->obscurityPrefix, '/');
    $prefix = strstr($request->getPathInfo(), $this->jsonApiBasePath, TRUE);
    if ($prefix == $this->obscurityPrefix) {
      return;
    }
    // Check with potential langcode.
    $langcode = substr($prefix, strrpos($prefix, '/') + 1);
    if (array_key_exists($langcode, LanguageManager::getStandardLanguageList()) &&
      $prefix == $this->obscurityPrefix . '/' . $langcode) {
      return;
    }
    throw new NotFoundHttpException();
  }

  /**
   * Cuts the obscurity prefix from the path in the request.
   */
  protected function reinitializeRequestWithoutPrefix(Request $request): void {
    $request->server->set('REQUEST_URI', $this->getBarePath($request));
    // The request has to be reinitialized to set the correct path info.
    $request->initialize(
      $request->query->all(),
      $request->request->all(),
      $request->attributes->all(),
      $request->cookies->all(),
      $request->files->all(),
      $request->server->all(),
      $request->getContent()
    );
  }

  /**
   * Returns the path without the obscurity prefix.
   */
  protected function getBarePath(Request $request): string {
    return preg_replace('/^' . preg_quote($this->obscurityPrefix, '/') . '/', '', $request->getPathInfo()) ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Choose a high priority because the route will be modified.
    $events[KernelEvents::REQUEST][] = ['handle', 980];
    return $events;
  }

}
