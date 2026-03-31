<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block with a button to relaunch the current page's tour.
 *
 * Place it in any theme region (header, sidebar, footer…).
 * Only appears on pages where a tour is configured.
 */
#[Block(
  id: 'guided_tour_trigger',
  admin_label: new TranslatableMarkup('Guided Tour Trigger'),
  category: new TranslatableMarkup('Guided Tour'),
)]
class TourTriggerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'button_label' => 'Need help? Take the tour!',
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
      '#title'         => $this->t('Button text'),
      '#default_value' => $config['button_label'],
      '#required'      => TRUE,
    ];

    $form['button_style'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Visual style'),
      '#options'       => [
        'button' => $this->t('Primary button'),
        'link'   => $this->t('Link'),
        'fab-derecha'    => $this->t('Floating action button (FAB)'),
        'fab-izquierda'  => $this->t('Floating action button (FAB) - left'),
      ],
      '#default_value' => $config['button_style'],
    ];

    $form['icon'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show icon of question mark'),
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
   * The block is only rendered when drupalSettings.guidedTour exists,
   * i.e. when there is an active tour for this page and user.
   * The JS handles showing/hiding it as appropriate.
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
