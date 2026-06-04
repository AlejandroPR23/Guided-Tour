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
  public function render(): array {
    $build = parent::render();

    $build['table']['#attributes']['data-tour'] = 'guided-tour-list';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'label'   => [
        'data'       => $this->t('Name', [], ['context' => 'guided_tour']),
        'data-tour'  => 'col-label',
      ],
      'id'      => $this->t('ID'),
      'routes'  => [
        'data'       => $this->t('Routes', [], ['context' => 'guided_tour']),
        'data-tour'  => 'col-routes',
      ],
      'roles'   => [
        'data'       => $this->t('Roles', [], ['context' => 'guided_tour']),
        'data-tour'  => 'col-roles',
      ],
      'status'  => [
        'data'       => $this->t('Status', [], ['context' => 'guided_tour']),
        'data-tour'  => 'col-status',
      ],
    ];

    $parent_header = parent::buildHeader();
    if (isset($parent_header['operations'])) {
      $parent_header['operations'] = [
        'data'       => $parent_header['operations'],
        'data-tour'  => 'col-operations',
      ];
    }

    return $header + $parent_header;
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
