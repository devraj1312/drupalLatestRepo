<?php

namespace Drupal\cors_ui\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the behavior of the core cors middleware.
 */
class CorsConfigurationForm extends ConfigFormBase {

  /**
   * A new-line character.
   */
  const NEW_LINE = "\n";

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('cors_ui.configuration');
    $form['more_info'] = [
      '#markup' => $this->t('<p>For more information about Cross-Origin Resource Sharing (CORS), visit the <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS">MDN Web Docs CORS documentation</a>.</p>'),
    ];
    $form['configuration'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['configuration']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Cross-Origin Resource Sharing (CORS)'),
      '#default_value' => $config->get('enabled'),
    ];
    $form['configuration']['maxAge'] = [
      '#type' => 'number',
      '#title' => $this->t('Max age'),
      '#default_value' => $config->get('maxAge'),
      '#description' => $this->t('Access-Control-Max-Age gives the value in seconds for how long the response to the preflight request can be cached for without sending another preflight request.  In this case, 86400 seconds is 24 hours.  Note that each browser has a maximum internal value that takes precedence when the Access-Control-Max-Age is greater.'),
    ];
    $form['configuration']['supportsCredentials'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Supports credentials'),
      '#default_value' => $config->get('supportsCredentials'),
      '#description' => $this->t('When making cross-site cross-site XMLHttpRequest invocations, browsers will not send credentials. A specific flag has to be set on the XMLHttpRequest object when it is invoked. When this flag is set, the browser will reject any response that does not have the Access-Control-Allow-Credentials: true header, and not make the response available to the invoking web content.'),
    ];
    $form['configuration']['allowedHeaders'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed headers'),
      '#default_value' => $config->get('allowedHeaders'),
      '#value_callback' => [static::class, 'textareaNewLinesToArray'],
      '#description' => $this->t('Used in response to a preflight request to indicate which HTTP headers can be used when making the actual request. One value per line, or you may use "*" to allow all.'),
    ];
    $form['configuration']['allowedMethods'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed methods'),
      '#value_callback' => [static::class, 'textareaNewLinesToArray'],
      '#default_value' => $config->get('allowedMethods'),
      '#description' => $this->t('Specifies the method or methods allowed when accessing the resource.  This is used in response to a preflight request. One value per line, or you may use "*" to allow all.'),
    ];
    $form['configuration']['allowedOrigins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed origins'),
      '#value_callback' => [static::class, 'textareaNewLinesToArray'],
      '#default_value' => $config->get('allowedOrigins'),
      '#description' => $this->t('Specifies a URI that may access the resource. For requests without credentials, the server may specify "*" as a wildcard, thereby allowing any origin to access the resource. One value per line, or you may use "*" to allow all.'),
    ];
    $form['configuration']['exposedHeaders'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exposed headers'),
      '#value_callback' => [static::class, 'textareaNewLinesToArray'],
      '#default_value' => $config->get('exposedHeaders'),
      '#description' => $this->t('This header lets a server whitelist headers that browsers are allowed to access. One value per line, or you may use "*" to allow all.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cors_ui.configuration', 'cors_ui.status'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'cors_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    /** @var string[] $allowed_origins */
    $allowed_origins = &$form_state->getValue(['configuration', 'allowedOrigins'], []);
    if ($allowed_origins !== ['*']) {
      if (in_array('*', $allowed_origins, TRUE)) {
        $form_state->setError(
          $form['configuration']['allowedOrigins'],
          $this->t('Cannot use wildcard "*" with other origins.')
        );
      }
      else {
        $invalid_origins = [];
        foreach ($allowed_origins as $allowed_origin) {
          if (!UrlHelper::isValid($allowed_origin, TRUE)) {
            $invalid_origins[] = $allowed_origin;
          }
          else {
            $parsed = parse_url($allowed_origin);
            if (isset($parsed['path']) || isset($parsed['query']) || isset($parsed['fragment'])) {
              $invalid_origins[] = $allowed_origin;
            }
          }
        }
        if (count($invalid_origins) > 0) {
          $form_state->setError(
            $form['configuration']['allowedOrigins'],
            $this->formatPlural(
              count($invalid_origins),
              'The origin @origin is not a valid origin value (scheme, hostname, and port.)',
              'The origins @origin are not valid origin values (scheme, hostname, and port.)',
              [
                '@origin' => implode(', ', $invalid_origins),
              ]
            )
          );
        }
      }
    }
    // If there are errors, the value is not converted back into a string.
    if (FormState::hasAnyErrors()) {
      $form['configuration']['allowedOrigins']['#value'] = implode("\n", $allowed_origins);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('cors_ui.configuration');
    $original_values = $config->get();

    $config->setData((array) $form_state->getValue('configuration'))->save();

    $new_values = $config->get();
    if ($new_values == $original_values) {
      parent::submitForm($form, $form_state);
    }
    else {
      $this->messenger()->addStatus($this->t('The configuration options have been saved. The dependency injection container has been rebuilt with the new configuration'));
    }
  }

  /**
   * An element value callback to convert between newlines and arrays.
   *
   * @param array $element
   *   The element.
   * @param string|false $input
   *   The input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|array
   *   The value.
   */
  public static function textareaNewLinesToArray($element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      return !empty($input) ? explode(static::NEW_LINE, static::normalizeLineEndings(trim($input))) : [];
    }
    if (!empty($element['#default_value']) && is_array($element['#default_value'])) {
      return implode(static::NEW_LINE, $element['#default_value']);
    }
    return [];
  }

  /**
   * Normalize line endings.
   *
   * @param string $input
   *   The input string.
   *
   * @return string
   *   A normalized string.
   */
  protected static function normalizeLineEndings($input) {
    return str_replace(["\r\n", "\r"], static::NEW_LINE, $input);
  }

}
