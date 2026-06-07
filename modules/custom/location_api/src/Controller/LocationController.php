<?php

namespace Drupal\location_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;

class LocationController extends ControllerBase {

  public function search(Request $request) {
    $search = $request->query->get('search');

    if (!$search) {
      return new JsonResponse([]);
    }

    $database = \Drupal::database();

    $query = $database->select('locations', 'l')
      ->fields('l', ['city_name'])
      ->condition('city_name', $search . '%', 'LIKE')
      ->range(0, 10);

    $results = $query->execute()->fetchAll();

    // Format clean response
    $data = [];
    foreach ($results as $row) {
      $data[] = [
        'city_name' => $row->city_name,
      ];
    }

    return new JsonResponse($data);
  }
}