<?php

namespace Drupal\client_inquiry\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;

class ClientInquiryController extends ControllerBase {

  public function submit(Request $request) {

    try {

      $data = json_decode($request->getContent(), TRUE);

      $name = $data['name'] ?? '';
      $number = $data['number'] ?? '';
      $category = $data['category'] ?? '';
      $event_date = $data['event_date'] ?? '';
      $event_time = $data['event_time'] ?? '';
      $description = $data['description'] ?? '';
      $order_amount = $data['order_amount'] ?? 0;

      if (
        empty($name) ||
        empty($number) ||
        empty($event_date) ||
        empty($event_time)
      ) {
        return new JsonResponse([
          'message' => 'Required fields missing'
        ], 400);
      }

      /**
       * Client ID Format:
       * 2026-06-CL-001
       */

      $yearMonth = date('Y-m');

      $connection = Database::getConnection();

      $count = $connection
        ->select('node_field_data', 'n')
        ->condition('type', 'client_inquiry')
        ->countQuery()
        ->execute()
        ->fetchField();

      $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

      $client_id = $yearMonth . '-CL-' . $sequence;

      $node = Node::create([
        'type' => 'client_inquiry',

        'title' => $client_id,

        'field_client_id' => $client_id,

        'field_client_name' => $name,

        'field_client_contact' => $number,

        'field_inquiry_category' => [
          'value' => $category,
        ],

        'field_event_date' => $event_date,

        'field_event_time' => $event_time,

        'body' => [
          'value' => $description,
          'format' => 'basic_html',
        ],

        'field_booking_status' => [
          'value' => 'pending',
        ],

        'field_payment_status' => [
          'value' => 'pending',
        ],

        'field_order_amount' => $order_amount,

        'status' => 1,
      ]);

      $node->save();

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Inquiry submitted successfully',
        'client_id' => $client_id,
        'nid' => $node->id(),
      ]);

    }
    catch (\Exception $e) {

      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 500);

    }

  }

}