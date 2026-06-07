<?php

namespace Drupal\cors_ui;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\Compiler\CorsCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The CORS UI compiler pass.
 */
class CorsUiCompilerPass extends CorsCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // The config factory might not be ready yet, so we bypass the container by
    // using the bootstrap factory.
    $config_storage = BootstrapConfigStorageFactory::get();
    // This config ignores overrides, but if people would set CORS options in
    // settings.php files then they would not use cors_ui module.
    $config = $config_storage->read('cors_ui.configuration');

    if (!empty($config)) {
      $container->setParameter('cors.config', $config);
    }
    parent::process($container);
  }

}
