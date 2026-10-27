<?php
defined('ABSPATH') || exit;
final class Mira_Quotes {
    public const COOKIE = 'mira_quote_session';
    private static bool $diagnostic_mail = false;
    private static bool $pdf_test_mail = false;
    // Client-approved voluntary cancellation period; statutory rights are additional.
    public const CANCELLATION_HOURS = 24;
    public static function cancellation_policy(): string {
        return 'Sie können Ihre Buchung innerhalb von 24 Stunden nach ausdrücklicher Annahme des Angebots stornieren. Nach Ablauf dieser 24 Stunden bleibt die Buchung verbindlich, vorbehaltlich Ihrer gesetzlichen Rechte. Gesetzliche Widerrufs- und sonstige zwingende Verbraucherrechte bleiben unberührt. Für eine Stornierung kontaktieren Sie Service Mira unter inf@service-mira.de unter Angabe Ihrer Angebots-ID.';
    }

    /** Only additional, distinct, client-provided legal information belongs in the secondary section. */
    public static function additional_cancellation_info($value): string {
        $value = trim((string) $value);
        $value = str_ireplace('info@service-mira.de', 'inf@service-mira.de', $value);
        if ($value === '') { return ''; }
        $policy = trim(self::cancellation_policy());
        // Older snapshots contained the approved policy alone or before genuinely separate text.
        if ($value === $policy) { return ''; }
        if (strpos($value, $policy) === 0) {
            $value = trim(substr($value, strlen($policy)));
            $value = preg_replace('/^Weitere vom Auftraggeber geprüfte Informationen:\s*/u', '', $value);
            return trim($value);
        }
        // Avoid duplicating the policy when already stored as extra text.
        return $value;
    }

