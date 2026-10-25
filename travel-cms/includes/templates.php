<?php
/**
 * Editable email and WhatsApp message templates (Admin > Message Templates).
 *
 * Texts are plain text with {placeholders}. The default-language version is stored in
 * settings ("tpl_email_KEY_subject" / "tpl_email_KEY_body" / "tpl_wa_KEY"), other
 * languages in the translations table (type "template"). When nothing was saved the
 * built-in default below is used (translated by the language pack when available).
 */
if (!defined('APP_ROOT')) {
    exit('No direct access');
}

/** Placeholders available in booking messages. */
function booking_placeholders(): array
{
    return [
        '{name}' => 'Guest name', '{reference}' => 'Booking reference', '{trip}' => 'Trip or activity', '{date}' => 'Travel date',
        '{guests}' => 'Guests (e.g. 2 adults, 1 child)', '{total}' => 'Amount due on arrival', '{status}' => 'Booking status',
        '{voucher_link}' => 'Link to the guest voucher', '{review_link}' => 'Link to review the trip', '{message}' => 'Your optional message',
        '{site_name}' => 'Website name', '{site_phone}' => 'Your phone', '{site_email}' => 'Your email',
    ];
}

function request_placeholders(): array
{
    return [
        '{name}' => 'Guest name', '{reference}' => 'Request reference', '{destinations}' => 'Destinations wanted', '{start_date}' => 'Travel date',
        '{travelers}' => 'Travellers', '{budget}' => 'Budget', '{message}' => 'Your optional message',
        '{site_name}' => 'Website name', '{site_phone}' => 'Your phone', '{site_email}' => 'Your email',
    ];
}

/** Email templates: key => [name, description, default subject, default body, placeholder group, HTML blocks]. */
function email_templates(): array
{
    $sign = "\n\n{site_name}\n{site_phone} · {site_email}";
    return [
        'booking_guest' => ['New booking (to guest)', 'Sent to the guest right after they book.',
            'Your reservation {reference} - {site_name}',
            "Hi {name},\n\nThank you for your reservation! {status_message} No payment has been charged: you pay on arrival.\n\n{booking_details}\n\n{voucher_button}\n\n{voucher_note}" . $sign,
            'booking', ['{booking_details}', '{voucher_button}']],
        'booking_admin' => ['New booking (to you)', 'Alert sent to your notification email for every new booking.',
            'New booking {reference} - {trip}',
            "A new booking request was received.\n\n{booking_details}\n\nGuest: {name}\nEmail: {guest_email}\nPhone: {guest_phone}\nNotes: {notes}\n\n{admin_link}",
            'booking', ['{booking_details}', '{admin_link}']],
        'status_pending' => ['Status: pending', 'Sent when you email the guest about a pending booking.',
            'Booking {reference} - received',
            "Hi {name},\n\nWe have received your booking request and will confirm it shortly.\n\n{booking_details}\n\n{message}\n\n{voucher_button}" . $sign,
            'booking', ['{booking_details}', '{voucher_button}']],
        'status_confirmed' => ['Status: confirmed', 'Sent when you confirm a booking and tick "Email the guest".',
            'Booking {reference} - confirmed',
            "Hi {name},\n\nGreat news! Your booking is confirmed. We look forward to welcoming you.\n\n{booking_details}\n\n{message}\n\n{voucher_button}" . $sign,
            'booking', ['{booking_details}', '{voucher_button}']],
        'status_completed' => ['Status: completed', 'Sent when you mark a trip as completed and tick "Email the guest".',
            'Thank you for travelling with us!',
            "Hi {name},\n\nThank you for travelling with us! We hope you had an amazing time on {trip}.\n\nWe would love to hear about your experience:\n\n{review_button}\n\n{message}" . $sign,
            'booking', ['{booking_details}', '{voucher_button}', '{review_button}']],
        'status_cancelled' => ['Status: cancelled', 'Sent when you cancel a booking and tick "Email the guest".',
            'Booking {reference} - cancelled',
            "Hi {name},\n\nYour booking {reference} for {trip} has been cancelled. If this is unexpected, please contact us.\n\n{message}" . $sign,
            'booking', ['{booking_details}']],
        'guest_cancelled_admin' => ['Guest cancelled (to you)', 'Alert when a guest cancels from their voucher page.',
            'Booking cancelled by guest: {reference}',
            "The guest cancelled this booking.\n\n{booking_details}",
            'booking', ['{booking_details}']],
        'review_request' => ['Review request', 'Asks the guest to review their trip (verified review).',
            'How was {trip}?',
            "Hi {name},\n\nThank you for travelling with us! Could you take a minute to share your experience of {trip}? Your review helps other travellers.\n\n{review_button}" . $sign,
            'booking', ['{review_button}']],
        'request_guest' => ['Trip request received (to guest)', 'Sent after a "Plan my trip" request.',
            'We received your trip request {reference}',
            "Hi {name},\n\nThank you for your trip request! Our travel experts are preparing a personalised proposal and will contact you within 24 hours.\n\nYour request:\n{request_details}" . $sign,
            'request', ['{request_details}']],
        'request_admin' => ['Trip request (to you)', 'Alert for every new "Plan my trip" request.',
            'New trip request {reference} from {name}',
            "A new custom trip request was received.\n\n{request_details}\n\nEmail: {guest_email}\nPhone: {guest_phone}\n\n{admin_link}",
            'request', ['{request_details}', '{admin_link}']],
        'newsletter_welcome' => ['Newsletter welcome', 'Sent to new newsletter subscribers (if enabled in Settings).',
            'Welcome to {site_name}',
            "Thanks for subscribing! You'll be the first to hear about new trips, exclusive offers and travel tips.\n\nDon't want these emails? {unsubscribe_link}",
            'newsletter', ['{unsubscribe_link}']],
        'contact_admin' => ['Contact message (to you)', 'Alert for messages sent with the contact form.',
            'New contact message: {subject}',
            "{name} ({guest_email}, {guest_phone}) wrote:\n\n{message}",
            'contact', []],
    ];
}

