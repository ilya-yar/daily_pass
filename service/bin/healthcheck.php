<?php

declare(strict_types=1);

$context = stream_context_create([
    'http' => [
        'ignore_errors' => true,
        'timeout' => 3,
    ],
]);
$body = file_get_contents('http://127.0.0.1:8080/health', false, $context);

exit(is_string($body) && str_contains($body, '"status":"ok"') ? 0 : 1);
