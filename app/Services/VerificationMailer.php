<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Resend\Resend;
use Throwable;

class VerificationMailer
{
    public function __construct(
        protected Resend $resend = new Resend(null) // biar bisa diresolve container
    ) {
        $this->resend->setApiKey(config('services.resend.key'));
    }

    public function send(User $user): void
    {
        // buat signed URL menuju route backend 'verification.verify'
        $link = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $html = $this->buildHtml($user->name, $link);
        $text = "Halo {$user->name},\n\n".
                "Klik tautan berikut untuk verifikasi email akun kamu:\n{$link}\n\n".
                "Link berlaku 60 menit.\n\nTerima kasih.";

        try {
            $this->resend->emails->send([
                'from'    => config('services.resend.from'),
                'to'      => [$user->email],
                'subject' => 'Verifikasi Email Akun Kamu',
                'html'    => $html,
                'text'    => $text,
            ]);
        } catch (Throwable $e) {
            report($e);
            // opsional: lempar exception kalau mau gagal total
            // throw $e;
        }
    }

    protected function buildHtml(string $name, string $link): string
    {
        // simple, aman di sebagian besar client
        return <<<HTML
<!doctype html>
<html>
  <body style="background:#0b0b0d;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto; padding:24px">
    <div style="max-width:560px;margin:auto;background:#111216;border:1px solid #2a2b31;border-radius:14px;padding:24px">
      <h1 style="margin:0 0 8px;font-size:20px;color:#fff">Verifikasi Email</h1>
      <p style="margin:0 0 16px;line-height:1.6">Halo <b>{$this->escape($name)}</b>,</p>
      <p style="margin:0 0 16px;line-height:1.6">
        Klik tombol di bawah untuk mengaktifkan akun kamu. Tautan berlaku selama <b>60 menit</b>.
      </p>
      <p style="margin:20px 0">
        <a href="{$this->escape($link)}"
           style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;
                  padding:12px 18px;border-radius:10px;font-weight:600">
          Verifikasi Email
        </a>
      </p>
      <p style="margin:16px 0 0;line-height:1.6;color:#9ca3af">
        Atau salin tautan ini ke browser:<br/>
        <span style="word-break:break-all;color:#c4c8d0">{$this->escape($link)}</span>
      </p>
    </div>
  </body>
</html>
HTML;
    }

    protected function escape(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