/** WhatsApp templates: key => [name, default text, placeholder group]. */
function whatsapp_templates(): array
{
    return [
        'booking_received' => ['Booking received', "Hello {name}! 👋\nThank you for booking *{trip}* on {date} ({guests}).\nYour reference is *{reference}*. We'll confirm your booking shortly.\nVoucher: {voucher_link}", 'booking'],
        'booking_confirmed' => ['Booking confirmed', "Hello {name}! ✅\nYour booking *{reference}* for *{trip}* on {date} is *confirmed*.\nGuests: {guests}\nAmount due on arrival: {total}\nVoucher: {voucher_link}\nSee you soon!", 'booking'],
        'price_confirmed' => ['Price confirmation', "Hello {name}!\nFor *{trip}* on {date} ({guests}), the price is *{total}*, to be paid on arrival.\nPlease reply *YES* to confirm your booking {reference}.", 'booking'],
        'reminder' => ['Trip reminder', "Hello {name}! 🌍\nJust a reminder: *{trip}* is on *{date}*.\nPlease have your voucher ready: {voucher_link}\nAny questions? Reply to this message.", 'booking'],
        'review_request' => ['Ask for a review', "Hello {name}! We hope you enjoyed *{trip}* 😊\nWould you share your experience? It takes one minute:\n{review_link}\nThank you!", 'booking'],
        'cancelled' => ['Booking cancelled', "Hello {name},\nYour booking *{reference}* for *{trip}* on {date} has been cancelled.\nIf you have any questions, just reply to this message.", 'booking'],
        'request_reply' => ['Trip request reply', "Hello {name}! 👋\nThank you for your trip request *{reference}*.\nWe're preparing your personalised proposal for {destinations}. Could you tell us a bit more about what you'd like to do?", 'request'],
    ];
}

