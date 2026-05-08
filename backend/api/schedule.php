<?php

require_once __DIR__ . '/../bootstrap.php';

use App\AllSecureService;

api_cors();

$input = api_input();
$scheduleId = trim((string) ($_GET['schedule_id'] ?? $input['schedule_id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? $input['action'] ?? 'show'));
$continueDateTime = trim((string) ($input['continue_datetime'] ?? ''));

if ($scheduleId === '') {
    api_json(422, array('ok' => false, 'message' => 'schedule_id is required'));
}

try {
    $service = new AllSecureService();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pause') {
        $result = $service->pauseSchedule($scheduleId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel') {
        $result = $service->cancelSchedule($scheduleId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'continue') {
        $result = $service->continueSchedule($scheduleId, $continueDateTime);
    } else {
        $result = $service->showSchedule($scheduleId);
    }

    api_json(200, array('ok' => true, 'schedule' => $result));
} catch (Throwable $exception) {
    api_json(500, array(
        'ok' => false,
        'scheduleId' => $scheduleId,
        'message' => $exception->getMessage(),
    ));
}
