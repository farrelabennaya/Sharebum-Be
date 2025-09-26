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
        'bg'          => '#050509',   // background luar yang lebih gelap
        'card'        => '#0f111a',   // kartu utama
        'cardInner'   => '#161825',   // inner card untuk depth
        'border'      => '#1e1f2e',   // border halus
        'borderLight' => '#2a2d3e',   // border yang lebih terang
        'text'        => '#f1f5f9',   // text utama
        'textSecond'  => '#cbd5e1',   // text secondary
        'muted'       => '#64748b',   // text muted
        'accent'      => '#8b5cf6',   // violet accent
        'accentDark'  => '#7c3aed',   // violet darker
        'success'     => '#10b981',   // green
        'ctaTxt'      => '#ffffff',
        'link'        => '#a78bfa',
        'gradient1'   => '#8b5cf6',   // gradient start
        'gradient2'   => '#ec4899',   // gradient end
        'bannerBg'    => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
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
    <title>Verifikasi Email - Sharebum</title>
    <style>
      .preheader {
        display:none !important;
        visibility:hidden;
        opacity:0;
        color:transparent;
        height:0;
        width:0;
        overflow:hidden;
        mso-hide:all;
      }

      /* Responsiveness */
      @media (max-width: 600px) {
        .container { width: 100% !important; }
        .card { padding: 20px !important; }
        .banner-text { font-size: 20px !important; line-height: 1.3 !important; }
        .banner-subtitle { font-size: 13px !important; }
        .main-title { font-size: 22px !important; }
        .btn-table { width: 100% !important; }
        .btn-link {
          display: block !important;
          width: 100% !important;
          text-align: center !important;
          padding: 14px 20px !important;
        }
      }

      @media (max-width: 480px) {
        .card { padding: 16px !important; }
        .banner { padding: 20px 16px !important; }
      }

      /* Dark mode support */
      @media (prefers-color-scheme: dark) {
        .auto-dark { color: {$brand['text']} !important; }
      }
    </style>
  </head>
  <body style="margin:0; padding:0; background:{$brand['bg']}; font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;">

    <div class="preheader">
      🎉 Aktivasi akun Sharebum kamu sekarang! Link berlaku 60 menit. Klik untuk memulai perjalanan sharing yang amazing!
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{$brand['bg']}; padding:0;">
      <tr>
        <td align="center" style="padding:24px 12px;">

          <!-- Main Container -->
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="container" style="width:600px; max-width:600px; border-radius:16px; overflow:hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);">

            <!-- Hero Banner -->
            <tr>
              <td class="banner" style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #8b5cf6 100%);
                padding:32px 24px;
                text-align:center;
                position:relative;
              ">
                <!-- Banner decoration (subtle pattern) -->
                <div style="
                  position:absolute;
                  top:0;
                  left:0;
                  right:0;
                  bottom:0;
                  background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 2px, transparent 2px);
                  background-size: 30px 30px;
                "></div>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="position:relative; z-index:1;">
                  <tr>
                    <td align="center">
                      <!-- Logo/Icon -->
                      <div style="
                        display:inline-block;
                        width:64px;
                        height:64px;
                        background:rgba(255,255,255,0.15);
                        border-radius:50%;
                        margin-bottom:16px;
                        position:relative;
                        backdrop-filter:blur(10px);
                        border:1px solid rgba(255,255,255,0.2);
                      ">
                        <div style="
                          position:absolute;
                          top:50%;
                          left:50%;
                          transform:translate(-50%,-50%);
                          color:white;
                          font-size:24px;
                          font-weight:700;
                        ">S</div>
                      </div>

                      <!-- Banner Title -->
                      <h1 class="banner-text" style="
                        margin:0 0 8px 0;
                        font-size:24px;
                        font-weight:700;
                        color:white;
                        line-height:1.2;
                        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                      ">
                        Selamat Datang di Sharebum!
                      </h1>

                      <!-- Banner Subtitle -->
                      <p class="banner-subtitle" style="
                        margin:0;
                        font-size:14px;
                        font-weight:400;
                        color:rgba(255,255,255,0.9);
                        line-height:1.5;
                      ">
                        Satu langkah lagi untuk memulai perjalanan sharing yang amazing
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Main Content Card -->
            <tr>
              <td style="background:{$brand['card']};">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="card" style="padding:36px 32px;">

                  <!-- Greeting -->
                  <tr>
                    <td style="padding-bottom:24px;">
                      <h2 class="main-title" style="
                        margin:0 0 12px 0;
                        font-size:26px;
                        font-weight:700;
                        color:{$brand['text']};
                        line-height:1.3;
                      ">
                        Verifikasi Email Kamu
                      </h2>

                      <p style="
                        margin:0;
                        font-size:16px;
                        line-height:1.6;
                        color:{$brand['textSecond']};
                      ">
                        Hai <strong style="color:{$brand['text']}; background:linear-gradient(135deg, {$brand['gradient1']}, {$brand['gradient2']}); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">{$name}</strong>! 👋
                      </p>

                      <p style="
                        margin:8px 0 0 0;
                        font-size:15px;
                        line-height:1.6;
                        color:{$brand['muted']};
                      ">
                        Terima kasih sudah bergabung dengan komunitas Sharebum. Untuk mengaktifkan akun dan mulai sharing konten amazing, silakan verifikasi email kamu dengan klik tombol di bawah.
                      </p>
                    </td>
                  </tr>

                  <!-- CTA Section -->
                  <tr>
                    <td style="padding:24px 0;">
                      <!-- CTA Card -->
                      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="
                        background:{$brand['cardInner']};
                        border:1px solid {$brand['borderLight']};
                        border-radius:12px;
                        padding:24px;
                      ">
                        <tr>
                          <td align="center">
                            <!-- Main CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="btn-table" style="margin:0 auto;">
                              <tr>
                                <td align="center" style="
                                  background: linear-gradient(135deg, {$brand['gradient1']} 0%, {$brand['gradient2']} 100%);
                                  border-radius:12px;
                                  box-shadow: 0 10px 15px -3px rgba(139, 92, 246, 0.3), 0 4px 6px -2px rgba(139, 92, 246, 0.1);
                                  mso-padding-alt:16px 32px;
                                ">
                                  <a href="{$linkEsc}" class="btn-link" style="
                                    display:inline-block;
                                    background:transparent;
                                    color:{$brand['ctaTxt']};
                                    text-decoration:none;
                                    font-weight:600;
                                    font-size:16px;
                                    padding:16px 32px;
                                    border-radius:12px;
                                    line-height:1;
                                    letter-spacing:0.025em;
                                    transition:all 0.2s ease;
                                  ">
                                    Verifikasi Email
                                  </a>
                                </td>
                              </tr>
                            </table>

                            <!-- Timer/Urgency indicator -->
                            <div style="
                              margin-top:16px;
                              padding:8px 16px;
                              background:rgba(16, 185, 129, 0.1);
                              border:1px solid rgba(16, 185, 129, 0.2);
                              border-radius:8px;
                              display:inline-block;
                            ">
                              <span style="
                                color:{$brand['success']};
                                font-size:12px;
                                font-weight:600;
                                letter-spacing:0.025em;
                              ">
                                Berlaku 60 menit
                              </span>
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>

                  <!-- Features Preview -->
                  <!-- <tr>
                    <td style="padding:24px 0;">
                      <div style="
                        background:{$brand['cardInner']};
                        border:1px solid {$brand['border']};
                        border-radius:10px;
                        padding:20px;
                      ">
                        <h3 style="
                          margin:0 0 12px 0;
                          font-size:16px;
                          font-weight:600;
                          color:{$brand['text']};
                        ">
                          Yang bisa kamu lakukan di Sharebum:
                        </h3>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                          <tr>
                            <td width="32" style="vertical-align:top; padding-right:12px;">
                              <div style="
                                width:24px;
                                height:24px;
                                background:linear-gradient(135deg, {$brand['gradient1']}, {$brand['gradient2']});
                                border-radius:50%;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:12px;
                              ">📤</div>
                            </td>
                            <td style="
                              font-size:14px;
                              line-height:1.5;
                              color:{$brand['muted']};
                              padding-bottom:8px;
                            ">
                              <strong style="color:{$brand['textSecond']};">Share konten</strong> dengan mudah dan cepat
                            </td>
                          </tr>
                          <tr>
                            <td width="32" style="vertical-align:top; padding-right:12px;">
                              <div style="
                                width:24px;
                                height:24px;
                                background:linear-gradient(135deg, {$brand['gradient1']}, {$brand['gradient2']});
                                border-radius:50%;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:12px;
                              ">🤝</div>
                            </td>
                            <td style="
                              font-size:14px;
                              line-height:1.5;
                              color:{$brand['muted']};
                              padding-bottom:8px;
                            ">
                              <strong style="color:{$brand['textSecond']};">Terhubung</strong> dengan komunitas yang aktif
                            </td>
                          </tr>
                          <tr>
                            <td width="32" style="vertical-align:top; padding-right:12px;">
                              <div style="
                                width:24px;
                                height:24px;
                                background:linear-gradient(135deg, {$brand['gradient1']}, {$brand['gradient2']});
                                border-radius:50%;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:12px;
                              ">🔥</div>
                            </td>
                            <td style="
                              font-size:14px;
                              line-height:1.5;
                              color:{$brand['muted']};
                            ">
                              <strong style="color:{$brand['textSecond']};">Discover</strong> konten menarik dari creator lain
                            </td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr> -->

                  <!-- Alternative link section -->
                  <tr>
                    <td style="padding:20px 0 0 0;">
                      <div style="
                        background:{$brand['cardInner']};
                        border:1px solid {$brand['border']};
                        border-radius:10px;
                        padding:16px;
                      ">
                        <p style="
                          margin:0 0 8px 0;
                          font-size:12px;
                          font-weight:600;
                          color:{$brand['textSecond']};
                          text-transform:uppercase;
                          letter-spacing:0.05em;
                        ">
                          Link tidak berfungsi?
                        </p>

                        <p style="
                          margin:0 0 12px 0;
                          font-size:13px;
                          line-height:1.5;
                          color:{$brand['muted']};
                        ">
                          Salin dan buka URL berikut di browser kamu:
                        </p>

                        <div style="
                          background:{$brand['bg']};
                          border:1px solid {$brand['borderLight']};
                          border-radius:8px;
                          padding:12px;
                          word-break:break-all;
                          font-family:Monaco,Consolas,'Liberation Mono','Courier New',monospace;
                        ">
                          <a href="{$linkEsc}" style="
                            color:{$brand['link']};
                            text-decoration:none;
                            font-size:12px;
                            line-height:1.4;
                          ">{$linkEsc}</a>
                        </div>
                      </div>
                    </td>
                  </tr>

                  <!-- Support section -->
                  <tr>
                    <td style="padding:24px 0 0 0; text-align:center;">
                      <div style="
                        padding:16px;
                        border-top:1px solid {$brand['border']};
                      ">
                        <p style="
                          margin:0 0 8px 0;
                          font-size:13px;
                          line-height:1.5;
                          color:{$brand['muted']};
                        ">
                          Tidak merasa mendaftar? Kamu bisa mengabaikan email ini dengan aman.
                        </p>

                        <p style="
                          margin:0;
                          font-size:12px;
                          line-height:1.5;
                          color:{$brand['muted']};
                        ">
                          Butuh bantuan? <a href="mailto:support@sharebum.com" style="color:{$brand['link']}; text-decoration:none;">Hubungi support kami</a>
                        </p>
                      </div>
                    </td>
                  </tr>

                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style="
                background:{$brand['cardInner']};
                padding:24px 32px;
                text-align:center;
                border-top:1px solid {$brand['border']};
              ">
                <div style="
                  font-size:12px;
                  line-height:1.6;
                  color:{$brand['muted']};
                ">
                  <div style="margin-bottom:8px;">
                    <strong style="color:{$brand['textSecond']};">Sharebum</strong> - Platform sharing terdepan di Indonesia
                  </div>

                  <div>
                    © 2024 Sharebum • Email otomatis, jangan balas langsung
                  </div>

                  <div style="margin-top:12px;">
                    <a href="#" style="color:{$brand['link']}; text-decoration:none; margin:0 8px;">Privacy</a>
                    <span style="color:{$brand['border']};">•</span>
                    <a href="#" style="color:{$brand['link']}; text-decoration:none; margin:0 8px;">Terms</a>
                    <span style="color:{$brand['border']};">•</span>
                    <a href="#" style="color:{$brand['link']}; text-decoration:none; margin:0 8px;">Unsubscribe</a>
                  </div>
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
