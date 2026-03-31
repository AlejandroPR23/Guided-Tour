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
      'label'   => $this->t('Name'),
      'id'      => $this->t('ID'),
      'routes'  => $this->t('Routes'),
      'roles'   => $this->t('Roles'),
      'status'  => $this->t('Status'),
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
      'routes' => empty($routes) ? $this->t('All') : implode(', ', $routes),
      'roles'  => empty($roles) ? $this->t('All') : implode(', ', $roles),
      'status' => $entity->status() ? $this->t('Enabled') : $this->t('Disabled'),
    ] + parent::buildRow($entity);
  }

}
