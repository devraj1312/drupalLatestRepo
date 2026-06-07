<?php

namespace Drupal\custom_otp_auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OtpController extends ControllerBase {

  protected $database;
  protected $entityTypeManager;

  // 🔥 Constructor
  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  // 🔥 Create method (DI binding)
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  // 🔥 SEND OTP
  public function sendOtp(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $phone = $data['phone'];

    // Purana OTP delete
    $this->database->delete('otp_storage')
      ->condition('phone', $phone)
      ->execute();

    // OTP generate
    $otp = rand(100000, 999999);
    $created = time();
    $expires = $created + 300;

    // Insert new OTP
    $this->database->insert('otp_storage')->fields([
      'phone' => $phone,
      'otp' => $otp,
      'created_at' => $created,
      'expires_at' => $expires,
    ])->execute();

    return new JsonResponse([
      'status' => 'success',
      'otp' => $otp
    ]);
  }

  // 🔥 VERIFY OTP
  public function verifyOtp(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $phone = $data['phone'];
    $otp = $data['otp'];

    $record = $this->database->select('otp_storage', 'o')
        ->fields('o')
        ->condition('phone', $phone)
        ->condition('is_used', 0)
        ->execute()
        ->fetchObject();

    if (!$record) {
        return new JsonResponse(['status' => 'error', 'message' => 'OTP not found'], 404);
    }

    // Expiry check
    if (time() > $record->expires_at) {
        return new JsonResponse(['status' => 'error', 'message' => 'OTP expired'], 400);
    }

    // Match check
    if ($record->otp != $otp) {
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid OTP'], 401);
    }

    // Mark as used
    $this->database->update('otp_storage')
        ->fields(['is_used' => 1])
        ->condition('id', $record->id)
        ->execute();

    // 🔍 USER CHECK (same as password login approach)
    $query = \Drupal::entityQuery('user')
        ->condition('field_mobile_number.value', $phone)
        ->accessCheck(FALSE);

    $uids = $query->execute();

    if (empty($uids)) {
      return new JsonResponse([
        'status' => 'not_found',
        'message' => 'User not registered. Please sign up first.'
      ], 404);
    }

    $uid = reset($uids);
    $user = User::load($uid);

    // ✅ JWT GENERATE (same as password login)
    $jwtService = \Drupal::service('jwt_auth.jwt_service');
    $jwt = $jwtService->generateToken($user);

    return new JsonResponse([
        'status' => 'success',
        'message' => 'Login successful',
        'token' => $jwt,
        'user' => [
        'id' => $user->id(),
        'name' => $user->get('field_full_name')->value ?? $phone
        ]
    ], 200);
  }
}