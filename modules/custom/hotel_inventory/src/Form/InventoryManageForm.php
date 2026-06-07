<?php

namespace Drupal\hotel_inventory\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

class InventoryManageForm extends FormBase {

  public function getFormId() {
    return 'hotel_inventory_manage_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'hotel_inventory/inventory_style';
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());

    if (!$user->hasField('field_assigned_hotel') || $user->get('field_assigned_hotel')->isEmpty()) {
      $form['message'] = [
        '#markup' => '<p>No hotel assigned.</p>',
      ];
      return $form;
    }

    $hotel_id = $user->get('field_assigned_hotel')->target_id;

    $hotel = Node::load($hotel_id);

    $hotel_custom_id = '';
    $hotel_name = '';
    $hotel_address = '';
    $hotel_contact = '';

    if ($hotel) {
    $hotel_custom_id = $hotel->get('field_hotel_id')->value ?? 'N/A';
    $hotel_name = $hotel->getTitle();
    $hotel_address = $hotel->get('field_location')->value ?? 'N/A';
    $hotel_contact = $hotel->get('field_hotel_contact')->value ?? 'N/A';
    }

    $form['hotel_info'] = [
    '#type' => 'container',
    '#attributes' => [
        'class' => ['hotel-info-box'],
    ],
    ];

    $form['hotel_info']['hotel_id'] = [
    '#markup' => '<p><strong>Hotel ID:</strong> ' . $hotel_custom_id . '</p>',
    ];

    $form['hotel_info']['hotel_name'] = [
    '#markup' => '<p><strong>Hotel Name:</strong> ' . $hotel_name . '</p>',
    ];

    $form['hotel_info']['hotel_contact'] = [
    '#markup' => '<p><strong>Hotel Contact:</strong> ' . $hotel_contact . '</p>',
    ];

    $form['hotel_info']['hotel_address'] = [
    '#markup' => '<p><strong>Hotel Address:</strong> ' . $hotel_address . '</p>',
    ];

    $room_ids = \Drupal::entityQuery('node')
      ->condition('type', 'rooms')
      ->condition('field_hotel_reference.target_id', $hotel_id)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($room_ids)) {
      $form['message'] = [
        '#markup' => '<p>No rooms found.</p>',
      ];
      return $form;
    }

    $rooms = Node::loadMultiple($room_ids);

    foreach ($rooms as $room) {
      $rid = $room->id();

      $total = (int) $room->get('field_total_rooms')->value;
      $booked = (int) $room->get('field_booked_rooms')->value;
      $available = (int) $room->get('field_available_rooms')->value;

      $form['room_' . $rid] = [
        '#type' => 'details',
        '#title' => $room->getTitle(),
        '#open' => TRUE,
      ];

      $form['room_' . $rid]['room_id'] = [
        '#type' => 'hidden',
        '#value' => $rid,
      ];

      $form['room_' . $rid]['total'] = [
        '#markup' => '<p><strong>Total Rooms:</strong> ' . $total . '</p>',
      ];

      $form['room_' . $rid]['available'] = [
        '#markup' => '<p><strong>Available Rooms:</strong> ' . $available . '</p>',
      ];

      $form['room_' . $rid]['booked'] = [
        '#markup' => '<p><strong>Booked Rooms:</strong> ' . $booked . '</p>',
      ];

      $form['room_' . $rid]['increment_' . $rid] = [
        '#type' => 'submit',
        '#value' => '+ Booked',
        '#name' => 'increment_' . $rid,
        '#submit' => ['::incrementBooking'],
        '#button_type' => 'primary',
        '#attributes' => [
            'class' => ['booked-btn'],
        ],
      ];

      $form['room_' . $rid]['decrement_' . $rid] = [
        '#type' => 'submit',
        '#value' => '- Released',
        '#name' => 'decrement_' . $rid,
        '#submit' => ['::decrementBooking'],
        '#button_type' => 'danger',
        '#attributes' => [
            'class' => ['released-btn'],
        ],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty because custom submit handlers used.
  }

  public function incrementBooking(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $room_id = str_replace('increment_', '', $trigger['#name']);

    $room = Node::load($room_id);

    if ($room) {
      $booked = (int) $room->get('field_booked_rooms')->value;
      $available = (int) $room->get('field_available_rooms')->value;

      if ($available > 0) {
        $room->set('field_booked_rooms', $booked + 1);
        $room->set('field_available_rooms', $available - 1);
        $room->save();

        $this->messenger()->addStatus('Room booked successfully.');
      }
      else {
        $this->messenger()->addError('No rooms available.');
      }
    }

    $form_state->setRebuild(TRUE);
  }

  public function decrementBooking(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $room_id = str_replace('decrement_', '', $trigger['#name']);

    $room = Node::load($room_id);

    if ($room) {
      $booked = (int) $room->get('field_booked_rooms')->value;
      $available = (int) $room->get('field_available_rooms')->value;

      if ($booked > 0) {
        $room->set('field_booked_rooms', $booked - 1);
        $room->set('field_available_rooms', $available + 1);
        $room->save();

        $this->messenger()->addStatus('Booking removed successfully.');
      }
    }

    $form_state->setRebuild(TRUE);
  }

}