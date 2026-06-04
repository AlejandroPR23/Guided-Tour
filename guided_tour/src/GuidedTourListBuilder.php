<?php

declare(strict_types=1);

namespace Drupal\guided_tour;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Table of listed tours in /admin/config/user-interface/guided-tour.
 */
class GuidedTourListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label'   => $this->t('Name', [], ['context' => 'guided_tour']),
      'id'      => $this->t('ID', [], ['context' => 'guided_tour']),
      'routes'  => $this->t('Routes', [], ['context' => 'guided_tour']),
      'roles'   => $this->t('Roles', [], ['context' => 'guided_tour']),
      'status'  => $this->t('Status', [], ['context' => 'guided_tour']),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\guided_tour\Entity\GuidedTour $entity */
    $roles  = $entity->getRoles();
    $routes = $entity->getRoutes();

    return [
      'label'  => $entity->label(),
      'id'     => $entity->id(),
      'routes' => empty($routes) ? $this->t('All', [], ['context' => 'guided_tour']) : implode(', ', $routes),
      'roles'  => empty($roles) ? $this->t('All', [], ['context' => 'guided_tour']) : implode(', ', $roles),
      'status' => $entity->status() ? $this->t('Enabled', [], ['context' => 'guided_tour']) : $this->t('Disabled', [], ['context' => 'guided_tour']),
    ] + parent::buildRow($entity);
  }

}
