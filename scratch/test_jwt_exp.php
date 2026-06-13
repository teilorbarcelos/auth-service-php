<?php

require 'vendor/autoload.php';

use App\Infrastructure\Auth\JwtService;
use Predis\Client;

$redis = new Client();
$jwt = new JwtService($redis);

echo "Creating token with 2s expiration...\n";
$token = $jwt->createToken('user-123', [], '2 seconds');

echo "Validating immediately...\n";
$claims = $jwt->validateToken($token);
echo "Valid: " . ($claims ? 'YES' : 'NO') . "\n";

echo "Waiting 5 seconds...\n";
sleep(5);

echo "Validating after 5s...\n";
$claims = $jwt->validateToken($token);
echo "Valid: " . ($claims ? 'YES' : 'NO') . "\n";
