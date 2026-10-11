<?php
/**
 * portal/resend.php
 *
 * Custom transactional email sent by our own code (not Supabase Auth's
 * built-in emails, which go through the SMTP relay configured in the
 * Supabase dashboard instead). Plain cURL against Resend's HTTP API.
 */
require_once __DIR__ . '/supabase.php'; // for portal_config() / portal_url()

function resend_send_email($to, $subject, $html) {
    $cfg = portal_config();
    if (empty($cfg['resend_api_key'])) {
        return ['status' => 0, 'error' => 'Resend API key not configured'];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['resend_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'from'    => $cfg['resend_from_address'],
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'raw' => $raw, 'error' => $err];
}

/** Notifies the portal admin that a new event needs review. Never throws — a failed email shouldn't break event creation. */
function notify_admin_new_event($eventTitle, $organizerLabel) {
    $cfg = portal_config();
    if (empty($cfg['admin_notify_email'])) return;

    $link = portal_url('admin/events.php');
    $html = '<p>A new event was just submitted and is waiting for approval:</p>'
          . '<p><strong>' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '</strong><br>'
          . 'by ' . htmlspecialchars($organizerLabel, ENT_QUOTES, 'UTF-8') . '</p>'
          . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Review and approve →</a></p>';

    try {
        resend_send_email($cfg['admin_notify_email'], 'New event pending approval: ' . $eventTitle, $html);
    } catch (Throwable $e) {
        // Swallow — a notification failure shouldn't block the organizer's event from being created.
    }
}

/**
 * Sends a business fee reminder email. Unlike notify_admin_new_event(),
 * this does NOT swallow failures — admin/finance.php's reminders tab needs
 * the real status/error to write into fee_reminder_recipients for the
 * audit log, so the caller decides what "failed" means for that row.
 */
function finance_send_reminder_email($to, $subject, $html) {
    return resend_send_email($to, $subject, $html);
}
