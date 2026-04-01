<?php
/**
 * Valírica – Demo Request Handler
 * Sends a branded HTML email to the team on each new demo booking.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$name    = trim(filter_input(INPUT_POST, 'name',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$company = trim(filter_input(INPUT_POST, 'company', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email   = trim(filter_input(INPUT_POST, 'email',   FILTER_SANITIZE_EMAIL)         ?? '');
$phone   = trim(filter_input(INPUT_POST, 'phone',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$date    = trim(filter_input(INPUT_POST, 'date',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$time    = trim(filter_input(INPUT_POST, 'time',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

if (!$name || !$company || !$email || !$date || !$time) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit;
}

// ── Format date nicely ──────────────────────────────
$dateObj   = DateTime::createFromFormat('Y-m-d', $date);
$dateNice  = $dateObj ? $dateObj->format('l, j \d\e F \d\e Y') : $date;
$phoneDisp = $phone ?: '—';

// ── HTML Email Template ─────────────────────────────
$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nueva Solicitud de Demo – Valírica</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f4f8;padding:40px 16px;">
<tr><td align="center">

  <!-- Card -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(1,33,51,0.10);">

    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,#012133 0%,#007a96 100%);padding:36px 40px;text-align:center;">
        <p style="margin:0 0 6px 0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.55);">Valírica · Demo Request</p>
        <h1 style="margin:0;font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;line-height:1.2;">Nueva solicitud de demo</h1>
        <p style="margin:10px 0 0;font-size:14px;color:rgba(255,255,255,0.65);">Alguien quiere conocer Valírica 🎉</p>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 40px;">

        <!-- Person info -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
          <tr>
            <td style="padding-bottom:20px;border-bottom:1px solid #e8ecf1;">
              <p style="margin:0 0 4px 0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">Interesado</p>
              <p style="margin:0;font-size:22px;font-weight:800;color:#012133;letter-spacing:-0.3px;">{$name}</p>
              <p style="margin:4px 0 0;font-size:15px;font-weight:600;color:#007a96;">{$company}</p>
            </td>
          </tr>
        </table>

        <!-- Contact details -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
          <tr>
            <td width="50%" style="padding-bottom:16px;vertical-align:top;">
              <p style="margin:0 0 4px 0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">Email</p>
              <a href="mailto:{$email}" style="font-size:14px;font-weight:600;color:#007a96;text-decoration:none;">{$email}</a>
            </td>
            <td width="50%" style="padding-bottom:16px;vertical-align:top;">
              <p style="margin:0 0 4px 0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">Teléfono</p>
              <p style="margin:0;font-size:14px;font-weight:600;color:#334155;">{$phoneDisp}</p>
            </td>
          </tr>
        </table>

        <!-- Session details -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f9fc;border-radius:14px;border:1px solid #e2e8f0;padding:0;margin-bottom:28px;">
          <tr>
            <td style="padding:20px 24px;">
              <p style="margin:0 0 14px 0;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">Sesión solicitada</p>
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="padding-bottom:10px;">
                    <table cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:28px;vertical-align:middle;">
                          <span style="font-size:16px;">📅</span>
                        </td>
                        <td style="vertical-align:middle;padding-left:8px;">
                          <span style="font-size:15px;font-weight:700;color:#012133;">{$dateNice}</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td>
                    <table cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="width:28px;vertical-align:middle;">
                          <span style="font-size:16px;">⏰</span>
                        </td>
                        <td style="vertical-align:middle;padding-left:8px;">
                          <span style="font-size:15px;font-weight:700;color:#012133;">{$time} (hora Colombia)</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- CTA -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
          <tr>
            <td align="center">
              <a href="mailto:{$email}?subject=Tu%20Demo%20de%20Val%C3%ADrica%20est%C3%A1%20confirmada&body=Hola%20{$name}%2C%0A%0ATu%20sesi%C3%B3n%20demo%20est%C3%A1%20confirmada%20para%20el%20{$dateNice}%20a%20las%20{$time}.%0A%0ATe%20enviaremos%20la%20invitaci%C3%B3n%20de%20Google%20Meet%20a%20este%20correo.%0A%0AEl%20equipo%20de%20Val%C3%ADrica"
                 style="display:inline-block;padding:14px 32px;background:#ff9700;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;letter-spacing:-0.1px;">
                Confirmar e invitar por email ›
              </a>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:13px;color:#94a3b8;text-align:center;line-height:1.6;">
          Al hacer clic se abrirá tu cliente de correo con un borrador pre-listo.<br>
          Recuerda adjuntar el enlace de Google Meet.
        </p>

      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f7f9fc;border-top:1px solid #e2e8f0;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#b0bec5;">
          Este correo fue generado automáticamente por <strong style="color:#007a96;">valirica.com</strong><br>
          © {year} Valírica · Cultura Organizacional con IA
        </p>
      </td>
    </tr>

  </table>
  <!-- /Card -->

</td></tr>
</table>
</body>
</html>
HTML;

$year = date('Y');
$html = str_replace('{year}', $year, $html);

// ── Plain text fallback ─────────────────────────────
$plain  = "NUEVA SOLICITUD DE DEMO – VALÍRICA\n";
$plain .= str_repeat("=", 40) . "\n\n";
$plain .= "Nombre:   $name\n";
$plain .= "Empresa:  $company\n";
$plain .= "Email:    $email\n";
$plain .= "Teléfono: $phoneDisp\n\n";
$plain .= "Fecha:    $dateNice\n";
$plain .= "Hora:     $time (hora Colombia)\n\n";
$plain .= str_repeat("=", 40) . "\n";
$plain .= "Acción: envía la invitación de Google Meet a $email\n";

// ── Send ────────────────────────────────────────────
$to      = 'vale@valirica.com';
$subject = "=?UTF-8?B?" . base64_encode("🗓️ Demo solicitada: $name · $company") . "?=";

$boundary = md5(uniqid());
$headers  = "From: Valírica Web <webmaster@valirica.com>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$body  = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $plain . "\r\n\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $html . "\r\n\r\n";
$body .= "--$boundary--";

$sent = mail($to, $subject, $body, $headers);

echo json_encode(['status' => 'success', 'sent' => $sent]);
