<?php

declare(strict_types=1);

namespace Drupal\guided_tour;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Tabla de listado de tours en /admin/config/user-interface/guided-tour.
 */
class GuidedTourListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label'   => $this->t('Nombre'),
      'id'      => $this->t('ID'),
      'routes'  => $this->t('Rutas'),
      'roles'   => $this->t('Roles'),
      'status'  => $this->t('Estado'),
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
      'routes' => empty($routes) ? $this->t('Todas') : implode(', ', $routes),
      'roles'  => empty($roles) ? $this->t('Todos') : implode(', ', $roles),
      'status' => $entity->status() ? $this->t('Habilitado') : $this->t('Deshabilitado'),
    ] + parent::buildRow($entity);
  }

}
