<?php

declare(strict_types=1);

namespace Drupal\cors_ui\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DestructableInterface;
use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates the service container if cors_ui.configuration is modified.
 */
final class ConfigSubscriber implements EventSubscriberInterface, DestructableInterface {

  /**
   * If the container should be invalidated.
   *
   * @var bool
   */
  private bool $shouldInvalidate = FALSE;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  private DrupalKernelInterface $drupalKernel;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Constructs a new ConfigSubscriber object.
   *
   * @param \Drupal\Core\DrupalKernelInterface $drupalKernel
   *   The Drupal kernel.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator.
   */
  public function __construct(DrupalKernelInterface $drupalKernel, CacheTagsInvalidatorInterface $cacheTagsInvalidator) {
    $this->drupalKernel = $drupalKernel;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

  /**
   * Checks if cors_ui.configuration was modified to invalidate the container.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event.
   */
  public function onSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() === 'cors_ui.configuration' && $config->get() !== $config->getOriginal()) {
      $this->shouldInvalidate = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    if ($this->shouldInvalidate) {
      $this->cacheTagsInvalidator->invalidateTags(['http_response']);
      $this->drupalKernel->rebuildContainer();
    }
  }

}
