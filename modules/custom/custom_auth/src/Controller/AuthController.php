<?php

namespace Drupal\custom_auth\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

class AuthController extends ControllerBase {

  public function register(Request $request) {

    try {
      $data = json_decode($request->getContent(), TRUE);

      $name = $data['name'] ?? '';
      $email = $data['email'] ?? '';
      $mobile = $data['mobile'] ?? '';
      $password = $data['password'] ?? '';

      // ❌ VALIDATION ERROR
      if (!$mobile || !$password) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Mobile & Password required'
        ], 400);
      }

      // 🔍 DUPLICATE CHECK
      $query = \Drupal::entityQuery('user')
        ->accessCheck(FALSE)
        ->condition('field_mobile_number', $mobile)
        ->execute();

      if (!empty($query)) {
        return new JsonResponse([
          'status' => 'exists',
          'message' => 'User already exists'
        ], 409); // ✅ better status code
      }

      // 🟢 CREATE USER
      $user = User::create([
        'name' => $mobile,
        'mail' => $email,
        'pass' => $password,
        'field_full_name' => $name,
        'field_mobile_number' => $mobile,
        'status' => 1,
      ]);

      $user->save();

      // ✅ SUCCESS
      return new JsonResponse([
        'status' => 'success',
        'message' => 'User registered successfully'
      ], 200);

    } catch (\Exception $e) {

      // ❌ SERVER ERROR
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Server error',
        'debug' => $e->getMessage() // optional (remove in production)
      ], 500);
    }
  }

  public function profile(Request $request) {

    try {
      $jwtService = \Drupal::service('jwt_auth.jwt_service');
      $decoded = $jwtService->verifyToken($request);

      // ❌ agar error aaya to service hi response de degi
      if ($decoded instanceof JsonResponse) {
        return $decoded;
      }

      // Get UID
      $uid = $decoded->uid ?? NULL;

      if (!$uid) {
        return new JsonResponse([
          'status' => false,
          'message' => 'Invalid token'
        ], 401);
      }

      // Load user
      $user = User::load($uid);

      if (!$user) {
        return new JsonResponse([
          'status' => false,
          'message' => 'User not found'
        ], 404);
      }

      // Return response
      return new JsonResponse([
        'status' => true,
        'user' => [
          'id' => $user->id(),
          'username' =>
            $user->getAccountName(),
          'email' =>
            $user->getEmail(),
          'full_name' =>
            $user->get('field_full_name')->value ?? '',
          'mobile' =>
            $user->get('field_mobile_number')->value ?? '',
        ]
      ]);

    } catch (\Exception $e) {

      return new JsonResponse([
        'status' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

}