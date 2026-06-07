<?php

namespace Drupal\cors_ui;

/**
 * Helper class to make correct types out of default parameters.
 */
class CorsUiConfig {

  /**
   * Replaces booleans in config properties with their matching types.
   */
  public static function normalize(array $config): array {
    if (isset($config['exposedHeaders']) && $config['exposedHeaders'] === FALSE) {
      $config['exposedHeaders'] = [];
    }
    if (isset($config['maxAge']) && $config['maxAge'] === FALSE) {
      unset($config['maxAge']);
    }
    return $config;
  }

}
