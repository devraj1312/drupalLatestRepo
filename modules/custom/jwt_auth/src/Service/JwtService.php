<?php

namespace Drupal\jwt_auth\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService {

  public function verifyToken(Request $request) {

    $authHeader = $request->headers->get('Authorization');

    if (!$authHeader) {
      return new JsonResponse([
        'status' => false,
        'message' => 'Token missing'
      ], 401);
    }

    $token = str_replace('Bearer ', '', $authHeader);

    $secret = \Drupal::config('jwt.settings')->get('secret');

    try {
      $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    } catch (\Exception $e) {
      return new JsonResponse([
        'status' => false,
        'message' => 'Invalid or expired token'
      ], 401);
    }

    return $decoded;
  }

  public function generateToken($user) {

    $secret = \Drupal::config('jwt.settings')->get('secret');

    $payload = [
      "uid" => $user->id(),
      "email" => $user->getEmail(),
      "exp" => time() + 3600
    ];

    return JWT::encode($payload, $secret, 'HS256');
  }
}