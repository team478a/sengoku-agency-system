<?php

declare(strict_types=1);

$sharedFiles = [
    __DIR__ . '/../src/Shared/Auth/ApiAuthenticationResult.php',
    __DIR__ . '/../src/Shared/Auth/ApiIpRestriction.php',
    __DIR__ . '/../src/Shared/Auth/ApiKeyAuthenticator.php',
    __DIR__ . '/../src/Shared/Auth/ApiScopeAuthorizer.php',
    __DIR__ . '/../src/Shared/Config/SettingsRepository.php',
    __DIR__ . '/../src/Shared/Database/DatabaseConnection.php',
    __DIR__ . '/../src/Shared/Database/SchemaVersionChecker.php',
    __DIR__ . '/../src/Shared/Database/TransactionManager.php',
    __DIR__ . '/../src/Shared/Http/ApiExceptionHandler.php',
    __DIR__ . '/../src/Shared/Http/HttpClient.php',
    __DIR__ . '/../src/Shared/Http/JsonRequest.php',
    __DIR__ . '/../src/Shared/Http/JsonResponse.php',
    __DIR__ . '/../src/Shared/Log/Logger.php',
    __DIR__ . '/../src/Shared/Time/Clock.php',
    __DIR__ . '/../src/Integration/Outbox/RetryPolicy.php',
    __DIR__ . '/../src/Integration/Outbox/OutboxClaimService.php',
    __DIR__ . '/../src/Integration/Outbox/OutboxRepository.php',
    __DIR__ . '/../src/Integration/Outbox/DeadLetterService.php',
];

foreach ($sharedFiles as $sharedFile) {
    if (is_file($sharedFile)) {
        require_once $sharedFile;
    }
}
