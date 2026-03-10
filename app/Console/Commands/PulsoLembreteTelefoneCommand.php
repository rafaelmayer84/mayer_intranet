<?php

namespace App\Console\Commands;

use App\Models\NotificationIntranet;
use Illuminate\Console\Command;

class PulsoLembreteTelefoneCommand extends Command
{
    protected $signature = 'pulso:lembrete-telefone';
    protected $description = 'Envia lembrete ao admin para upload do relatório de ligações da semana';

    public function handle(): int
    {
        NotificationIntranet::enviar(
            1, // Rafael
            'Lembrete: Relatório de ligações',
            'Enviar o relatório de ligações da semana para o Pulso do Cliente. Acesse Upload de Ligações no CRM > Pulso.',
            '/crm/pulso/upload',
            'pulso',
            'phone'
        );

        $this->info('Lembrete de telefone enviado para Rafael.');
        return 0;
    }
}
