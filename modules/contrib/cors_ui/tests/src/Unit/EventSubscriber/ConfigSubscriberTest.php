<?php

declare(strict_types=1);

namespace Drupal\Tests\cors_ui\Unit\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\cors_ui\EventSubscriber\ConfigSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests ConfigSubscriber.
 *
 * @group cors_ui
 * @coversDefaultClass \Drupal\cors_ui\EventSubscriber\ConfigSubscriber
 */
final class ConfigSubscriberTest extends UnitTestCase {

  /**
   * Tests the subscribed events.
   *
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedEvents(): void {
    self::assertEquals(
      [
        ConfigEvents::SAVE => [['onSave']],
      ],
      ConfigSubscriber::getSubscribedEvents()
    );
  }

  /**
   * Tests cache tag and container invalidation.
   *
   * @param string $config_name
   *   The config name.
   * @param array{string, mixed} $original_data
   *   The original config data.
   * @param array{string, mixed} $data
   *   The config data.
   * @param bool $should_invalidate
   *   Whether the container should have been invalidated.
   *
   * @dataProvider shouldInvalidateData
   *
   * @covers ::onSave
   * @covers ::destruct
   */
  public function testShouldInvalidate(string $config_name, array $original_data, array $data, bool $should_invalidate): void {
    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->expects($should_invalidate ? $this->once() : $this->never())
      ->method('rebuildContainer');

    $cache_tags_invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cache_tags_invalidator->expects($should_invalidate ? $this->once() : $this->never())
      ->method('invalidateTags')
      ->with(['http_response']);

    $sut = new ConfigSubscriber($kernel, $cache_tags_invalidator);

    $config = new Config(
      $config_name,
      $this->createMock(StorageInterface::class),
      $this->createMock(EventDispatcher::class),
      $this->createMock(TypedConfigManagerInterface::class)
    );
    // Ensure the original data is populated.
    $config->initWithData($original_data);
    $config->setData($data);

    $event = new ConfigCrudEvent($config);
    $sut->onSave($event);
    $sut->destruct();
  }

  /**
   * Invalidation test data.
   *
   * @return \Generator
   *   The test data.
   */
  public static function shouldInvalidateData(): \Generator {
    yield 'cors_ui.configuration changed' => [
      'cors_ui.configuration',
      [],
      ['foo' => 'bar'],
      TRUE,
    ];
    yield 'cors_ui.configuration unchanged' => [
      'cors_ui.configuration',
      [],
      [],
      FALSE,
    ];
    yield 'random config ignored' => [
      'foobar.baz',
      ['bar' => FALSE],
      ['bar' => TRUE],
      FALSE,
    ];
  }

}
