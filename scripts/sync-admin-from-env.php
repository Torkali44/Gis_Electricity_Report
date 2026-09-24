<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = config('auth.admin.email');
$name = config('auth.admin.name');
$password = config('auth.admin.password');

if (! is_string($email) || ! is_string($name) || ! is_string($password)
    || trim($email) === '' || trim($name) === '' || trim($password) === '') {
    fwrite(STDERR, "ADMIN_* missing from .env\n");
    exit(1);
}

$user = User::query()->updateOrCreate(
    ['email' => $email],
    [
        'name' => $name,
        // Plain text — User model "hashed" cast will hash once.
        'password' => $password,
        'is_admin' => true,
    ]
);

$ok = Hash::check($password, $user->fresh()->password);

echo "email={$user->email}\n";
echo "is_admin=".($user->is_admin ? '1' : '0')."\n";
echo "password_matches=".($ok ? 'yes' : 'no')."\n";

exit($ok ? 0 : 1);
