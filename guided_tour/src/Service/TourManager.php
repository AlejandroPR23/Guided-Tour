<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\guided_tour\Entity\GuidedTourInterface;

/**
 * Resolves which tour to display for a given route and user.
 *
 * Reads from Config Entities.
 *
 * (editable from the admin UI).
 */
class TourManager {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Returns the GuidedTour entity that applies, or NULL if none.
   */
  public function getTourForRouteAndUser(string $route_name, AccountInterface $account): ?GuidedTourInterface {
    $role    = $this->getPrimaryRole($account);
    $storage = $this->entityTypeManager->getStorage('guided_tour');

    /** @var \Drupal\guided_tour\Entity\GuidedTourInterface[] $tours */
    $tours = $storage->loadByProperties(['status' => TRUE]);

    foreach ($tours as $tour) {
      $routes = $tour->getRoutes();
      if (!empty($routes) && !in_array($route_name, $routes, TRUE)) {
        continue;
      }

      foreach ($tour->getRouteParams() as $param => $value) {
        $actual = $this->routeMatch->getRawParameter($param);
        if ((string) $actual !== (string) $value) {
          continue 2;
        }
      }

      $bundle_filter = $tour->getBundleFilter();
      if (!empty($bundle_filter['entity_type']) && !empty($bundle_filter['bundle'])) {
        $entity_type = $bundle_filter['entity_type'];
        $bundle      = $bundle_filter['bundle'];

        /** @var \Drupal\Core\Entity\EntityInterface|null $entity */
        $entity = $this->routeMatch->getParameter($entity_type);

        if (!$entity || !method_exists($entity, 'bundle') || $entity->bundle() !== $bundle) {
          continue;
        }
      }

      $allowed = $tour->getRoles();
      if (!empty($allowed) && !in_array($role, $allowed, TRUE)) {
        continue;
      }

      return $tour;
    }

    return NULL;
  }

  /**
   * Determines the "primary" role of the user.
   *
   * To use in the tours logic.
   *
   * If they have multiple roles, the one with higher priority is assigned.
   */
  protected function getPrimaryRole(AccountInterface $account): string {
    if ($account->isAnonymous()) {
      return 'anonymous';
    }
    $priority = ['administrator', 'editor', 'content_manager', 'authenticated'];
    foreach ($priority as $role) {
      if (in_array($role, $account->getRoles(), TRUE)) {
        return $role;
      }
    }
    return 'authenticated';
  }

}
