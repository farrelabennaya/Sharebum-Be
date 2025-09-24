<?php
namespace App\Services;

use Resend\Resend;
use Illuminate\Support\Facades\URL;

class VerificationMailer
{
    public function __construct(private Resend $resend) {}

    public function send($user): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)]
        );

        $name = htmlspecialchars($user->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html>
  <body style="font-family:Inter,Arial,sans-serif;background:#0b0b10;color:#e5e7eb;padding:24px">
    <div style="max-width:520px;margin:auto;background:#11131a;border:1px solid #27272a;border-radius:14px;padding:24px">
      <h2 style="margin:0 0 8px;color:#fff">Verifikasi Email</h2>
      <p style="margin:0 0 16px;color:#cbd5e1">Hai {$name}, klik tombol di bawah untuk aktivasi akun kamu.</p>
      <p style="margin:20px 0">
        <a href="{$url}" style="background:#7c3aed;color:white;text-decoration:none;padding:12px 16px;border-radius:10px;display:inline-block">
          Verifikasi Email
        </a>
      </p>
      <p style="font-size:12px;color:#94a3b8">Link berlaku 60 menit. Jika tombol tidak berfungsi, salin URL berikut:</p>
      <code style="display:block;word-break:break-all;color:#cbd5e1">{$url}</code>
    </div>
  </body>
</html>
HTML;

        $this->resend->emails->send([
            'from'    => config('mail.from.address'),
            'to'      => [$user->email],
            'subject' => 'Verifikasi Email Akun Kamu',
            'html'    => $html,
        ]);
    }
}
