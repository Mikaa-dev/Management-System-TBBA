<?php
/** Offline push regression checks: no DB connection, OAuth call or push send. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not Found'); }
require_once __DIR__ . '/../core/NotificationService.php';

function checkPush(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$expired = new ReflectionMethod(NotificationService::class, 'isUnregisteredToken');
$payload = new ReflectionMethod(NotificationService::class, 'buildPushPayload');
$fcmType = 'type.googleapis.com/google.firebase.fcm.v1.FcmError';
foreach ([
    ['error' => ['status' => 'INVALID_ARGUMENT']],
    ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'message.webpush.fcm_options.link']]]]]],
    ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['@type' => $fcmType, 'errorCode' => 'INVALID_ARGUMENT']]]],
    ['error' => ['status' => 'PERMISSION_DENIED', 'details' => [['@type' => $fcmType, 'errorCode' => 'SENDER_ID_MISMATCH']]]],
    ['error' => ['status' => 'UNAVAILABLE']],
] as $error) {
    checkPush(!$expired->invoke(null, $error), 'Payload, configuration and transient errors preserve device tokens');
}
checkPush($expired->invoke(null, ['error' => ['details' => [
    ['@type' => 'type.googleapis.com/google.rpc.RequestInfo'],
    ['@type' => $fcmType, 'errorCode' => 'UNREGISTERED'],
]]]), 'Confirmed UNREGISTERED token is detected anywhere in the details array');

$message = $payload->invoke(null, 'test-token', 'Title', 'Body', 'index.php?page=notifications', 'test', 'https://www.thebridgebusiness.com');
checkPush($message['message']['webpush']['fcm_options']['link'] === 'https://www.thebridgebusiness.com/index.php?page=notifications', 'Production click URL is absolute HTTPS');
$message = $payload->invoke(null, 'test-token', 'Title', 'Body', '', 'test', 'https://www.thebridgebusiness.com');
checkPush($message['message']['webpush']['fcm_options']['link'] === 'https://www.thebridgebusiness.com/index.php?page=notifications', 'Empty click URL has a valid HTTPS default instead of a relative slash');
$message = $payload->invoke(null, 'test-token', 'Title', 'Body', 'index.php?page=leave', 'test', 'http://localhost/Bridge');
checkPush(!isset($message['message']['webpush']['fcm_options']), 'Local HTTP configuration cannot send an invalid FCM HTTPS click option');
checkPush($message['message']['notification']['title'] === 'Title', 'Notification remains displayable without a click option');
echo "PUSH BACKEND CHECKS COMPLETE\n";
