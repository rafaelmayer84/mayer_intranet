<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'rafaelmayer@mayeradvogados.adv.br')->first();

if ($user) {
    echo "Usuário encontrado:\n";
    echo "ID: " . $user->id . "\n";
    echo "Nome: " . $user->name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Role: " . $user->role . "\n\n";
    
    // Resetar senha para Mayer@2024
    $user->password = Hash::make('Mayer@2024');
    $user->save();
    
    echo "Senha resetada para: Mayer@2024\n";
} else {
    echo "Usuário não encontrado!\n";
}
