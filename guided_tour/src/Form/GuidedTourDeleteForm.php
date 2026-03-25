<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Confirmación antes de eliminar un tour.
 */
class GuidedTourDeleteForm extends EntityConfirmFormBase {

  /**
   *
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('¿Eliminar el tour <em>@label</em>?', ['@label' => $this->entity->label()]);
  }

  /**
   *
   */
  public function getCancelUrl(): Url {
    return new Url('entity.guided_tour.collection');
  }

  /**
   *
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Eliminar');
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Tour <em>@label</em> eliminado.', ['@label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
