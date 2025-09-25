<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Resend\Resend;

class VerificationMailer
{
    public function __construct(private ?Resend $client = null)
    {
        // siapkan client sekali
        $this->client ??= Resend::client(config('services.resend.key'));
    }

    public function send(User $user): void
    {
        // signed URL ke route 'verification.verify'
        $link = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $from = config('services.resend.from');

        $html = $this->html($user->name, $link);
        $text = "Halo {$user->name},\n\n".
                "Klik tautan ini untuk verifikasi akun:\n{$link}\n\n".
                "Link berlaku 60 menit.\n";

        // kirim via Resend API
        $this->client->emails->send([
            'from'    => $from,
            'to'      => [$user->email],
            'subject' => 'Verifikasi Email Akun Kamu',
            'html'    => $html,
            'text'    => $text,
        ]);
    }

    private function html(string $name, string $link): string
    {
        $e = static fn ($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!doctype html>
<html><body style="background:#0b0b0d;color:#e5e7eb;font-family:system-ui; padding:24px">
<div style="max-width:560px;margin:auto;background:#111216;border:1px solid #2a2b31;border-radius:14px;padding:24px">
<h1 style="margin:0 0 8px;font-size:20px;color:#fff">Verifikasi Email</h1>
<p style="margin:0 0 16px;line-height:1.6">Halo <b>{$e($name)}</b>,</p>
<p style="margin:0 0 16px;line-height:1.6">Klik tombol di bawah. Tautan berlaku <b>60 menit</b>.</p>
<p style="margin:20px 0">
  <a href="{$e($link)}" style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;
     padding:12px 18px;border-radius:10px;font-weight:600">Verifikasi Email</a>
</p>
<p style="margin:16px 0 0;line-height:1.6;color:#9ca3af">Atau salin tautan:<br>
  <span style="word-break:break-all;color:#c4c8d0">{$e($link)}</span>
</p>
</div></body></html>
HTML;
    }
}
