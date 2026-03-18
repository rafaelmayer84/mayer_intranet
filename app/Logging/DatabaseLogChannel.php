<?php

namespace App\Logging;

use Monolog\Logger;

class DatabaseLogChannel
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler());
        return $logger;
    }
}
