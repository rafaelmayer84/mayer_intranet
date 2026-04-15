<?php

namespace App\Console\Commands;

use App\Models\NexoPublicToken;
use Illuminate\Console\Command;

class NexoPurgeExpiredTokens extends Command
{
    protected $signature   = 'nexo:purge-expired-tokens';
    protected $description = 'Remove tokens públicos expirados há mais de 24 horas';

    public function handle(): int
    {
        $deleted = NexoPublicToken::where('expires_at', '<', now()->subHours(24))->delete();

        $this->info("nexo:purge-expired-tokens: {$deleted} token(s) removido(s).");

        return self::SUCCESS;
    }
}
