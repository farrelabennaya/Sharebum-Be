<?php

// app/Services/VerificationMailer.php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class VerificationMailer
{
    public function send(User $user): void
    {
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id'   => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $client = $this->makeClient();

        $client->emails->send([
            'from'    => config('services.resend.from', 'onboarding@resend.dev'),
            'to'      => [$user->email],
            'subject' => 'Verifikasi Email Akun Kamu',
            'html'    => $this->html($user->name, $link),
            'text'    => "Halo {$user->name}\n{$link}\n",
        ]);
    }

    private function makeClient(): object
    {
        $key = config('services.resend.key');

        // v0.x: \Resend::client() atau new \Resend($key)
        if (class_exists(\Resend::class) && method_exists(\Resend::class, 'client')) {
            return \Resend::client($key);
        }
        if (class_exists(\Resend::class)) {
            return new \Resend($key);
        }

        throw new \RuntimeException('Resend SDK tidak terpasang/terdeteksi.');
    }

    private function html(string $name, string $link): string
    {
        $e = static fn ($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return "<p>Halo <b>{$e($name)}</b>, klik <a href=\"{$e($link)}\">tautan verifikasi</a>. Berlaku 60 menit.</p>";
    }
}
