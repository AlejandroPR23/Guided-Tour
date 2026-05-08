<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\RoleStorageInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Provides the creation and editing form for a guided tour.
 *
 * Basic fields (routes, roles, options) are edited with standard form controls.
 * Tour steps are edited as YAML — it is the most readable format for
 * complex nested structures.
 */
class GuidedTourForm extends EntityForm {

  /**
   * Constructs a new GuidedTourForm.
   *
   * @param \Drupal\user\RoleStorageInterface $roleStorage
   *   The role storage.
   */
  public function __construct(
    protected RoleStorageInterface $roleStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')->getStorage('user_role')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\guided_tour\Entity\GuidedTour $tour */
    $tour = $this->entity;

    // ── Información básica ────────────────────────────────────────────────
    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Name of the tour'),
      '#default_value' => $tour->label(),
      '#required'      => TRUE,
      '#maxlength'     => 128,
    ];

    $form['id'] = [
      '#type'          => 'machine_name',
      '#default_value' => $tour->id(),
      '#machine_name'  => [
        'exists' => '\Drupal\guided_tour\Entity\GuidedTour::load',
        'source' => ['label'],
      ],
      '#disabled'      => !$tour->isNew(),
    ];

    $form['status'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enabled'),
      '#default_value' => $tour->status(),
    ];

    // ── Rutas ─────────────────────────────────────────────────────────────
    $form['routing'] = [
      '#type'  => 'details',
      '#title' => $this->t('Routes and access conditions'),
      '#open'  => TRUE,
    ];

    $form['routing']['routes_text'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Routes of Drupal'),
      
      '#description'   => $this->t(
        'One route for line. Use the internal route name of Drupal, e.g. <code>entity.node.canonical</code>, <code>&lt;front&gt;</code>. Leave empty for all routes.'
      ),
      '#default_value' => implode("\n", $tour->getRoutes()),
      '#rows'          => 4,
    ];

    $form['routing']['route_params_text'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Route parameters'),
      '#description'   => $this->t(
        'One parameter per line in <code>key: value</code> format. E.g. <code>node: 2707</code>. Leave empty to not filter by parameter.'
      ),
      '#default_value' => $this->routeParamsToText($tour->getRouteParams()),
      '#rows'          => 3,
    ];

    // ── Roles ─────────────────────────────────────────────────────────────
    $roles        = $this->roleStorage->loadMultiple();
    $role_options = ['anonymous' => $this->t('Anonymous')];
    foreach ($roles as $role_id => $role) {
      if (!in_array($role_id, ['anonymous'], TRUE)) {
        $role_options[$role_id] = $role->label();
      }
    }

    $form['routing']['roles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Roles that see the tour'),
      '#description'   => $this->t('Leave all unchecked to show to all roles.'),
      '#options'       => $role_options,
      '#default_value' => $tour->getRoles(),
    ];

    $bundle_filter = $tour->getBundleFilter();
    $form['bundle'] = [
  '#type'  => 'details',
  '#title' => $this->t('Filter by content type (bundle)'),
  '#open'  => !empty($bundle_filter),
];

$form['bundle']['bundle_entity_type'] = [
  '#type'          => 'textfield',
  '#title'         => $this->t('Entity type'),
  '#description'   => $this->t(
    'Internal entity type machine name. Use <code>node</code> for content, <code>taxonomy_term</code> for terms, etc.'
  ),
  '#default_value' => $bundle_filter['entity_type'] ?? '',
  '#placeholder'   => 'node',
];