/** Saved text of a template for a language, or the built-in default (translated if possible). */
function template_text(string $kind, string $key, string $field, ?string $lang = null): string
{
    $lang = $lang ?: default_lang();
    $field_key = $kind === 'email' ? "email_{$key}_{$field}" : "wa_{$key}";
    if ($lang === default_lang()) {
        $saved = (string) (settings_all()['tpl_' . $field_key] ?? '');
    } else {
        $saved = (string) (db_value('SELECT value FROM ' . tbl('translations') . " WHERE lang = ? AND type = 'template' AND item_id = 0 AND field = ?", [$lang, $field_key]) ?: '');
    }
    if (trim($saved) !== '') {
        return $saved;
    }
    return template_default($kind, $key, $field, $lang);
}

/** Built-in text of a template: the language pack version, else the English default (translated if possible). */
function template_default(string $kind, string $key, string $field, string $lang): string
{
    $packed = language_pack_templates($lang)[$kind][$key] ?? null;
    if ($packed !== null) {
        return (string) ($kind === 'email' ? ($packed[$field === 'subject' ? 0 : 1] ?? '') : $packed);
    }
    if ($kind === 'email') {
        $def = email_templates()[$key] ?? null;
        $text = $def ? ($field === 'subject' ? $def[2] : $def[3]) : '';
    } else {
        $text = whatsapp_templates()[$key][1] ?? '';
    }
    return __($text, $lang);
}

function save_template_text(string $kind, string $key, string $field, string $value, string $lang): void
{
    $field_key = $kind === 'email' ? "email_{$key}_{$field}" : "wa_{$key}";
    if ($lang === default_lang()) {
        save_setting('tpl_' . $field_key, $value);
    } else {
        save_translations('template', 0, [$lang => [$field_key => $value]], [$field_key]);
    }
}

/** Replace {placeholders}. Values in $html are inserted as-is; everything else is escaped when $escape is true. */
function fill_placeholders(string $text, array $vars, array $html = [], bool $escape = false): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $html, $escape) {
        $k = '{' . $m[1] . '}';
        if (array_key_exists($k, $html)) {
            return $html[$k];
        }
        if (!array_key_exists($k, $vars)) {
            return $m[0];
        }
        return $escape ? e($vars[$k]) : (string) $vars[$k];
    }, $text);
}

/** Common values for booking messages. */
function booking_vars(array $b, ?string $lang = null): array
{
    $lang = $lang ?: ($b['lang'] ?: default_lang());
    $statuses = booking_statuses($lang);
    $tour = $b['tour_id'] ? localize_row(db_one('SELECT id, title FROM ' . tbl('tours') . ' WHERE id = ?', [$b['tour_id']]), 'tour', $lang) : null;
    return [
        '{name}' => $b['customer_name'], '{reference}' => $b['reference'], '{trip}' => $tour['title'] ?? $b['tour_title'],
        '{date}' => format_date($b['travel_date'], '', $lang), '{guests}' => guests_summary($b, $lang), '{total}' => booking_total($b, $lang),
        '{status}' => $statuses[$b['status']][0] ?? $b['status'], '{voucher_link}' => voucher_url($b), '{review_link}' => review_url($b),
        '{guest_email}' => $b['customer_email'], '{guest_phone}' => $b['customer_phone'], '{notes}' => (string) $b['notes'],
        '{message}' => '', '{site_name}' => setting('site_name', 'WanderLuxe'), '{site_phone}' => setting('contact_phone'), '{site_email}' => setting('contact_email'),
    ];
}

