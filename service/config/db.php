<?php

declare(strict_types=1);

$required = static function (string $name): string {
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('Environment variable %s is required.', $name));
    }

    return $value;
};

return [
    'class' => yii\db\Connection::class,
    'dsn' => $required('DB_DSN'),
    'username' => $required('DB_USER'),
    'password' => $required('DB_PASSWORD'),
    'charset' => 'utf8',
];
