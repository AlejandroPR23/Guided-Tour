<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting a guided tour entity.
 */
class GuidedTourDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('¿Eliminar el tour <em>@label</em>?', ['@label' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.guided_tour.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Eliminar');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void { //phpcs:ignore
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Tour <em>@label</em> eliminado.', ['@label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