    public static function table(): string { global $wpdb; return $wpdb->prefix . 'mira_quotes'; }
    public static function settings(): array {
        $s = array_replace(Mira_Domain::defaults(), (array) get_option('mira_settings', []));
        // Historical default was incorrect. Preserve any separately configured legitimate mailbox.
        if (in_array(strtolower(trim((string) ($s['admin_email'] ?? ''))), ['info@service.mira.de', 'info@service-mira.de'], true)) {
            $s['admin_email'] = 'inf@service-mira.de';
        }
        // Client-confirmed fixed hourly prices override legacy stored 17.50 / price-on-request.
        foreach (Mira_Domain::defaults()['services'] as $id => $service) {
            $existing = isset($s['services'][$id]) && is_array($s['services'][$id]) ? $s['services'][$id] : [];
            $s['services'][$id] = array_replace($service, $existing, ['cents' => 2000, 'confirmed' => true]);
        }
        $s['minimum_units'] = null; // No business minimum: technical quarter-hour increments still apply.
        // Distance method is pending: never reuse a stale flat fee as an approved travel price.
        $s['fee_cents'] = null;
        $s['fee_label'] = 'Anfahrt: 10 km frei; danach 0,50 EUR/km. Fahrstrecke noch zu bestätigen.';
        $s['vat_mode'] = 'net';
        // Standard 19% German VAT for staging illustration; owner must validate tax treatment.
        $s['vat_bps'] = 1900;
        // Invalidate acceptance until travel calculation and contract conditions are confirmed.
        // Explicit, independently authorized deployment gate. Never enabled on staging.
        // Define both constants in server config ONLY after client approval and E2E testing.
        $production_approved = defined('MIRA_PRODUCTION_BOOKINGS_ENABLED') && MIRA_PRODUCTION_BOOKINGS_ENABLED === true
            && defined('MIRA_PRODUCTION_MAIL_APPROVED') && MIRA_PRODUCTION_MAIL_APPROVED === true
            && !self::allowed();
        $s['acceptance'] = $production_approved && !empty($s['acceptance']);
        $s['reviewed'] = $production_approved && !empty($s['reviewed']);
        return $s;
    }
    public static function allowed(): bool {
        $url = wp_parse_url(home_url('/')); $host = strtolower($url['host'] ?? ''); $path = rtrim($url['path'] ?? '', '/');
        return (in_array($host, ['www.service-mira.de', 'service-mira.de'], true) && $path === '/yamato') || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test');
    }
    public static function boot(): void {
        add_shortcode('service_mira_quote', [self::class, 'form']);
        foreach (['mira_submit' => 'submit', 'mira_accept' => 'accept'] as $action => $method) {
            add_action('admin_post_' . $action, [self::class, $method]); add_action('admin_post_nopriv_' . $action, [self::class, $method]);
        }
        add_action('admin_post_mira_rehearsal_view', [self::class, 'rehearsal_view']);
        add_action('admin_post_nopriv_mira_rehearsal_view', [self::class, 'rehearsal_view']);
        add_action('template_redirect', [self::class, 'session']);
        add_action('send_headers', function () { if (self::allowed()) { header('X-Robots-Tag: noindex, nofollow, noarchive', true); } });
        add_filter('wp_robots', function ($r) { if (self::allowed()) { $r['noindex'] = true; $r['nofollow'] = true; } return $r; });
        // Global WordPress staging mail guard. No live delivery setting is provided.
        add_filter('pre_wp_mail', function ($pre) { return self::allowed() && !self::reroute() && !self::$diagnostic_mail && !self::$pdf_test_mail ? false : $pre; }, PHP_INT_MAX);
        add_filter('wp_mail', function ($mail) {
            if (self::allowed() && self::reroute() && !self::$pdf_test_mail) { $mail['to'] = MIRA_TEST_RECIPIENT; $mail['subject'] = '[MIRA STAGING] ' . $mail['subject']; $mail['headers'] = ['Content-Type: text/plain; charset=UTF-8']; $mail['attachments'] = []; }
            return $mail;
        }, PHP_INT_MAX);
    }
    /** One explicit, admin-only transport probe. No quotation or customer data is sent. */
    public static function diagnostic_mail(string $recipient): bool {
        self::guard();
        if (!current_user_can('manage_options') || !is_email($recipient) ||
            !hash_equals(strtolower((string) wp_get_current_user()->user_email), strtolower($recipient))) {
            self::fail('Nur die eigene WordPress-Admin-Adresse ist für den Versandtest erlaubt.', 403);
        }
        $was = self::$diagnostic_mail;
        self::$diagnostic_mail = true;
        try {
            return (bool) wp_mail($recipient, '[MIRA STAGING] Test des E-Mail-Transports',
                "Service Mira Staging - technische Test-E-Mail.\n\n" .
                "Keine Kundendaten, kein Angebot, kein Annahmelink.\n" .
                "Diese Nachricht ist keine Buchung und kein verbindliches Angebot.\n", 
                ['Content-Type: text/plain; charset=UTF-8']);
        } finally {
            self::$diagnostic_mail = $was;
        }
    }
    /** Admin-initiated staging-only PDF transport test; never includes customer data. */
    public static function diagnostic_pdf_mail(): bool {
        self::guard();
        if (!current_user_can('manage_options')) { self::fail('Keine Berechtigung.', 403); }
        $recipient = 'staging-test@example.invalid'; // One explicitly authorized test mailbox; no customer addresses.
        $snapshot = [
            'quote_id' => 'TEST-NOT-BINDING',
            'customer' => ['name' => 'Testkunde (keine echten Kundendaten)'],
            'schedule' => ['preferred_date' => '2026-10-30', 'preferred_time' => '10:00-12:00'],
            'price' => [
                'service' => 'Unterhaltsreinigung', 'units' => 8, 'hourly_cents' => 2000,
                'base_cents' => 4000, 'fee_cents' => null, 'vat_bps' => 1900,
                'tax_cents' => 760, 'gross_cents' => null,
            ],
        ];
        $path = self::temporary_pdf($snapshot);
        if ($path === null) { return false; }
        $was = self::$pdf_test_mail;
        self::$pdf_test_mail = true;
        try {
            return (bool) wp_mail($recipient, '[MIRA STAGING TEST] PDF-Anhang - keine Buchung',
                "Testnachricht mit synthetischem PDF-Angebot.\nKeine echten Kundendaten.\nKein Annahmelink, keine Buchung.\n",
                ['Content-Type: text/plain; charset=UTF-8'], [$path]);
        } finally {
            self::$pdf_test_mail = $was;
            if (is_file($path)) { @unlink($path); }
        }
    }
    /** Combined staging-only transport journey: synthetic PDF + one-time review URL. */
    public static function diagnostic_full_journey(): bool {
        self::guard();
        if (!self::allowed() || !current_user_can('manage_options')) { self::fail('Nur Staging-Administratoren.', 403); }
        $recipient = 'staging-test@example.invalid';
        $snapshot = [
            'quote_id' => 'STAGING-ONLY',
            'customer' => ['name' => 'Synthetischer Testkunde'],
            'schedule' => ['preferred_date' => '2026-10-30', 'preferred_time' => '10:00-12:00'],
            'price' => ['service' => 'Unterhaltsreinigung', 'units' => 8, 'hourly_cents' => 2000,
                        'base_cents' => 4000, 'fee_cents' => null, 'vat_bps' => 1900,
                        'tax_cents' => 760, 'gross_cents' => null],
        ];
        $path = self::temporary_pdf($snapshot);
        if ($path === null) { return false; }
        $url = self::issue_rehearsal_link();
        $was = self::$pdf_test_mail;
        self::$pdf_test_mail = true;
        try {
            $sent = (bool) wp_mail($recipient, '[MIRA STAGING TEST] PDF + sicherer Testlink - KEINE BUCHUNG',
                "Reiner technischer Test ohne Kundendaten oder verbindliche Buchung.\n\n" .
                "Synthetische PDF-Vorschau im Anhang. Geschuetzter Testlink (15 Minuten, nur angemeldeter Staging-Administrator):\n" .
                $url . "\n\nOffene Anfahrt, offener Brutto-Endpreis: KEINE rechtsverbindliche Annahme moeglich.\n",
                ['Content-Type: text/plain; charset=UTF-8'], [$path]);
        } finally {
            self::$pdf_test_mail = $was;
            if (is_file($path)) { @unlink($path); }
        }
        set_transient('mira_rehearsal_link_url_' . get_current_user_id(), $url, 15 * MINUTE_IN_SECONDS);
        return $sent;
    }
    /** Issue an expiring one-time rehearsal token; never references a customer record. */
    public static function issue_rehearsal_link(): string {
        self::guard();
        if (!current_user_can('manage_options')) { self::fail('Nicht autorisiert.', 403); }
        $token = bin2hex(random_bytes(32));
        $uid = get_current_user_id();
        $record = ['token_hash' => hash('sha256', $token), 'used' => false, 'issued_utc' => gmdate('c'), 'user_id' => $uid];
        set_transient('mira_rehearsal_link_' . $uid, $record, 15 * MINUTE_IN_SECONDS);
        return add_query_arg(['action' => 'mira_rehearsal_view', 'uid' => $uid, 'token' => $token], admin_url('admin-post.php'));
    }
    /** Complete a synthetic acceptance from a genuine review + consent + POST; no real booking. */
    public static function rehearsal_view(): void {
        self::guard();
        if (!current_user_can('manage_options')) { self::fail('Nur Staging-Administratoren.', 403); }
        $uid = isset($_REQUEST['uid']) ? absint($_REQUEST['uid']) : 0;
        $token = isset($_REQUEST['token']) && is_string($_REQUEST['token']) ? wp_unslash($_REQUEST['token']) : '';
        if (!$uid || get_current_user_id() !== $uid || !preg_match('/^[a-f0-9]{64}$/D', $token)) { self::fail('Testlink ungültig.', 403); }
        $key = 'mira_rehearsal_link_' . $uid;
        $record = get_transient($key);
        if (!is_array($record) || !empty($record['used']) || !hash_equals((string) ($record['token_hash'] ?? ''), hash('sha256', $token))) { self::fail('Testlink abgelaufen oder verbraucht.', 403); }
        nocache_headers();
        header("X-Robots-Tag: noindex, nofollow, noarchive");
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $url = add_query_arg(['action' => 'mira_rehearsal_view', 'uid' => $uid, 'token' => $token], admin_url('admin-post.php'));
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!isset($_POST['_mira_rehearsal_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mira_rehearsal_nonce'])), 'mira_rehearsal_' . $record['token_hash']) || ($_POST['confirm'] ?? '') !== '1') { self::fail('Bestätigung fehlt.', 403); }
            // Invalidate BEFORE sending to ensure replays cannot send multiple confirmations.
            delete_transient($key);
            $result = self::diagnostic_acceptance_rehearsal();
            self::page('Testannahme dokumentiert', '<p><strong>Nur Simulation – KEINE BUCHUNG.</strong></p><p>Nachweis UTC: ' . esc_html($result['recorded_utc']) . '</p><p>Snapshot SHA-256: <code>' . esc_html($result['quote_sha256']) . '</code></p><p>Bestätigungs-E-Mail an die autorisierte Testadresse: ' . (!empty($result['confirmation_transport_accepted']) ? 'vom Transport angenommen' : 'nicht angenommen') . '.</p>');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { self::fail('Methode nicht erlaubt.', 405); }
        $body = '<div class="mira-offer-status"><strong>STAGING · Synthetisches Angebot · KEINE BUCHUNG</strong><small>Unterhaltsreinigung · 2 Stunden · 20,00 EUR netto/Stunde · 40,00 EUR netto · 7,60 EUR vorläufige MwSt. · 47,60 EUR nur Leistung. Anfahrt und endgültiger Gesamtpreis sind offen.</small></div>';
        $body .= '<p>Der Wunschtermin ist unverbindlich. Dieser Vorgang dient nur der technischen Prüfung. Keine kostenpflichtige Bestellung wird erstellt.</p>';
        $body .= '<form method="post" action="' . esc_url($url) . '">' . wp_nonce_field('mira_rehearsal_' . $record['token_hash'], '_mira_rehearsal_nonce', false, false) . '<p><label><input type="checkbox" name="confirm" value="1" required> Ich bestätige die reine Testsimulation. Mir ist bekannt, dass keine echte Buchung entsteht.</label></p><button type="submit">TESTBESTÄTIGUNG AUSLÖSEN – KEINE BUCHUNG</button></form>';
        self::page('Geschütztes Testangebot', $body);
    }
    /** Isolated synthetic acceptance rehearsal. Never updates a customer quotation or booking. */
    public static function diagnostic_acceptance_rehearsal(): array {
        self::guard();
        if (!current_user_can('manage_options') || !self::allowed()) { self::fail('Nur autorisierte Staging-Administratoren.', 403); }
        $recipient = 'staging-test@example.invalid';
        $snapshot = [
            'quote_id' => 'STAGING-' . strtoupper(bin2hex(random_bytes(6))),
            'service' => 'Unterhaltsreinigung',
            'hours' => 2,
            'net_cents' => 4000,
            'vat_cents' => 760,
            'gross_service_cents' => 4760,
            'travel' => 'Unbestätigt (keine Buchung möglich)',
            'preferred_date' => '30.10.2026',
            'preferred_time' => '10:00–12:00',
        ];
        $at = gmdate('Y-m-d H:i:s');
        $encoded = wp_json_encode($snapshot);
        $evidence = [
            'type' => 'staging_acceptance_rehearsal',
            'binding' => false,
            'status' => 'simulated_only',
            'recorded_utc' => $at,
            'quote_sha256' => hash('sha256', $encoded),
            'snapshot' => $snapshot,
            'confirmation_transport_accepted' => false,
        ];
        $subject = '[MIRA STAGING TEST] Annahme-Simulation – KEINE BUCHUNG';
        $body = "Service Mira – technische Annahme-Simulation\n\n" .
            "Keine rechtsverbindliche Annahme. Keine echte Kundendaten. Keine Terminbuchung.\n\n" .
            'Referenz: ' . $snapshot['quote_id'] . "\n" .
            'Simulierter Nachweis UTC: ' . $at . "\n" .
            'SHA-256 Angebotssnapshot: ' . $evidence['quote_sha256'] . "\n" .
            "Leistung: Unterhaltsreinigung; 2 Stunden; 40,00 EUR netto; 7,60 EUR MwSt.; 47,60 EUR nur Leistung.\n" .
            "Fahrtkosten und Gesamtpreis sind unbestätigt. Echte Annahme bleibt gesperrt.\n";
        $was = self::$pdf_test_mail;
        self::$pdf_test_mail = true;
        try { $sent = (bool) wp_mail($recipient, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']); }
        finally { self::$pdf_test_mail = $was; }
        $evidence['confirmation_transport_accepted'] = $sent;
        // Store only synthetic records, TTL one day; customer quote table remains untouched.
        set_transient('mira_acceptance_rehearsal_' . get_current_user_id(), $evidence, DAY_IN_SECONDS);
        return $evidence;
    }
    public static function reroute(): bool { return defined('MIRA_MAIL_MODE') && MIRA_MAIL_MODE === 'reroute' && defined('MIRA_TEST_RECIPIENT') && is_email(MIRA_TEST_RECIPIENT); }
    public static function activate(): void {
        if (!self::allowed()) { wp_die('Dieses Paket ist ausschließlich für /yamato/ oder lokale .test-Installationen vorgesehen.'); }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php'; global $wpdb; $table = self::table();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            quote_id varchar(36) NOT NULL,
            submission_hash char(64) NOT NULL,
            status varchar(12) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            accepted_at datetime NULL,
            customer_email varchar(254) NOT NULL,
            snapshot longtext NOT NULL,
            snapshot_mac char(64) NOT NULL,
            token_hash char(64) NOT NULL,
            evidence longtext NULL,
            mail_state text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY quote_id (quote_id),
            UNIQUE KEY submission_hash (submission_hash),
            KEY status_created (status,created_at),
            KEY customer_email (customer_email)
        ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
        update_option('mira_schema', '1');
    }
    public static function private_headers(): void {
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers(); header('X-Robots-Tag: noindex, nofollow, noarchive', true); header('Referrer-Policy: no-referrer', true); header('X-Content-Type-Options: nosniff', true); header('X-Frame-Options: DENY', true);
    }
    public static function guard(): void {
        self::private_headers();
        if (!self::allowed()) { self::fail('Staging-Schutz: Diese Funktion ist hier deaktiviert.', 403); }
        $host = strtolower(wp_parse_url(home_url('/'), PHP_URL_HOST) ?? '');
        if (in_array($host, ['service-mira.de', 'www.service-mira.de'], true) && !is_ssl()) { self::fail('HTTPS ist für Angebotsdaten erforderlich. Proxy-/SSL-Konfiguration prüfen.', 403); }
    }
    public static function fail(string $text, int $code = 400): void { wp_die(esc_html($text), 'Service Mira', ['response' => $code]); }
    public static function value(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim(wp_unslash($_POST[$key])) : ''; }
    private static function cookie(string $name, string $value, int $expires, string $path = '/'): void {
        setcookie($name, $value, ['expires' => $expires, 'path' => $path, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']); $_COOKIE[$name] = $value;
    }
    public static function session(): void {
        if (!self::allowed() || !is_singular()) { return; } global $post;
        if (!$post || !has_shortcode($post->post_content, 'service_mira_quote')) { return; }
        self::private_headers();
        wp_enqueue_style('mira-quotes', plugins_url('assets/quotes.css', MIRA_QUOTES_FILE), [], '1.2.6');
        wp_enqueue_script('mira-quotes', plugins_url('assets/quotes.js', MIRA_QUOTES_FILE), [], '1.2.6', true);
        if (!self::session_key()) { self::cookie(self::COOKIE, bin2hex(random_bytes(32)), time() + 7200, wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/'); }
    }
    public static function session_key(): string { $key = $_COOKIE[self::COOKIE] ?? ''; return is_string($key) && preg_match('/^[a-f0-9]{64}$/D', $key) ? $key : ''; }
    public static function form(): string {
        if (!self::allowed()) { return '<p>Der Angebotsrechner ist nur auf Staging verfügbar.</p>'; }
        if (!self::session_key()) { return '<p>Bitte diese Seite direkt aufrufen und notwendige Sitzungscookies zulassen. Der Rechner benötigt eine ungecachte WordPress-Seite mit diesem Shortcode im Seiteninhalt.</p>'; }
        $s = self::settings(); $privacy = $s['privacy_page'] ? get_permalink($s['privacy_page']) : get_privacy_policy_url();
        if (!$privacy) { return '<p>Der Rechner wird nach Einrichtung der Datenschutzhinweise freigeschaltet.</p>'; }
        wp_enqueue_style('mira-quotes', plugins_url('assets/quotes.css', MIRA_QUOTES_FILE), [], '1.2.6');
        wp_enqueue_script('mira-quotes', plugins_url('assets/quotes.js', MIRA_QUOTES_FILE), [], '1.2.6', true);
        $ticket = bin2hex(random_bytes(16)); $started = time(); $seal = hash_hmac('sha256', $ticket . '|' . $started . '|' . self::session_key(), wp_salt('nonce'));
        ob_start(); ?>
        <section class="mira-quote" aria-labelledby="mira-title">
          <header class="mira-quote-head">
            <span class="mira-eyebrow"><span class="mira-dot" aria-hidden="true"></span> SERVICE MIRA · BREMEN &amp; UMGEBUNG</span>
            <div class="mira-heading-grid"><div><h2 id="mira-title">Saubere Räume. <em>Ihr Angebot, ganz einfach.</em></h2><p>Wählen Sie Ihre Reinigung, den gewünschten Termin und den Zeitumfang. Wir erstellen Ihre unverbindliche Anfrage.</p></div></div>
            <nav class="mira-step-nav" aria-label="Formularabschnitte"><a href="#mira-section-services"><b>01</b><span>Leistung</span></a><a href="#mira-section-schedule"><b>02</b><span>Termin &amp; Dauer</span></a><a href="#mira-section-contact"><b>03</b><span>Kontaktdaten</span></a></nav>
          </header>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mira-form" data-mira-config="<?php echo esc_attr(wp_json_encode(['services' => $s['services'], 'vat_mode' => $s['vat_mode'], 'vat_bps' => $s['vat_bps'], 'fee_cents' => $s['fee_cents'], 'fee_label' => $s['fee_label'], 'minimum_units' => $s['minimum_units']])); ?>">
            <input type="hidden" name="action" value="mira_submit"><?php wp_nonce_field('mira_submit_' . self::session_key(), '_mira_nonce', false); ?>
            <input type="hidden" name="ticket" value="<?php echo esc_attr($ticket); ?>"><input type="hidden" name="started" value="<?php echo esc_attr((string) $started); ?>"><input type="hidden" name="seal" value="<?php echo esc_attr($seal); ?>">
            <div class="mira-form-grid">
             <div class="mira-form-sections">
              <section id="mira-section-services" class="mira-section" aria-labelledby="mira-services-heading"><h3 id="mira-services-heading" class="mira-section-heading"><span class="mira-section-num">01</span><span>Welche Leistung benötigen Sie?</span></h3><p class="mira-help-intro">Wählen Sie eine Hauptleistung aus dem bestehenden Service-Mira-Angebot.</p>
                <div class="mira-service-grid" role="radiogroup" aria-label="Gewünschte Reinigungsleistung">
                  <?php foreach ($s['services'] as $id => $v) : ?><label class="mira-service-choice"><input type="radio" name="service" value="<?php echo esc_attr($id); ?>" required><span class="mira-checkshape" aria-hidden="true">✓</span><span class="mira-service-copy"><strong><?php echo esc_html($v['name']); ?></strong><?php if ($v['cents'] !== null) : ?><small>Basis: <?php echo esc_html(Mira_Domain::money($v['cents'])); ?> / Std.</small><?php else : ?><small>Preis auf Anfrage</small><?php endif; ?></span></label><?php endforeach; ?>
                </div><p class="mira-subtle">Weitere Leistungen können Sie im Bemerkungsfeld anfragen. Die Berechnung bezieht sich auf die ausgewählte Hauptleistung.</p>
              </section>
              <section id="mira-section-schedule" class="mira-section" aria-labelledby="mira-schedule-heading"><h3 id="mira-schedule-heading" class="mira-section-heading"><span class="mira-section-num">02</span><span>Wann und wie lange?</span></h3>
                <div class="mira-field-stack"><label for="mira-hours">Gewünschte Reinigungsdauer *</label><div class="mira-duration-picks" aria-label="Schnellauswahl Reinigungsdauer"><?php foreach (['2','2.5','3','3.5','4','4.5','5','5.5'] as $hour) : ?><button type="button" class="mira-duration-button" data-hours="<?php echo esc_attr($hour); ?>" aria-pressed="false"><?php echo esc_html(str_replace('.', ',', $hour)); ?> Std.</button><?php endforeach; ?></div><div class="mira-custom-duration"><span>Andere Dauer (15-Minuten-Schritte)</span><input id="mira-hours" name="hours" type="number" min="<?php echo esc_attr((string) (($s['minimum_units'] ?? 1) / 4)); ?>" max="100" step="0.25" value="2" required aria-describedby="mira-hours-hint"></div><small id="mira-hours-hint">Keine Mindestbuchungsdauer; Abrechnung in 15-Minuten-Schritten.</small></div>
                <div class="mira-two-fields"><div class="mira-field-stack"><label for="mira-date">Gewünschter Start (Datum) *</label><div class="mira-german-date"><input id="mira-date" type="text" inputmode="numeric" autocomplete="off" placeholder="TT.MM.JJJJ" maxlength="10" pattern="[0-9]{2}\.[0-9]{2}\.[0-9]{4}" required aria-describedby="mira-date-hint" aria-label="Gewünschter Start im Format Tag Punkt Monat Punkt Jahr"><input id="mira-date-value" name="preferred_date" type="hidden"><button class="mira-calendar-control" type="button" aria-label="Kalender öffnen" aria-controls="mira-date-picker"><svg viewBox="0 0 24 24" width="21" height="21" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></button><input id="mira-date-picker" type="date" lang="de-DE" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" aria-label="Datum mit Kalender auswählen" title="Kalenderdatum"></div><small id="mira-date-hint" class="mira-date-hint" aria-live="polite">Datumsformat: TT.MM.JJJJ</small></div><div class="mira-field-stack"><label for="mira-time">Gewünschtes Zeitfenster *</label><select id="mira-time" name="preferred_time" required><option value="">Bitte wählen</option><option value="05:00–14:00">05:00–14:00 Uhr</option><option value="14:00–22:00">14:00–22:00 Uhr</option><option value="22:00–05:00">22:00–05:00 Uhr</option></select></div></div>
                <p class="mira-subtle">Wunschzeiten sind unverbindlich und werden vor einer Zusage geprüft.</p>
                <div class="mira-field-stack"><label>Wie oft benötigen Sie die Reinigung? *</label><div class="mira-frequency-grid" role="radiogroup" aria-label="Reinigungshäufigkeit"><label class="mira-frequency-choice"><input type="radio" name="frequency" value="once" checked required><span><strong>Einmalig</strong><small>Ein Termin</small></span></label><label class="mira-frequency-choice"><input type="radio" name="frequency" value="weekly"><span><strong>Wöchentlich</strong><small>Regelmäßig</small></span></label><label class="mira-frequency-choice"><input type="radio" name="frequency" value="biweekly"><span><strong>Alle 2 Wochen</strong><small>14-tägig</small></span></label><label class="mira-frequency-choice"><input type="radio" name="frequency" value="monthly"><span><strong>Monatlich</strong><small>Regelmäßig</small></span></label></div><p class="mira-subtle">Der angezeigte Betrag bezieht sich immer auf einen Reinigungstermin, ohne Abo-Rabatt oder automatische Folgekosten.</p></div>
              </section>
              <section id="mira-section-contact" class="mira-section mira-contact-section" aria-labelledby="mira-contact-heading">
                <div class="mira-contact-heading-row">
                  <h3 id="mira-contact-heading" class="mira-section-heading"><span class="mira-section-num">03</span><span>Ihre Kontaktdaten</span></h3>
                  <p class="mira-contact-intro">Fast geschafft! Bitte geben Sie Ihre Kontaktdaten und den Einsatzort an.</p>
                </div>
                <div class="mira-contact-fields mira-two-fields">
                  <div class="mira-field-stack"><label for="mira-name">Vollständiger Name *</label><input id="mira-name" name="customer_name" maxlength="120" autocomplete="name" required placeholder="Ihr Name"></div>
                  <div class="mira-field-stack"><label for="mira-email">E-Mail-Adresse *</label><input id="mira-email" name="email" type="email" maxlength="254" autocomplete="email" required placeholder="name@beispiel.de"></div>
                  <div class="mira-field-stack"><label for="mira-phone">Telefon (optional)</label><input id="mira-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" placeholder="Ihre Telefonnummer"></div>
                  <div class="mira-field-stack"><label for="mira-postcode">Postleitzahl (5-stellig) *</label><input id="mira-postcode" name="postcode" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code" placeholder="z. B. 28195" required></div>
                </div>
                <div class="mira-field-stack mira-contact-full"><label for="mira-address">Einsatzort / Anschrift *</label><textarea id="mira-address" name="address" maxlength="500" autocomplete="street-address" rows="2" placeholder="Straße, Hausnummer, Ort" required></textarea></div>
                <div class="mira-field-stack mira-contact-full"><label for="mira-message">Auftragsbeschreibung / Bemerkungen (optional)</label><textarea id="mira-message" name="message" maxlength="2000" rows="2" placeholder="Welche Räume? Besondere Wünsche oder weitere Leistungen?"></textarea></div>
                <div class="mira-submit-panel mira-contact-submit">
                  <div class="mira-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                  <div class="mira-contact-final-row">
                    <label class="mira-check"><input type="checkbox" name="privacy" value="1" required><span>Ich habe die <a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener noreferrer">Datenschutzhinweise</a> zur Verarbeitung meiner Angebotsanfrage gelesen. *</span></label>
                    <button type="submit" class="mira-submit-button">Unverbindliches Angebot anfragen <span aria-hidden="true">→</span></button>
                  </div>
                  <p class="mira-note">Staging-Testbetrieb. Keine kostenpflichtige Beauftragung durch diese Anfrage. Der E-Mail-Versand ist gesperrt oder auf eine Testadresse umgeleitet.</p>
                </div>
              </section>
             </div>
             <aside class="mira-summary-panel" aria-label="Ihre Angebotsübersicht"><div class="mira-summary-sticky"><div class="mira-summary-head"><span class="mira-summary-tag">IHRE ANFRAGE</span><h3>Alles im Überblick.</h3><p>Ihre Auswahl wird direkt aktualisiert.</p></div><dl class="mira-summary-choices"><div><dt>Leistung</dt><dd data-mira-summary="service">Noch nicht gewählt</dd></div><div><dt>Dauer</dt><dd data-mira-summary="duration">2 Std.</dd></div><div><dt>Wunschtermin</dt><dd data-mira-summary="date">Bitte auswählen</dd></div><div><dt>Zeitfenster</dt><dd data-mira-summary="time">Bitte auswählen</dd></div><div><dt>Häufigkeit</dt><dd data-mira-summary="frequency">Einmalig</dd></div></dl><div class="mira-price" role="status" aria-live="polite" aria-atomic="true">Wählen Sie Ihre Leistung, um die Berechnungsbasis zu sehen.</div><p class="mira-summary-disclaimer">19 % MwSt. auf die Leistung als vorläufige Kalkulation. Anfahrt und endgültiger Gesamtpreis stehen bis zur Distanzbestätigung aus. Noch keine Buchung.</p><div class="mira-support"><span class="mira-support-icon" aria-hidden="true">✦</span><span><strong>Wir beraten Sie gern.</strong><small>Bei Fragen: <a href="mailto:inf@service-mira.de">inf@service-mira.de</a></small></span></div></div></aside>
            </div>
            <noscript><p>JavaScript ist deaktiviert. Die Berechnung erfolgt serverseitig nach dem Absenden.</p></noscript>
          </form>
        </section><?php return ob_get_clean();
    }
    public static function throttle(): void {
        global $wpdb; $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $key = '_mira_rate_' . sprintf('%012d', intdiv(time(), 900)) . '_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 24);
        $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,'1','off') ON DUPLICATE KEY UPDATE option_value=CAST(option_value AS UNSIGNED)+1", $key));
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $key));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name<%s", $wpdb->esc_like('_mira_rate_') . '%', '_mira_rate_' . sprintf('%012d', intdiv(time(), 900) - 8) . '_'));
        if ($count < 1 || $count > 20) { self::fail('Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.', 429); }
    }
    public static function submit(): void {
        self::guard(); if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { self::fail('POST erforderlich.', 405); } self::throttle();
        $session = self::session_key();
        if (!$session || !wp_verify_nonce(self::value('_mira_nonce'), 'mira_submit_' . $session)) { self::fail('Sitzung abgelaufen. Bitte Formular neu laden.', 403); }
        $ticket = self::value('ticket'); $started = self::value('started');
        if (!preg_match('/^[a-f0-9]{32}$/D', $ticket) || !ctype_digit($started) || !hash_equals(hash_hmac('sha256', $ticket . '|' . $started . '|' . $session, wp_salt('nonce')), self::value('seal')) || time() - (int) $started < 3 || time() - (int) $started > 7200 || self::value('website') !== '') { self::fail('Formularprüfung fehlgeschlagen. Bitte erneut versuchen.', 403); }
        $name = sanitize_text_field(self::value('customer_name')); $email_raw = self::value('email'); $email = sanitize_email($email_raw); $phone = sanitize_text_field(self::value('phone')); $address = sanitize_textarea_field(self::value('address'));
        if ($name === '' || strlen($name) > 120 || $email !== $email_raw || !is_email($email) || strlen($email) > 254 || strlen($phone) > 40 || $address === '' || strlen($address) > 500 || self::value('privacy') !== '1') { self::fail('Bitte gültige Kontaktdaten und Datenschutzhinweis bestätigen.'); }
        // Scheduling preferences are informational only: no automatic booking or price discounts.
        $postcode = self::value('postcode'); $message = sanitize_textarea_field(self::value('message'));
        $preferred_date = self::value('preferred_date'); $preferred_time = self::value('preferred_time'); $frequency = self::value('frequency');
        $allowed_times = ['05:00–14:00', '14:00–22:00', '22:00–05:00'];
        $allowed_frequencies = ['once', 'weekly', 'biweekly', 'monthly'];
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $preferred_date)) { self::fail('Bitte ein gültiges Wunschdatum wählen.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $preferred_date, wp_timezone());
        $today = new DateTimeImmutable('today', wp_timezone());
        if (!preg_match('/^[0-9]{5}$/D', $postcode) || strlen($message) > 2000 || !$date || $date->format('Y-m-d') !== $preferred_date || $date < $today || $date > $today->modify('+365 days') || !in_array($preferred_time, $allowed_times, true) || !in_array($frequency, $allowed_frequencies, true)) { self::fail('Bitte gültige PLZ, Terminwünsche und Häufigkeit wählen.'); }
        $s = self::settings(); $privacy = $s['privacy_page'] ? get_permalink($s['privacy_page']) : get_privacy_policy_url(); if (!$privacy) { self::fail('Datenschutzhinweise fehlen.', 403); }
        try { $price = Mira_Domain::price($s, self::value('service'), Mira_Domain::units(self::value('hours'))); } catch (InvalidArgumentException $e) { self::fail($e->getMessage()); }
        // Compute the actual two-leg driving distance for new quotations when a
        // properly configured routing service is available. Fail closed: an API
        // error must NEVER turn into a zero-euro travel charge.
        $route = null;
        if (Mira_Routes::configured()) {
            try {
                $destination = trim($address . ', ' . $postcode . ', Germany');
                $route = Mira_Routes::quote($destination, (int) $price['base_cents'], 1900);
                $price['fee_cents'] = $route['travel_cents'];
                $price['fee_label'] = 'Hin- und Rückfahrt: ' . number_format($route['roundtrip_m'] / 1000, 2, ',', '.') . ' km (erste 10 km frei; danach 0,50 EUR/km)';
                $price['net_cents'] = $route['net_cents'];
                $price['tax_cents'] = $route['vat_cents'];
                $price['gross_cents'] = $route['gross_cents'];
                // Pricing completeness does not imply legally approved booking terms.
                $price['complete'] = false;
                $price['eligible'] = false;
            } catch (Throwable $e) {
                // Do not leak provider/API details to public visitors or assume zero distance.
                error_log('[Service Mira] Driving route calculation unavailable for new staging quotation.');
            }
        }
        $quote_id = wp_generate_uuid4(); $token = bin2hex(random_bytes(32));
        $snapshot = ['version' => 2, 'quote_id' => $quote_id, 'created_utc' => gmdate('c'), 'expires_utc' => gmdate('c', time() + $s['ttl_hours'] * 3600), 'test' => true, 'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone, 'postcode' => $postcode, 'address' => $address, 'message' => $message], 'schedule' => ['preferred_date' => $preferred_date, 'preferred_time' => $preferred_time, 'frequency' => $frequency, 'confirmed' => false], 'price' => $price, 'route' => $route, 'provider' => $s['provider'], 'terms' => $s['terms'], 'cancellation' => self::cancellation_policy() . ($s['cancellation'] !== '' ? "\n\nWeitere vom Auftraggeber geprüfte Informationen:\n" . $s['cancellation'] : ''), 'privacy_url' => $privacy, 'privacy_acknowledged_utc' => gmdate('c')];
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); global $wpdb;
        $previous_suppression = $wpdb->suppress_errors(true);
        $ok = $wpdb->insert(self::table(), ['quote_id' => $quote_id, 'submission_hash' => hash('sha256', $ticket . $session), 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + $s['ttl_hours'] * 3600), 'customer_email' => $email, 'snapshot' => $json, 'snapshot_mac' => self::mac($json), 'token_hash' => hash('sha256', $token)]);
        $wpdb->suppress_errors($previous_suppression);
        if (!$ok) { self::fail('Anfrage bereits verarbeitet oder Speicherung fehlgeschlagen. Bitte nicht mehrfach absenden.', 409); }
        $link = add_query_arg(['action' => 'mira_accept', 'id' => $quote_id, 'token' => $token], admin_url('admin-post.php')); self::mail($quote_id, $snapshot, $link, false);
        self::page('Ihre Anfrage ist gespeichert.', '<p>Vielen Dank. Ihre Angebots-ID: <strong>' . esc_html($quote_id) . '</strong></p><p>Testbetrieb: E-Mails sind gesperrt oder an die konfigurierte Testadresse umgeleitet. Eine Annahme ist nur bei vollständig bestätigten Konditionen möglich.</p><a href="' . esc_url(home_url('/')) . '">Zurück zur Website</a>');
    }
    public static function mac(string $data): string { return hash_hmac('sha256', $data, wp_salt('auth')); }
    public static function row(string $id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE quote_id=%s', $id)); }
    public static function decode($row): array {
        if (!$row || !hash_equals($row->snapshot_mac, self::mac($row->snapshot))) { self::fail('Angebot nicht verfügbar.', 404); }
        $s = json_decode($row->snapshot, true); if (!is_array($s)) { self::fail('Angebot nicht verfügbar.', 404); } return $s;
    }
    public static function summary(array $s): string {
        $p = $s['price'];
        return implode("\n\n", ['TESTANGEBOT — keine Terminbuchung', 'Angebots-ID: ' . $s['quote_id'], 'Erstellt (UTC): ' . $s['created_utc'], 'Gültig bis (UTC): ' . $s['expires_utc'], 'Anbieter: ' . ($s['provider'] ?: 'Bestätigung ausstehend'), 'Kunde: ' . $s['customer']['name'], 'E-Mail: ' . $s['customer']['email'], 'Telefon: ' . $s['customer']['phone'], 'Einsatzort: ' . $s['customer']['address'], 'Postleitzahl: ' . ($s['customer']['postcode'] ?? 'nicht angegeben'), 'Bemerkungen: ' . ($s['customer']['message'] ?? ''), 'Wunschtermin (nicht bestätigt): ' . ($s['schedule']['preferred_date'] ?? 'nicht angegeben'), 'Zeitfenster (nicht bestätigt): ' . ($s['schedule']['preferred_time'] ?? 'nicht angegeben'), 'Häufigkeit: ' . (['once'=>'Einmalig','weekly'=>'Wöchentlich','biweekly'=>'Alle 2 Wochen','monthly'=>'Monatlich'][$s['schedule']['frequency'] ?? 'once'] ?? 'nicht angegeben'), 'Berechnungsregel: Betrag pro Reinigungstermin; keine Folgetermine automatisch gebucht.', 'Leistung: ' . $p['service'], 'Leistungsumfang: ' . ($p['scope'] ?: 'Bestätigung ausstehend'), 'Stunden: ' . ($p['units'] / 4), 'Stundensatz (Berechnungsbasis): ' . Mira_Domain::money($p['hourly_cents']), 'Steuerbehandlung: ' . ['unknown' => 'Bestätigung ausstehend', 'net' => 'Stundensatz und Gebühren netto', 'gross' => 'Stundensatz und Gebühren brutto', 'exempt' => 'Steuerbefreit gemäß bestätigter Anbieterangabe'][$p['vat_mode']], 'Leistungsbetrag (Berechnungsbasis): ' . Mira_Domain::money($p['base_cents']), 'Gebühren: ' . Mira_Domain::money($p['fee_cents']) . ' — ' . ($p['fee_label'] ?: 'Bestätigung ausstehend'), 'Mindestbuchung: ' . ($p['minimum_units'] === null ? 'Keine Mindestbuchungsdauer' : ($p['minimum_units'] / 4) . ' Stunden'), 'Netto gesamt: ' . Mira_Domain::money($p['net_cents']), 'Steuersatz: ' . ($p['vat_bps'] === null ? 'Bestätigung ausstehend' : ($p['vat_bps'] / 100) . ' %'), 'Steuerbetrag: ' . Mira_Domain::money($p['tax_cents']), 'Gesamtbetrag brutto: ' . Mira_Domain::money($p['gross_cents']), 'Bedingungen: ' . ($s['terms'] ?: 'Bestätigung ausstehend'), 'Widerruf / Stornierung: ' . ($s['cancellation'] ?: 'Bestätigung ausstehend'), 'Datenschutzhinweise: ' . $s['privacy_url'], 'Datenschutzhinweis gelesen (UTC): ' . $s['privacy_acknowledged_utc'], $p['eligible'] ? 'Für Testannahme freigegeben.' : 'Unverbindliche Anfrage: Konditionen / Freigabe ausstehend. Annahme gesperrt.']);
    }
    /** Create the PDF in a restricted temporary directory; never persist a public URL. */
    private static function temporary_pdf(array $snapshot): ?string {
        if (!class_exists('Mira_Pdf')) { return null; }
        $dir = get_temp_dir();
        $path = wp_tempnam('mira-quote-pdf-', $dir);
        if (!$path || !is_string($path)) { return null; }
        $pdf = Mira_Pdf::render($snapshot);
        if (strncmp($pdf, '%PDF-', 5) !== 0 || file_put_contents($path, $pdf, LOCK_EX) !== strlen($pdf)) {
            @unlink($path); return null;
        }
        @chmod($path, 0600);
        return $path;
    }
    /** Format a saved ISO date for German customer emails without changing stored values. */
    private static function german_email_date($value): string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return '—';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('d.m.Y') : '—';
    }

    /** Escaped customer-facing mail body; staging preview never exposes a real acceptance token. */
    public static function customer_email_html(array $s, string $link = '', bool $accepted = false): string {
        $p = $s['price'] ?? [];
        $customer = $s['customer'] ?? [];
        $schedule = $s['schedule'] ?? [];
        $route = $s['route'] ?? null;
        $ready = is_array($route) && isset($route['outbound_m'], $route['return_m'], $route['roundtrip_m'])
            && isset($p['gross_cents'], $p['fee_cents']) && is_numeric($p['gross_cents'])
            && !empty($p['scope']) && !empty($schedule['confirmed']) && !empty($s['terms'])
            && !empty($s['cancellation']);
        $safe = static fn($v) => esc_html((string) ($v ?? ''));
        $line = static function (string $label, string $value) use ($safe): string {
            return '<tr><th style="text-align:left;padding:9px 12px;border-bottom:1px solid #e0eaf0;color:#496476">' . $safe($label) . '</th><td style="padding:9px 12px;border-bottom:1px solid #e0eaf0">' . $safe($value) . '</td></tr>';
        };
        $money = static fn($n) => is_numeric($n) ? Mira_Domain::money((int) $n) : 'Noch nicht bestätigt';
        $body = '<div style="font-family:Arial,sans-serif;color:#183747;max-width:660px;margin:auto;line-height:1.6">'
            . '<div style="background:#087fa3;color:white;padding:22px;border-radius:12px 12px 0 0"><strong>SERVICE MIRA</strong><h1 style="margin:8px 0;font-size:25px">' . ($accepted ? 'Verbindliche Buchungsbestätigung' : 'Ihre Angebotsanfrage') . '</h1></div>'
            . '<div style="padding:22px;border:1px solid #d5e6ed;border-radius:0 0 12px 12px">'
            . '<p>Guten Tag ' . $safe($customer['name'] ?? '') . ',</p>'
            . '<p>' . ($accepted ? 'Ihre ausdrückliche Annahme wurde mit Zeitpunkt und Angebotsstand dokumentiert. Die Buchung ist verbindlich.' : 'Vielen Dank für Ihre Anfrage. Hier finden Sie Ihre Angaben und die derzeit bekannte Preisgrundlage.') . '</p>'
            . ($accepted ? '<p style="background:#eaf7fb;padding:12px;border-radius:8px"><strong>Verbindliche, zahlungspflichtige Buchung bestätigt.</strong> Gesetzliche Widerrufs- und Verbraucherrechte bleiben unberührt. Bitte bewahren Sie die beigefügte Angebotsfassung auf.</p>' : '<p style="background:#eaf7fb;padding:12px;border-radius:8px"><strong>Unverbindliche Anfrage – keine bestätigte Buchung.</strong> Der Wunschtermin und der Gesamtpreis müssen noch bestätigt werden.</p>')
            . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
            . $line('Angebots-ID', (string) ($s['quote_id'] ?? ''))
            . $line('Leistung', (string) ($p['service'] ?? ''))
            . $line('Dauer', isset($p['units']) ? number_format(((int) $p['units']) / 4, 2, ',', '.') . ' Std.' : '—')
            . $line('Wunschtermin', self::german_email_date($schedule['preferred_date'] ?? null))
            . $line('Zeitfenster', (string) ($schedule['preferred_time'] ?? '—'))
            . $line('Leistungsumfang', !empty($p['scope']) ? (string) $p['scope'] : 'Wird vor einem verbindlichen Angebot abgestimmt')
            . $line('Reinigung netto', $money($p['base_cents'] ?? null))
            . $line('Fahrtkosten netto', $route ? $money($p['fee_cents'] ?? null) : 'Fahrstrecke noch nicht bestätigt')
            . $line('Mehrwertsteuer', $money($p['tax_cents'] ?? null))
            . $line('Gesamt brutto', $ready ? $money($p['gross_cents']) : 'Noch nicht verbindlich festgestellt')
            . $line('Hin- und Rückfahrt', $route && isset($route['outbound_m'], $route['return_m']) ? number_format(((int)$route['outbound_m'] + (int)$route['return_m']) / 1000, 2, ',', '.') . ' km' : 'Noch nicht von OpenRouteService bestätigt')
            . $line('Vertragsbedingungen', !empty($s['terms']) ? (string)$s['terms'] : 'Noch nicht vom Auftraggeber freigegeben')
            . $line('24-Stunden-Stornierung', self::cancellation_policy())
            . (self::additional_cancellation_info($s['cancellation'] ?? '') !== '' ? $line('Weitere Widerrufsinformationen', self::additional_cancellation_info($s['cancellation'])) : '')
            . '</table>';
        if (!$accepted && $link !== '') {
            $body .= '<p><a href="' . esc_url($link) . '" style="display:inline-block;background:#087fa3;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px">Anfrage geschützt ansehen</a></p>';
            $body .= '<p style="font-size:12px;color:#657e89">Persönlichen Link nicht weitergeben. Der Link allein löst keine Buchung aus.</p>';
        }
        $body .= '<p style="font-size:12px;color:#657e89">Service Mira · ' . (self::allowed() ? 'Staging: keine verbindliche Buchung.' : 'Ihre Unterlagen zum Vorgang.') . '</p></div></div>';
        return $body;
    }

    public static function mail(string $id, array $s, string $link, bool $accepted): void {
        $text = self::summary($s) . ($accepted ? "\n\nVerbindliche Buchung bestätigt; Angebotsstand und Annahmezeitpunkt wurden dokumentiert." : "\n\nAngebot geschützt prüfen:\n" . $link . "\n\nDiesen persönlichen Link nicht weitergeben.");
        $subject = 'Service Mira · ' . ($accepted ? 'Verbindliche Buchungsbestätigung' : 'Angebotsanfrage') . ' · ' . $id;
        // Staging never sends customer data. Attachment wiring is ready for a future
        // authorized mail path, but the global staging filters continue to block/reroute.
        $pdf = self::temporary_pdf($s);
        $attachments = $pdf === null ? [] : [$pdf];
        try {
            $a = wp_mail($s['customer']['email'], $subject, self::customer_email_html($s, $link, $accepted), ['Content-Type: text/html; charset=UTF-8'], $attachments);
            $b = wp_mail(self::settings()['admin_email'], $subject,
                self::summary($s) . ($accepted ? "\n\nVerbindliche Buchungsannahme im System gespeichert." : "\n\nNeue Anfrage. Details im geschützten WordPress-Adminbereich."),
                ['Content-Type: text/plain; charset=UTF-8'], $attachments);
        } finally {
            if ($pdf !== null && is_file($pdf)) { @unlink($pdf); }
        }
        $state = [
            'event' => $accepted ? 'accepted' : 'submitted',
            'mode' => self::allowed() ? (self::reroute() ? 'reroute' : 'blocked') : 'production',
            'customer_transport_accepted' => (bool) $a,
            'admin_transport_accepted' => (bool) $b,
            'pdf_generated' => $pdf !== null,
            'pdf_transmitted' => !self::allowed() && $pdf !== null && (bool) $a,
            'utc' => gmdate('c'),
        ];
        global $wpdb;
        $wpdb->update(self::table(), ['mail_state' => wp_json_encode($state)], ['quote_id' => $id]);
    }
    /** Independent server-side acceptance checks against the immutable saved snapshot.
     * Never trust a previously persisted eligible flag alone. No setting here enables
     * customer acceptance: staging permissions remain separately hard-disabled.
     */
    public static function acceptance_requirements(array $s): array {
        $p = is_array($s['price'] ?? null) ? $s['price'] : [];
        $r = is_array($s['route'] ?? null) ? $s['route'] : [];
        $date = is_array($s['schedule'] ?? null) ? $s['schedule'] : [];
        $valid_route = isset($r['outbound_m'], $r['return_m'], $r['roundtrip_m'], $r['travel_cents'], $r['net_cents'], $r['vat_cents'], $r['gross_cents'], $r['service_cents'], $r['vat_bps'])
            && is_int($r['outbound_m']) && is_int($r['return_m']) && is_int($r['roundtrip_m'])
            && $r['outbound_m'] >= 0 && $r['return_m'] >= 0
            && $r['roundtrip_m'] === $r['outbound_m'] + $r['return_m']
            && ($r['provider'] ?? '') === 'openrouteservice'
            && ($r['origin'] ?? '') === Mira_Routes::ORIGIN
            && !empty($r['destination'])
            && (int) ($r['free_m'] ?? -1) === 10000
            && (int) $r['travel_cents'] === intdiv(max(0, $r['roundtrip_m'] - 10000) * 50 + 500, 1000);
        $matching_price = $valid_route && isset($p['base_cents'], $p['fee_cents'], $p['net_cents'], $p['tax_cents'], $p['gross_cents'], $p['vat_bps'])
            && (int) ($p['hourly_cents'] ?? 0) === 2000
            && (int) $r['service_cents'] === (int) $p['base_cents']
            && (int) $p['fee_cents'] === (int) $r['travel_cents']
            && (int) $p['net_cents'] === (int) $r['net_cents']
            && (int) $p['tax_cents'] === (int) $r['vat_cents']
            && (int) $p['gross_cents'] === (int) $r['gross_cents']
            && (int) $p['vat_bps'] === (int) $r['vat_bps']
            && (int) $p['gross_cents'] === (int) $p['net_cents'] + (int) $p['tax_cents'];
        return [
            'OpenRouteService-Fahrstrecke für Hin- und Rückfahrt vollständig' => $valid_route,
            'Fahrtkosten, Netto, Steuer und Brutto stimmen überein' => $matching_price,
            'Leistungsumfang intern geprüft' => trim((string) ($p['scope'] ?? '')) !== '',
            'Wunschtermin intern geprüft' => !empty($date['confirmed']),
            'Mehrwertsteuer und ihre Anwendung auf Fahrtkosten bestätigt' => !empty($p['vat_approved']) && !empty(self::settings()['vat_approved']) && (int) ($p['vat_bps'] ?? -1) === (int) self::settings()['vat_bps'],
            'Vertragsbedingungen und Stornierung hinterlegt' => trim((string) ($s['terms'] ?? '')) !== '' && trim((string) ($s['cancellation'] ?? '')) !== '',
        ];
    }
    public static function accept(): void {
        self::guard(); self::throttle(); header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $id = isset($_GET['id']) && is_string($_GET['id']) ? wp_unslash($_GET['id']) : '';
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { self::fail('Angebot nicht verfügbar.', 404); }
        $row = self::row($id); $path = wp_parse_url(admin_url('admin-post.php'), PHP_URL_PATH); $cookie_name = 'mira_access_' . str_replace('-', '', $id);
        if (isset($_GET['token']) && is_string($_GET['token']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $token = wp_unslash($_GET['token']);
            if (!$row || !preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($row->token_hash, hash('sha256', $token)) || $row->status !== 'pending' || strtotime($row->expires_at . ' UTC') <= time()) { self::fail('Link ungültig, abgelaufen oder bereits verwendet.', 403); }
            self::cookie($cookie_name, $token, min(time() + 7200, strtotime($row->expires_at . ' UTC')), $path);
            wp_safe_redirect(add_query_arg(['action' => 'mira_accept', 'id' => $id], admin_url('admin-post.php')), 303); exit;
        }
        $token = $_COOKIE[$cookie_name] ?? '';
        if (!$row || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($row->token_hash, hash('sha256', $token)) || $row->status !== 'pending' || strtotime($row->expires_at . ' UTC') <= time()) { self::fail('Link ungültig, abgelaufen oder bereits verwendet.', 403); }
        $s = self::decode($row); $current = self::settings(); $requirements = self::acceptance_requirements($s); $eligible = !in_array(false, $requirements, true) && empty($s['test']) && !empty($s['price']['eligible']) && $current['acceptance'] && $current['reviewed'] && !self::allowed(); $nonce_action = 'mira_accept_' . $id . '_' . hash('sha256', $token);
        $consent = 'Ich habe die 24-Stunden-Stornierungsregelung und das Angebot einschließlich Leistungsumfang, Termin, Gesamtpreis inklusive Anfahrt und Mehrwertsteuer sowie die bereitgestellten Vertrags- und Widerrufsinformationen gelesen. Mir ist bekannt, dass meine ausdrückliche Annahme eine verbindliche, zahlungspflichtige Buchung auslöst. Gesetzliche Widerrufs- und Verbraucherrechte bleiben unberührt.';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!$eligible || !wp_verify_nonce(self::value('_mira_nonce'), $nonce_action) || self::value('consent') !== '1') { self::fail('Annahme nicht freigegeben oder Bestätigung fehlt.', 403); }
            $at = gmdate('Y-m-d H:i:s');
            $cancel_until = gmdate('Y-m-d H:i:s', time() + self::CANCELLATION_HOURS * HOUR_IN_SECONDS);
            $evidence = wp_json_encode(['version' => 1, 'quote_id' => $id, 'accepted_utc' => $at, 'voluntary_cancellation_deadline_utc' => $cancel_until, 'voluntary_cancellation_hours' => self::CANCELLATION_HOURS, 'snapshot_sha256' => hash('sha256', $row->snapshot), 'snapshot_mac' => $row->snapshot_mac, 'consent' => $consent, 'test' => false, 'source' => 'secure_customer_acceptance', 'remote_ip_hash' => hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), wp_salt('auth')), 'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))]);
            global $wpdb;
            // Atomic compare-and-swap: evidence, timestamp, status and token invalidation commit together.
            $changed = $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . " SET status='accepted',accepted_at=%s,evidence=%s,token_hash='' WHERE quote_id=%s AND status='pending' AND token_hash=%s AND snapshot_mac=%s AND expires_at>%s", $at, $evidence, $id, hash('sha256', $token), $row->snapshot_mac, $at));
            if ($changed !== 1) { self::fail('Annahme bereits verarbeitet oder nicht mehr möglich.', 409); }
            self::cookie($cookie_name, '', time() - 3600, $path); self::mail($id, $s, '', true);
            self::page('Buchung verbindlich bestätigt', '<p>Angebots-ID: ' . esc_html($id) . '</p><p>Zeitpunkt (UTC): ' . esc_html($at) . '</p><p>Freiwillige Stornierungsfrist bis (UTC): ' . esc_html($cancel_until) . '</p><p>Stornierung: inf@service-mira.de unter Angabe der Angebots-ID. Gesetzliche Rechte bleiben unberührt.</p><p>Die verbindliche Annahme wurde zusammen mit dem unveränderten Angebotsstand gespeichert.</p>');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { self::fail('Methode nicht erlaubt.', 405); }
        $body = self::offer_view($s, $eligible);
        if ($eligible) {
            $body .= '<div class="mira-final-consent"><h2>Verbindliche Buchung</h2><p>Mit Ihrer ausdrücklichen Bestätigung wird die Buchung zu dem angegebenen Gesamtpreis einschließlich Anfahrt und Mehrwertsteuer verbindlich und zahlungspflichtig. Gesetzliche Widerrufs- und Verbraucherrechte bleiben unberührt.</p>' .
                '<form method="post" action="' . esc_url(add_query_arg(['action' => 'mira_accept', 'id' => $id], admin_url('admin-post.php'))) . '">' .
                '<p><strong>24-Stunden-Stornierung:</strong> ' . esc_html(self::cancellation_policy()) . '</p>' .
                wp_nonce_field($nonce_action, '_mira_nonce', false, false) .
                '<label class="mira-legal-consent"><input type="checkbox" name="consent" value="1" required><span>' . esc_html($consent) . '</span></label>' .
                '<p><button type="submit">Zahlungspflichtig buchen</button></p></form></div>';
        } else {
            $body .= '<div class="mira-status-box" role="status"><strong>Annahme derzeit gesperrt.</strong><p>Preis, Leistungsumfang, Steuern, Gebühren oder Vertragsbedingungen sind noch nicht vollständig bestätigt. Dies ist keine Buchung.</p><ul>' . implode('', array_map(static fn($title, $ready) => '<li>' . esc_html($title) . ': ' . ($ready ? 'geprüft' : 'offen') . '</li>', array_keys($requirements), array_values($requirements))) . '</ul></div>';
        }
        self::page('Ihr Angebot im Überblick', $body);
    }
    /** Shared, escaped structured preview. Never contains a private acceptance token. */
    public static function offer_view(array $s, bool $booking_ready = false): string {
        $p = $s['price'];
        $schedule = $s['schedule'] ?? [];
        $customer = $s['customer'];
        $freq = ['once' => 'Einmalig', 'weekly' => 'Wöchentlich', 'biweekly' => 'Alle zwei Wochen', 'monthly' => 'Monatlich'];
        $date = $schedule['preferred_date'] ?? '';
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($d) { $date = $d->format('d.m.Y'); }
        }
        $line = static function (string $name, $value): string {
            return '<div class="mira-offer-row"><dt>' . esc_html($name) . '</dt><dd>' . esc_html((string) ($value === null || $value === '' ? 'Noch nicht bestätigt' : $value)) . '</dd></div>';
        };
        $money = static function ($v): string { return Mira_Domain::money($v); };
        $h = '<div class="mira-offer-intro"><p>' . ($booking_ready ? 'Dieses Angebot enthält den intern bestätigten Termin und den verbindlichen Gesamtpreis. Eine Buchung entsteht erst nach Ihrer ausdrücklichen, zahlungspflichtigen Annahme.' : 'Ihre Angaben wurden gespeichert. Der angefragte Termin ist noch nicht verbindlich gebucht.') . '</p></div>';
        $h .= $booking_ready ? '<div class="mira-offer-status"><span>ANGEBOT ZUR ANNAHME</span><strong>Verbindliche, zahlungspflichtige Buchung erst nach ausdrücklicher Annahme</strong><small>Der dargestellte Endpreis umfasst Reinigung, Anfahrt und Mehrwertsteuer.</small></div>' : '<div class="mira-offer-status"><span>' . (self::allowed() ? 'TESTANGEBOT' : 'ANGEBOTSANFRAGE') . '</span> <strong>Noch keine Buchung</strong> <small>Die Angaben werden vor einer verbindlichen Annahme geprüft.</small></div>';
        $h .= '<div class="mira-offer-two">';
        $h .= '<section class="mira-offer-card"><h2>Reinigung &amp; Termin</h2><dl>';
        $h .= $line('Leistung', $p['service']);
        $h .= $line('Dauer', number_format($p['units'] / 4, ($p['units'] % 4 === 0 ? 0 : ($p['units'] % 2 === 0 ? 1 : 2)), ',', '.') . ' Stunden');
        $h .= $line('Wunschtermin', $date);
        $h .= $line('Zeitfenster', $schedule['preferred_time'] ?? '');
        $h .= $line('Häufigkeit', $freq[$schedule['frequency'] ?? 'once'] ?? 'Nicht angegeben');
        $h .= $line('Leistungsumfang', $p['scope'] ?: 'Bestätigung ausstehend');
        $h .= '</dl></section>';
        $h .= '<section class="mira-offer-card mira-offer-cost"><h2>Preisübersicht</h2><p class="mira-offer-rate">Berechnungsbasis pro Reinigungstermin</p><p class="mira-offer-amount">' . esc_html($money($p['base_cents'])) . '</p><dl>';
        $h .= $line('Stundensatz', $money($p['hourly_cents']));
        $h .= $line('Weitere Gebühren', $money($p['fee_cents']));
        $h .= $line('Steuer', $money($p['tax_cents']));
        $h .= $line('Endbetrag', $money($p['gross_cents']));
        $h .= '</dl><p class="mira-offer-warning">' . ($booking_ready ? 'Dies ist der verbindliche Gesamtpreis des geprüften Angebots. Eine Buchung erfolgt erst durch Ihre ausdrückliche Bestätigung.' : 'Dies ist noch kein verbindlich bestätigter Endpreis. Vor einer Annahme müssen Leistungsumfang, Steuern, Gebühren und gesetzlich erforderliche Informationen geklärt sein.') . '</p></section></div>';
        $h .= '<section class="mira-offer-card"><h2>Ihre Angaben</h2><dl>';
        $h .= $line('Angebots-ID', $s['quote_id']);
        $h .= $line('Name', $customer['name']);
        $h .= $line('E-Mail', $customer['email']);
        $h .= $line('Telefon', $customer['phone'] ?: 'Nicht angegeben');
        $h .= $line('Postleitzahl', $customer['postcode'] ?? '');
        $h .= $line('Einsatzort', $customer['address']);
        $h .= $line('Bemerkungen', $customer['message'] ?: 'Keine');
        $h .= '</dl></section>';
        if (!empty($s['terms']) || !empty($s['cancellation'])) {
            $h .= '<section class="mira-offer-card"><h2>Bedingungen &amp; Stornierung</h2>';
            if (!empty($s['terms'])) { $h .= '<h3>Vertragsbedingungen</h3><p>' . nl2br(esc_html($s['terms'])) . '</p>'; }
            if (self::additional_cancellation_info($s['cancellation'] ?? '') !== '') { $h .= '<h3>Weitere Widerrufsinformationen</h3><p>' . nl2br(esc_html(self::additional_cancellation_info($s['cancellation']))) . '</p>'; }
            $h .= '</section>';
        }
        $h .= '<section class="mira-offer-card"><h2>24-Stunden-Stornierung</h2><p>' . esc_html(self::cancellation_policy()) . '</p><p class="mira-subtle">Die Frist beginnt mit dem dokumentierten Zeitpunkt der ausdrücklichen Annahme, nicht mit der Anfrage.</p></section>';
        $h .= '<p class="mira-subtle">Gesetzliche Widerrufs- und Verbraucherrechte bleiben unberührt.</p>';
        return $h;
    }
    public static function page(string $title, string $body): void {
        status_header(200); header('Content-Type: text/html; charset=UTF-8');
        $config = self::settings();
        $imprint_url = $config['imprint_page'] ? get_permalink($config['imprint_page']) : home_url('/?page_id=25');
        $privacy_url = $config['privacy_page'] ? get_permalink($config['privacy_page']) : (get_privacy_policy_url() ?: home_url('/?page_id=319'));
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>' . esc_html($title) . ' · Service Mira</title><style>:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#17394a;background:#f2f8fa}*{box-sizing:border-box}body{margin:0;background:linear-gradient(125deg,#e9f9fc 0%,#f7fafb 55%,#e5f2f5 100%);min-height:100vh;color:#17394a;font-size:15px;line-height:1.6}main{width:min(1080px,calc(100% - 34px));margin:clamp(16px,4vw,54px) auto;padding:clamp(23px,4vw,55px);background:white;border-radius:26px;box-shadow:0 22px 85px rgba(17,76,99,.08)}h1,h2,h3{line-height:1.18;letter-spacing:-.03em}h1{font-size:clamp(2rem,4vw,3.25rem);margin:0 0 22px;font-weight:800}h2{font-size:1.32rem;margin:0 0 18px}h3{font-size:1.05rem;margin-top:20px}a{color:#0877a2}a:hover{text-decoration-thickness:2px}p{margin:0 0 15px}pre{white-space:pre-wrap;overflow-wrap:anywhere}button{font:inherit;font-weight:700;background:linear-gradient(110deg,#096ca9,#039cbc);color:white;border:0;border-radius:11px;padding:14px 20px;cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #4bd1d7;outline-offset:3px}.mira-overline{font-size:11px;letter-spacing:.15em;color:#1287a5;font-weight:800;margin-bottom:16px}.mira-eyebrow-public{font-size:11px;font-weight:800;letter-spacing:.14em;color:#0d8ab2}.mira-offer-intro{margin:0 0 20px;color:#667f8b}.mira-offer-status{background:#eaf8fc;border:1px solid #d4edf5;border-radius:16px;padding:17px 23px;display:flex;gap:13px;align-items:center;margin:24px 0 22px;flex-wrap:wrap}.mira-offer-status span{color:#098eac;font-size:11px;font-weight:800;letter-spacing:.1em}.mira-offer-status strong{font-size:1rem;display:inline-block}.mira-offer-status span{display:inline-block}.mira-offer-status small{width:100%;color:#5b7d8c}.mira-offer-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.86fr);gap:18px}.mira-offer-card{border:1px solid #e0edf1;border-radius:17px;padding:clamp(16px,3vw,27px);margin-bottom:18px;background:#fff;min-width:0}.mira-offer-card dl{margin:0}.mira-offer-row{display:grid;grid-template-columns:36% minmax(0,1fr);gap:12px;padding:11px 0;border-bottom:1px solid #eff4f6}.mira-offer-row:last-child{border:0}.mira-offer-row dt{color:#6c8793;font-size:13px;font-weight:650}.mira-offer-row dd{margin:0;font-weight:650;overflow-wrap:anywhere}.mira-offer-cost{background:#f4fbfe}.mira-offer-rate{font-size:12px;color:#567a8b;font-weight:650}.mira-offer-amount{font-size:clamp(2rem,4vw,3rem);font-weight:850;color:#0879b2;letter-spacing:-.05em;line-height:1.1;margin:5px 0 18px}.mira-offer-warning{color:#855e19;background:#fff7e8;border-radius:9px;padding:12px 14px;font-size:12px;margin:17px 0 0}.mira-status-box{border:1px solid #f3dbb0;background:#fff9eb;border-radius:15px;padding:19px 22px;margin-top:10px}.mira-status-box strong{color:#81520b}.mira-status-box p{margin:8px 0 0}.mira-final-consent{border:1px solid #bde5dc;background:#f0fbf7;border-radius:15px;padding:20px}.mira-legal-consent{display:flex;gap:12px;align-items:flex-start}.mira-legal-consent input{width:20px;height:20px;flex:none}.mira-footer{border-top:1px solid #dcecf0;margin-top:32px;padding-top:22px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:9px;font-size:13px;color:#7892a0}.mira-footer a{color:#0877a2}@media(max-width:760px){main{width:calc(100% - 20px);padding:23px 16px;margin:10px auto;border-radius:18px}.mira-offer-two{grid-template-columns:1fr;gap:0}.mira-offer-row{grid-template-columns:37% minmax(0,1fr);gap:9px}.mira-offer-card{padding:19px 17px}.mira-offer-status{align-items:flex-start}h1{margin-bottom:14px}}</style></head><body><main><div class="mira-overline">SERVICE MIRA · STAGING</div><h1>' . esc_html($title) . '</h1>' . $body . '<footer class="mira-footer"><span><a href="' . esc_url($imprint_url) . '">Impressum</a> · <a href="' . esc_url($privacy_url) . '">Datenschutz</a></span><span>Designed by <a href="https://pointnova.de">Pointnova</a></span></footer></main></body></html>'; exit;
    }
}
