<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Bloque con un botón para relanzar el tour de la página actual.
 *
 * Colócalo en cualquier región del tema (header, sidebar, footer…).
 * Solo aparece en las páginas donde hay un tour configurado.
 */
#[Block(
  id: 'guided_tour_trigger',
  admin_label: new TranslatableMarkup('Botón de tour guiado'),
  category: new TranslatableMarkup('Guided Tour'),
)]
class TourTriggerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'button_label' => '¿Necesitas ayuda? Ver tour',
      'button_style' => 'button',
      'icon'         => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->configuration;

    $form['button_label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Texto del botón'),
      '#default_value' => $config['button_label'],
      '#required'      => TRUE,
    ];

    $form['button_style'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Estilo visual'),
      '#options'       => [
        'button' => $this->t('Botón primario'),
        'link'   => $this->t('Enlace'),
        'fab-derecha'    => $this->t('Botón flotante (FAB)'),
        'fab-izquierda'  => $this->t('Botón flotante (FAB) - izquierda'),
      ],
      '#default_value' => $config['button_style'],
    ];

    $form['icon'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Mostrar icono de ayuda'),
      '#default_value' => $config['icon'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {//phpcs:ignore
    $this->configuration['button_label'] = $form_state->getValue('button_label');
    $this->configuration['button_style'] = $form_state->getValue('button_style');
    $this->configuration['icon']         = (bool) $form_state->getValue('icon');
  }

  /**
   * {@inheritdoc}
   *
   * El bloque solo se renderiza cuando drupalSettings.guidedTour existe,
   * es decir cuando hay un tour activo para esta página y usuario.
   * El JS se encarga de mostrarlo/ocultarlo según corresponda.
   */
  public function build(): array {
    $config = $this->configuration;
    $style  = $config['button_style'];
    $label  = $config['button_label'];
    $icon   = $config['icon'] ? '<span class="guided-tour-trigger__icon" aria-hidden="true">?</span>' : '';

    $classes = ['guided-tour-trigger', 'guided-tour-trigger--' . $style];
    if (str_starts_with($style, 'fab')) {
      $classes[] = 'guided-tour-trigger--fab';
    }
    
    return [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#attributes' => [
        'class'           => $classes,
        'id'              => 'guided-tour-trigger',
        'style'           => 'display: none;',
        'aria-hidden'     => 'true',
      ],
      'button' => [
        '#type'       => 'html_tag',
        '#tag'        => 'button',
        '#value'      => $icon . '<span class="guided-tour-trigger__label">' . $label . '</span>',
        '#attributes' => [
          'type'         => 'button',
          'class'        => ['guided-tour-trigger__btn'],
          'id'           => 'guided-tour-replay-btn',
          'aria-label'   => $label,
        ],
      ],
      '#attached' => [
        'library' => ['guided_tour/guided_tour'],
      ],
      '#cache' => [
        'contexts' => ['route', 'user.roles'],
      ],
    ];
  }

}