function request_vars(array $r, ?string $lang = null): array
{
    $lang = $lang ?: ($r['lang'] ?: default_lang());
    $travelers = sprintf(__($r['adults'] == 1 ? '%d adult' : '%d adults', $lang), $r['adults'])
        . ($r['children'] ? __(', ', $lang) . sprintf(__($r['children'] == 1 ? '%d child' : '%d children', $lang), $r['children']) : '');
    return [
        '{name}' => $r['name'], '{reference}' => $r['reference'], '{destinations}' => $r['destinations'] ?: __('anywhere', $lang),
        '{start_date}' => $r['start_date'] ? format_date($r['start_date'], '', $lang) . ($r['flexible_dates'] ? ' (' . __('flexible', $lang) . ')' : '') : __('flexible', $lang),
        '{travelers}' => $travelers, '{budget}' => $r['budget'] ?: '-', '{guest_email}' => $r['email'], '{guest_phone}' => $r['phone'],
        '{message}' => '', '{site_name}' => setting('site_name', 'WanderLuxe'), '{site_phone}' => setting('contact_phone'), '{site_email}' => setting('contact_email'),
    ];
}

/** Summary table of a trip request (HTML for emails). */
function request_details_html(array $r, string $lang): string
{
    $v = request_vars($r, $lang);
    $rows = [
        __('Reference', $lang) => $r['reference'], __('Destinations', $lang) => $v['{destinations}'], __('Travel date', $lang) => $v['{start_date}'],
        __('Nights', $lang) => $r['nights'] ?: '-', __('Travellers', $lang) => $v['{travelers}'], __('Budget per person', $lang) => $v['{budget}'],
        __('Travel style', $lang) => $r['styles'] ?: '-', __('Accommodation', $lang) => $r['accommodation'] ?: '-', __('Message', $lang) => (string) $r['message'],
    ];
    return email_table($rows);
}

function email_table(array $rows): string
{
    $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#64748b;vertical-align:top">' . e($k) . '</td><td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold">' . nl2br(e($v)) . '</td></tr>';
    }
    return $html . '</table>';
}

function email_button(string $href, string $label): string
{
    return '<a href="' . e($href) . '" style="display:inline-block;background:#f97316;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:bold">' . e($label) . '</a>';
}

/**
 * Send a templated email. $vars are plain-text placeholder values, $html are HTML blocks
 * ({booking_details}, {voucher_button}...). Returns true when the mail was handed over.
 */
function send_template_email(string $key, string $to, array $vars, array $html = [], ?string $lang = null): bool
{
    $lang = $lang ?: default_lang();
    $subject = fill_placeholders(template_text('email', $key, 'subject', $lang), $vars);
    $body = template_text('email', $key, 'body', $lang);
    // Escape the text, keep line breaks, then insert placeholder values.
    $paras = preg_split('/(\r\n|\r|\n){2,}/', trim($body));
    $out = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $isBlock = isset($html[$p]);
        $filled = fill_placeholders(nl2br(e($p)), $vars, $html, true);
        if (trim(strip_tags($filled, '<table><a><img>')) === '') {
            continue; // an empty placeholder such as {message}
        }
        $out .= $isBlock ? $filled : '<p>' . $filled . '</p>';
    }
    return send_mail($to, $subject, $out, $lang);
}

/** Send a booking email template in the booking's language. */
function send_booking_email(string $key, array $b, ?string $to = null, array $extra = [], ?string $lang = null): bool
{
    $lang = $lang ?: ($b['lang'] ?: default_lang());
    $vars = $extra + booking_vars($b, $lang);
    $html = [
        '{booking_details}' => booking_email_table($b, $lang),
        '{voucher_button}' => email_button(voucher_url($b), __('View & print your voucher', $lang)),
        '{review_button}' => email_button(review_url($b), __('Write a review', $lang)),
        '{admin_link}' => '<a href="' . e(url('admin/booking.php?id=' . $b['id'])) . '">' . e(__('Open in the admin panel', $lang)) . '</a>',
    ];
    return send_template_email($key, $to ?? $b['customer_email'], $vars, $html, $lang);
}

/** WhatsApp text of a template for a booking or trip request. */
function whatsapp_text(string $key, array $vars, ?string $lang = null): string
{
    return fill_placeholders(template_text('wa', $key, '', $lang), $vars);
}

function whatsapp_link(string $phone, string $text): string
{
    $digits = preg_replace('/\D/', '', $phone);
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}