$form['bundle']['bundle_name'] = [
  '#type'          => 'textfield',
  '#title'         => $this->t('Bundle'),
  '#description'   => $this->t(
    'Machine name of the bundle. E.g. <code>course</code>, <code>article</code>, <code>tags</code>.'
  ),
  '#default_value' => $bundle_filter['bundle'] ?? '',
  '#placeholder'   => 'course',
  '#states'        => [
    'visible' => [
      ':input[name="bundle_entity_type"]' => ['filled' => TRUE],
    ],
  ],
];

    // ── Opciones de comportamiento ─────────────────────────────────────────
    $form['behavior'] = [
      '#type'  => 'details',
      '#title' => $this->t('Behavior'),
      '#open'  => TRUE,
    ];

    $form['behavior']['cookie_days'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Dismissal days'),
      '#description'   => $this->t('How many days to remember that the user has seen the tour (cookie). Use 0 to not remember.'),
      '#default_value' => $tour->getCookieDays(),
      '#min'           => 0,
      '#max'           => 3650,
    ];

    $form['behavior']['wait_for_wc'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Wait for Web Components'),
      '#description'   => $this->t('Activate if your elements are Web Components (Storybook/Lit). The tour will wait for them to upgrade before starting.'),
      '#default_value' => $tour->isWaitForWc(),
    ];

    $options = $tour->getOptions();
    $form['behavior']['use_modal_overlay'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use dark overlay'),
      '#description'   => $this->t('Shows a semi-transparent overlay highlighting the active element.'),
      '#default_value' => $options['useModalOverlay'] ?? TRUE,
    ];

    // ── Pasos del tour ─────────────────────────────────────────────────────
    $form['steps_wrapper'] = [
      '#type'  => 'details',
      '#title' => $this->t('Steps of the tour'),
      '#open'  => TRUE,
    ];

    $form['steps_wrapper']['steps_yaml'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Steps (YAML format)'),
      '#description'   => $this->t(
        'Define the steps in YAML. Each step supports: <code>id</code>, <code>title</code>, <code>text</code>, <code>attachTo</code> (element + on), <code>buttons</code> (type: next/back/cancel). <a href="#yaml-help">View example</a>.'
      ),
      '#default_value' => $this->stepsToYaml($tour->getSteps()),
      '#rows'          => 20,
      '#attributes'    => ['class' => ['guided-tour-steps-yaml'], 'style' => 'font-family: monospace;'],
    ];

    // Ejemplo colapsado como referencia rápida.
    $form['steps_wrapper']['yaml_help'] = [
      '#type'   => 'details',
      '#title'  => $this->t('YAML Steps Example'),
      '#open'   => FALSE,
      '#id'     => 'yaml-help',
      'example' => [
        '#type'   => 'html_tag',
        '#tag'    => 'pre',
        '#value'  => htmlspecialchars($this->getYamlExample()),
        '#attributes' => ['style' => 'font-size: 12px; background: #f5f5f5; padding: 12px; overflow: auto;'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validar YAML de pasos.
    $yaml = $form_state->getValue('steps_yaml') ?? '';
    if (!empty(trim($yaml))) {
      try {
        $parsed = Yaml::parse($yaml);
        if (!is_array($parsed)) {
          $form_state->setErrorByName('steps_yaml', $this->t('The YAML must be a list of steps.'));
        }
      }
      catch (ParseException $e) {
        $form_state->setErrorByName('steps_yaml', $this->t('Invalid YAML: @error', ['@error' => $e->getMessage()]));
      }
    }

    // Validar YAML de route_params.
    $params_text = $form_state->getValue('route_params_text') ?? '';
    if (!empty(trim($params_text))) {
      try {
        Yaml::parse($params_text);
      }
      catch (ParseException $e) {
        $form_state->setErrorByName(
          'route_params',
          $this->t('Invalid parameters: @error',
          ['@error' => $e->getMessage()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int { //phpcs:ignore
    /** @var \Drupal\guided_tour\Entity\GuidedTour $tour */
    $tour = $this->entity;

    // Procesar rutas (una por línea → array).
    $routes_raw = $form_state->getValue('routes_text', '');
    $tour->set('routes', array_filter(array_map('trim', explode("\n", $routes_raw))));

    // Procesar route_params (YAML inline → array).
    $params_raw = $form_state->getValue('route_params_text', '');
    $tour->set('route_params', !empty($params_raw) ? (Yaml::parse($params_raw) ?? []) : []);

    $entity_type = trim((string) $form_state->getValue('bundle_entity_type'));
    $bundle_name = trim((string) $form_state->getValue('bundle_name'));

    $tour->set('bundle_filter', ($entity_type && $bundle_name)
      ? ['entity_type' => $entity_type, 'bundle' => $bundle_name]
      : []
    );

    // Procesar roles (filtrar los no marcados).
    $roles = array_filter($form_state->getValue('roles', []));
    $tour->set('roles', array_values($roles));

    // Opciones de Driver.js.
    $tour->set('options', [
      'useModalOverlay' => (bool) $form_state->getValue('use_modal_overlay'),
    ]);

    // Pasos desde YAML.
    $steps_yaml = trim((string) $form_state->getValue('steps_yaml', ''));
    $tour->set('steps', !empty($steps_yaml) ? (Yaml::parse($steps_yaml) ?? []) : []);

    $tour->set('cookie_days', (int) $form_state->getValue('cookie_days'));
    $tour->set('wait_for_wc', (bool) $form_state->getValue('wait_for_wc'));

    $status = $tour->save();

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Tour <em>@label</em> created successfully.', ['@label' => $tour->label()])
        : $this->t('Tour <em>@label</em> updated successfully.', ['@label' => $tour->label()])
    );

    $form_state->setRedirectUrl($tour->toUrl('collection'));

    return $status;
  }

  /**
   * Converts route parameters to a YAML string.
   *
   * @param array $params
   *   The route parameters array.
   *
   * @return string
   *   The YAML representation of the parameters.
   */
  private function routeParamsToText(array $params): string {
    if (empty($params)) {
      return '';
    }
    return Yaml::dump($params, 1);
  }

  /**
   * Converts the tour steps array to a YAML string.
   *
   * @param array $steps
   *   The array of steps.
   *
   * @return string
   *   The YAML formatted steps.
   */
  private function stepsToYaml(array $steps): string {
    if (empty($steps)) {
      return $this->getYamlExample();
    }
    return Yaml::dump($steps, 4, 2);
  }

  /**
   * Provides a YAML example of steps for the UI reference.
   *
   * @return string
   *   The YAML example string.
   */
  private function getYamlExample(): string {
    return <<<YAML
- id: step-1
  title: 'Title of the first step'
  text: 'Brief description of what the user sees here.'
  attachTo:
    element: '[data-tour="my-component"]'
    on: bottom
  buttons:
    - text: 'Next'
      type: next
    - text: 'Skip tour'
      type: cancel

- id: step-2
  title: 'Title of the second step'
  text: 'More information about this section.'
  attachTo:
    element: '[data-tour="other-component"]'
    on: right
  buttons:
    - text: 'Previous'
      type: back
    - text: 'Done'
      type: next
YAML;
  }

}
