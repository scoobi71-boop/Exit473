<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

// Honeypot — bots fill this in, humans don't
if (!empty($_POST['website_url'])) {
    header('Location: contact.html?sent=1');
    exit;
}

$first   = trim($_POST['first_name'] ?? '');
$last    = trim($_POST['last_name']  ?? '');
$email   = trim($_POST['email']      ?? '');
$phone   = trim($_POST['phone']      ?? '');
$subject = trim($_POST['subject']    ?? '');
$message = trim($_POST['message']    ?? '');

function redirect_error($msg) {
    header('Location: contact.html?error=' . urlencode($msg));
    exit;
}

if (!$first || !$email || !$subject || !$message) {
    redirect_error('Please fill in all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_error('Please enter a valid email address.');
}

$subject_labels = [
    'general'  => 'General Enquiry',
    'leasing'  => 'Leasing / Space Availability',
    'event'    => 'Event or Performance Booking',
    'listing'  => 'Business Listing Update',
    'feedback' => 'Feedback or Complaint',
    'media'    => 'Media & Press',
    'other'    => 'Other',
];
$subject_label = $subject_labels[$subject] ?? ucfirst($subject);
$name          = trim("$first $last");

$to      = 'scoobi71@gmail.com';
$headers = implode("\r\n", [
    "From: Exit 473 Website <noreply@exit473.com>",
    "Reply-To: {$name} <{$email}>",
    "Content-Type: text/plain; charset=UTF-8",
    "X-Mailer: PHP/" . phpversion(),
]);

$body = "You have a new message from the Exit 473 website contact form.\n"
      . str_repeat('-', 48) . "\n"
      . "Name:    {$name}\n"
      . "Email:   {$email}\n"
      . ($phone ? "Phone:   {$phone}\n" : '')
      . "Subject: {$subject_label}\n"
      . str_repeat('-', 48) . "\n\n"
      . $message . "\n";

if (mail($to, "Contact: {$subject_label}", $body, $headers)) {
    header('Location: contact.html?sent=1');
} else {
    redirect_error('Message could not be sent. Please email us directly at exit473.gd@gmail.com.');
}
exit;
