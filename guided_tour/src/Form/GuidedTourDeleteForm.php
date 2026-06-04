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
    return $this->t('Delete the tour <em>@label</em>?', ['@label' => $this->entity->label()],['context' => 'guided_tour']);
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
    return $this->t('Delete', [], ['context' => 'guided_tour']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void { //phpcs:ignore
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Tour <em>@label</em> deleted.', ['@label' => $this->entity->label()], ['context' => 'guided_tour']));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
