<?php

namespace Drupal\Tests\cors_ui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\cors_ui\CorsUiConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * The CORS UI test.
 *
 * @group cors_ui
 */
class CorsUiTest extends KernelTestBase {

  /**
   * The initials CORS configuration.
   *
   * @var array
   */
  protected $initialCorsConfig;

  /**
   * The config key where cors settings are stored.
   *
   * @var string
   */
  protected $configKey = 'cors_ui.configuration';

  /**
   * The config form class.
   *
   * @var string
   */
  protected $formClass = '\Drupal\cors_ui\Form\CorsConfigurationForm';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'cors_ui'];

  /**
   * Test the CORS UI.
   */
  public function testCorsUi(): void {

    $cors_parameter = $this->container->getParameter('cors.config');
    $this->assertTrue(is_array($cors_parameter) && count($cors_parameter) > 0);
    // @todo remove after https://github.com/mglaman/phpstan-drupal/issues/668
    // @phpstan-ignore-next-line
    $this->assertFalse($this->container->has('http_middleware.cors'));

    // Install and run the install hook for the cors_ui module.
    $this->enableModules(['cors_ui']);
    \Drupal::moduleHandler()->loadInclude('cors_ui', 'install');
    cors_ui_install();
    $this->container->get('cors_ui.config_subscriber')->destruct();
    $this->assertFalse($this->container->has('http_middleware.cors'));

    // The state of the config object on install should match what appears in
    // the container.
    $this->assertSame(CorsUiConfig::normalize($cors_parameter), $this->config($this->configKey)->get());

    $form_state = (new FormState())->setValues([
      'configuration' => [
        'enabled' => TRUE,
        'maxAge' => 500,
        'supportsCredentials' => FALSE,
        'allowedHeaders' => "foo\r\nbar",
        'allowedMethods' => "bar\nbaz",
        'allowedOrigins' => 'baz',
        'exposedHeaders' => 'qux',
      ],
    ]);
    $this->container->get('form_builder')->submitForm($this->formClass, $form_state);

    $form_errors = $form_state->getErrors();
    self::assertCount(1, $form_errors);
    self::assertArrayHasKey('configuration][allowedOrigins', $form_errors);
    self::assertEquals('The origin baz is not a valid origin value (scheme, hostname, and port.)', $form_errors['configuration][allowedOrigins']);

    $form_state = (new FormState())->setValues([
      'configuration' => [
        'enabled' => TRUE,
        'maxAge' => 500,
        'supportsCredentials' => FALSE,
        'allowedHeaders' => "foo\r\nbar",
        'allowedMethods' => "bar\nbaz",
        'allowedOrigins' => 'https://baz',
        'exposedHeaders' => 'qux',
      ],
    ]);
    $this->container->get('form_builder')->submitForm($this->formClass, $form_state);

    $expected_config = [
      'enabled' => TRUE,
      'maxAge' => 500,
      'supportsCredentials' => FALSE,
      'allowedHeaders' => ['foo', 'bar'],
      'allowedMethods' => ['bar', 'baz'],
      'allowedOrigins' => ['https://baz'],
      'exposedHeaders' => ['qux'],
    ];
    $this->assertEquals($expected_config, $this->config($this->configKey)->get());

    $this->container->get('cors_ui.config_subscriber')->destruct();
    $this->assertEquals($expected_config, $this->container->getParameter('cors.config'));
    $this->assertTrue($this->container->has('http_middleware.cors'));
  }

}
