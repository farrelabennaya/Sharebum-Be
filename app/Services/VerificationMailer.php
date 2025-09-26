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
        $esc = static fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $brand = [
            'bg'      => '#0b0b10',   // background luar
            'card'    => '#11131a',   // kartu
            'border'  => '#27272a',
            'text'    => '#e5e7eb',
            'muted'   => '#94a3b8',
            'cta'     => '#7c3aed',   // ungu (bisa kamu ganti)
            'ctaTxt'  => '#ffffff',
            'link'    => '#a78bfa',
        ];

        $name = $esc($name);
        $linkEsc = $esc($link);

        return <<<HTML
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Email</title>
    <!-- Preheader (disembunyikan di body, tampil di preview list) -->
    <style>
      .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all; }
      @media (max-width: 600px) {
        .container { width: 100% !important; }
        .card { padding: 20px !important; }
        .btn { display:block !important; width:100% !important; }
      }
    </style>
  </head>
  <body style="margin:0; padding:0; background:{$brand['bg']};">
    <div class="preheader">Klik tombol untuk aktivasi akun kamu. Link berlaku 60 menit.</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$brand['bg']}; padding:24px 12px;">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="560" class="container" style="width:560px; max-width:560px;">
            <!-- Header / Logo (opsional) -->
            <tr>
              <td align="left" style="padding:0 4px 12px 4px;">
                <div style="font:600 16px/1 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['text']};">
                  Sharebum
                </div>
              </td>
            </tr>

            <!-- Card -->
            <tr>
              <td>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       class="card"
                       style="background:{$brand['card']}; border:1px solid {$brand['border']}; border-radius:14px; padding:28px;">
                  <tr>
                    <td style="font:700 20px/1.3 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['text']}; padding-bottom:8px;">
                      Verifikasi Email
                    </td>
                  </tr>

                  <tr>
                    <td style="font:400 14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['muted']}; padding-bottom:18px;">
                      Hai <span style="color:{$brand['text']}; font-weight:600;">{$name}</span>,<br>
                      Terima kasih sudah mendaftar. Klik tombol di bawah untuk mengaktifkan akun kamu.
                    </td>
                  </tr>

                  <!-- CTA -->
                  <!-- Wrapper table biar aman di semua klien -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate; width:auto; max-width:100%;">
  <tr>
    <td align="left" style="padding-bottom:18px;">
      <!-- Inner table bikin button lebih stabil di Outlook -->
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate; max-width:100%;">
        <tr>
          <td align="center" bgcolor="#7c3aed" style="
              border-radius:10px;
              mso-padding-alt:12px 18px; /* Outlook */
              ">
            <a href="{$linkEsc}"
               style="
                 display:inline-block;
                 max-width:100%;
                 background:{$brand['cta']};
                 color:{$brand['ctaTxt']};
                 text-decoration:none;
                 font:600 14px/1 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
                 padding:12px 18px;
                 border-radius:10px;
                 box-sizing:border-box;
                 word-break:keep-all;
                 white-space:nowrap;
               ">
              Verifikasi Email
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- Media query: kecilkan font & padding di layar sempit -->
<style>
@media screen and (max-width:480px) {
  a.btn, /* kalau kamu pakai class */
  a[href*="{$linkEsc}"] {
    font-size:13px !important;
    padding:10px 14px !important;
    white-space:normal !important; /* boleh wrap kalau perlu */
  }
}
</style>


                  <!-- Expiry info -->
                  <tr>
                    <td style="font:400 12px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['muted']}; padding-bottom:12px;">
                      Link ini berlaku selama 60 menit. Jika tombol tidak berfungsi, salin dan buka URL berikut:
                    </td>
                  </tr>

                  <!-- Fallback URL -->
                  <tr>
                    <td style="background:#0f1117; border:1px solid {$brand['border']}; border-radius:10px; padding:12px; word-break:break-all;">
                      <a href="{$linkEsc}" style="color:{$brand['link']}; text-decoration:none;">{$linkEsc}</a>
                    </td>
                  </tr>

                  <!-- Support -->
                  <tr>
                    <td style="font:400 12px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['muted']}; padding-top:18px;">
                      Tidak merasa mendaftar? Abaikan email ini.
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td align="center" style="padding:14px 4px 0 4px;">
                <div style="font:400 12px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:{$brand['muted']};">
                  © <!--{Y}--> Sharebum • Email ini dikirim otomatis, mohon jangan balas.
                </div>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
    }
}
