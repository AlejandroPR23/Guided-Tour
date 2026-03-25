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
 * Formulario de creación/edición de un tour guiado.
 *
 * Los campos básicos (rutas, roles, opciones) se editan con controles de form.
 * Los pasos del tour se editan como YAML — es el formato más legible para
 * estructuras anidadas complejas y no requiere un widget de arrastre complejo.
 */
class GuidedTourForm extends EntityForm {

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
      '#title'         => $this->t('Nombre del tour'),
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
      '#title'         => $this->t('Habilitado'),
      '#default_value' => $tour->status(),
    ];

    // ── Rutas ─────────────────────────────────────────────────────────────
    $form['routing'] = [
      '#type'  => 'details',
      '#title' => $this->t('Rutas y condiciones'),
      '#open'  => TRUE,
    ];

    $form['routing']['routes_text'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Rutas de Drupal'),
      '#description'   => $this->t(
        'Una ruta por línea. Usa el nombre interno de ruta de Drupal, ej: <code>entity.node.canonical</code>, <code>&lt;front&gt;</code>. Deja vacío para todas las rutas.'
      ),
      '#default_value' => $this->routeParamsToText($tour->getRoutes()),
      '#rows'          => 4,
    ];

    $form['routing']['route_params_text'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Parámetros de ruta'),
      '#description'   => $this->t(
        'Un parámetro por línea en formato <code>clave: valor</code>. Ej: <code>node: 2707</code>. Deja vacío para no filtrar por parámetro.'
      ),
      '#default_value' => $this->routeParamsToText($tour->getRouteParams()),
      '#rows'          => 3,
    ];

    // ── Roles ─────────────────────────────────────────────────────────────
    $roles        = $this->roleStorage->loadMultiple();
    $role_options = ['anonymous' => $this->t('Anónimo')];
    foreach ($roles as $role_id => $role) {
      if (!in_array($role_id, ['anonymous'], TRUE)) {
        $role_options[$role_id] = $role->label();
      }
    }

    $form['routing']['roles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Roles que ven el tour'),
      '#description'   => $this->t('Deja todo sin marcar para mostrar a todos los roles.'),
      '#options'       => $role_options,
      '#default_value' => $tour->getRoles(),
    ];

    // ── Opciones de comportamiento ─────────────────────────────────────────
    $form['behavior'] = [
      '#type'  => 'details',
      '#title' => $this->t('Comportamiento'),
      '#open'  => TRUE,
    ];

    $form['behavior']['cookie_days'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Días de dismissal'),
      '#description'   => $this->t('Cuántos días se recuerda que el usuario ya vio el tour (cookie). Usa 0 para no recordar.'),
      '#default_value' => $tour->getCookieDays(),
      '#min'           => 0,
      '#max'           => 3650,
    ];

    $form['behavior']['wait_for_wc'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Esperar a Web Components'),
      '#description'   => $this->t('Activa si tus elementos son Web Components (Storybook/Lit). El tour esperará a que hagan upgrade antes de iniciar.'),
      '#default_value' => $tour->isWaitForWc(),
    ];

    $options = $tour->getOptions();
    $form['behavior']['use_modal_overlay'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Usar overlay oscuro'),
      '#description'   => $this->t('Muestra un overlay semitransparente resaltando el elemento activo.'),
      '#default_value' => $options['useModalOverlay'] ?? TRUE,
    ];

    // ── Pasos del tour ─────────────────────────────────────────────────────
    $form['steps_wrapper'] = [
      '#type'  => 'details',
      '#title' => $this->t('Pasos del tour'),
      '#open'  => TRUE,
    ];

    $form['steps_wrapper']['steps_yaml'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Pasos (formato YAML)'),
      '#description'   => $this->t(
        'Define los pasos en YAML. Cada paso admite: <code>id</code>, <code>title</code>, <code>text</code>, <code>attachTo</code> (element + on), <code>buttons</code> (type: next/back/cancel). <a href="#yaml-help">Ver ejemplo</a>.'
      ),
      '#default_value' => $this->stepsToYaml($tour->getSteps()),
      '#rows'          => 20,
      '#attributes'    => ['class' => ['guided-tour-steps-yaml'], 'style' => 'font-family: monospace;'],
    ];

    // Ejemplo colapsado como referencia rápida.
    $form['steps_wrapper']['yaml_help'] = [
      '#type'   => 'details',
      '#title'  => $this->t('Ejemplo de pasos YAML'),
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
          $form_state->setErrorByName('steps_yaml', $this->t('El YAML debe ser una lista de pasos.'));
        }
      }
      catch (ParseException $e) {
        $form_state->setErrorByName('steps_yaml', $this->t('YAML inválido: @error', ['@error' => $e->getMessage()]));
      }
    }

    // Validar YAML de route_params.
    $params_text = $form_state->getValue('route_params_text') ?? '';
    if (!empty(trim($params_text))) {
      try {
        Yaml::parse($params_text);
      }
      catch (ParseException $e) {
        $form_state->setErrorByName('route_params', $this->t('Parámetros inválidos: @error', ['@error' => $e->getMessage()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\guided_tour\Entity\GuidedTour $tour */
    $tour = $this->entity;

    // Procesar rutas (una por línea → array).
    $routes_raw = $form_state->getValue('routes_text', '');
    $tour->set('routes', array_filter(array_map('trim', explode("\n", $routes_raw))));

    // Procesar route_params (YAML inline → array).
    $params_raw = $form_state->getValue('route_params_text', '');
    $tour->set('route_params', !empty($params_raw) ? (Yaml::parse($params_raw) ?? []) : []);

    // Procesar roles (filtrar los no marcados).
    $roles = array_filter($form_state->getValue('roles', []));
    $tour->set('roles', array_values($roles));

    // Opciones de Shepherd.
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
        ? $this->t('Tour <em>@label</em> creado correctamente.', ['@label' => $tour->label()])
        : $this->t('Tour <em>@label</em> actualizado correctamente.', ['@label' => $tour->label()])
    );

    $form_state->setRedirectUrl($tour->toUrl('collection'));

    return $status;
  }

  /**
   * ── Helpers ───────────────────────────────────────────────────────────────
   */
  private function routeParamsToText(array $params): string {
    if (empty($params)) {
      return '';
    }
    return Yaml::dump($params, 1);
  }

  /**
   * Convierte los pasos a YAML.
   */
  private function stepsToYaml(array $steps): string {
    if (empty($steps)) {
      return $this->getYamlExample();
    }
    return Yaml::dump($steps, 4, 2);
  }

  /**
   * Ejemplo de pasos en YAML para mostrar como referencia en el formulario.
   */
  private function getYamlExample(): string {
    return <<<YAML
- id: paso-1
  title: 'Título del primer paso'
  text: 'Descripción breve de lo que el usuario ve aquí.'
  attachTo:
    element: '[data-tour="mi-componente"]'
    on: bottom
  buttons:
    - text: 'Siguiente'
      type: next
    - text: 'Omitir tour'
      type: cancel

- id: paso-2
  title: 'Segundo paso'
  text: 'Más información sobre esta sección.'
  attachTo:
    element: '[data-tour="otro-componente"]'
    on: right
  buttons:
    - text: 'Anterior'
      type: back
    - text: '¡Listo!'
      type: next
YAML;
  }

}
