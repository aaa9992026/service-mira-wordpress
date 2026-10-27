<?php
defined('ABSPATH') || exit;
final class Mira_Admin {
    public static function boot(): void {
        add_action('admin_menu', function () {
            add_menu_page('Mira Angebote', 'Mira Angebote', 'manage_options', 'mira-quotes', [self::class, 'listing'], 'dashicons-clipboard', 58);
            add_submenu_page('mira-quotes', 'E-Mail-Test', 'E-Mail-Test', 'manage_options', 'mira-mail-test', [self::class, 'mail_test']);
            add_submenu_page('mira-quotes', 'Fahrstrecke testen', 'Fahrstrecke testen', 'manage_options', 'mira-route-check', [self::class, 'route_check']);
            add_submenu_page('mira-quotes', 'Konfiguration', 'Konfiguration', 'manage_options', 'mira-settings', [self::class, 'settings']);
        });
        foreach (['mira_save' => 'save', 'mira_export' => 'export', 'mira_test_link' => 'test_link', 'mira_delete' => 'delete', 'mira_diagnostic_mail' => 'diagnostic_mail', 'mira_diagnostic_pdf_mail' => 'diagnostic_pdf_mail', 'mira_pdf_preview' => 'pdf_preview', 'mira_acceptance_rehearsal' => 'acceptance_rehearsal', 'mira_rehearsal_link' => 'rehearsal_link', 'mira_full_journey' => 'full_journey', 'mira_travel_scenario' => 'travel_scenario', 'mira_route_probe' => 'route_probe', 'mira_ors_key_save' => 'ors_key_save', 'mira_stage_review' => 'stage_review', 'mira_stage_terms' => 'stage_terms', 'mira_refresh_quote_route' => 'refresh_quote_route', 'mira_approve_live_quote' => 'approve_live_quote'] as $action => $method) { add_action('admin_post_' . $action, [self::class, $method]); }
        add_action('admin_notices', function () { if (current_user_can('manage_options') && !Mira_Quotes::allowed()) { echo '<div class="notice notice-error"><p>Service Mira: Staging-Schutz aktiv. Angebotsfunktionen sind außerhalb /yamato/ und lokalen Testdomains gesperrt.</p></div>'; } });
    }
    /** Explicit admin-only route check; no quote writes, emails or bookings. */
    public static function route_check(): void {
        self::guard();
        echo '<div class="wrap"><h1>Service Mira · Fahrstrecke prüfen (Staging)</h1>';
        echo '<section style="background:#fff; padding:18px; margin:15px 0; border:1px solid #ccd0d4; max-width:760px"><h2>OpenRouteService API-Schlüssel (nur Administratoren)</h2>';
        echo '<p>Status: <strong>' . (Mira_Routes::configured() ? 'Konfiguriert' : 'Nicht konfiguriert') . '</strong>. Schlüssel wird niemals erneut angezeigt. Hosting-Konstante MIRA_ORS_API_KEY hat Vorrang.</p>';
        echo '<p>Speicherung als nicht automatisch geladene WordPress-Option. Sie bleibt in Datenbank-Backups enthalten; für strengere Geheimhaltung stattdessen wp-config.php benutzen. Niemals API-Schlüssel in Screenshots oder Nachrichten zeigen.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_ors_key_save">';
        wp_nonce_field('mira_ors_key_save');
        echo '<p><label for="mira-ors-secret">API-Schlüssel eingeben oder ersetzen</label><br><input autocomplete="new-password" type="password" id="mira-ors-secret" name="ors_secret" value="" style="width:min(100%,520px)" placeholder="API-Schlüssel (wird nicht angezeigt)"></p>';
        echo '<p><label><input type="checkbox" name="delete_ors_secret" value="1"> Gespeicherten WordPress-API-Schlüssel löschen</label></p>';
        submit_button('API-Schlüssel sicher speichern', 'secondary', 'submit', false); echo '</form></section>';
        echo '<p>Start: <strong>' . esc_html(Mira_Routes::ORIGIN) . '</strong>. Zwei echte Fahrtrouten werden abgefragt (Hin- und Rückfahrt). 10 km insgesamt frei, danach 0,50 € netto/km. Nur Test, keine Freigabe oder Buchung.</p>';
        if (!Mira_Routes::configured()) echo '<div class="notice notice-warning"><p>OpenRouteService API-Schlüssel fehlt. Hinterlegen Sie den Schlüssel unten im geschützten Admin-Formular oder über wp-config.php.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_route_probe">';
        wp_nonce_field('mira_route_probe');
        echo '<p><label>Vollständige Test-Zieladresse <input name="destination" type="text" maxlength="300" size="75" required autocomplete="off"></label></p>';
        submit_button('Fahrstrecke berechnen (externer OpenRouteService API-Aufruf)'); echo '</form></div>';
    }
    public static function route_probe(): void {
        self::guard('mira_route_probe');
        $destination = sanitize_text_field(Mira_Quotes::value('destination'));
        try { $r = Mira_Routes::quote($destination, 4000); }
        catch (Throwable $e) { wp_die(esc_html($e->getMessage()), 'Routing-Test fehlgeschlagen', ['response'=>400]); }
        echo '<div class="wrap"><h1>Fahrstrecke – Testberechnung</h1><p>Nur Staging. Keine Kundendaten gespeichert oder E-Mails versendet.</p><table class="widefat striped" style="max-width:780px"><tbody>';
        $rows = ['Start'=>$r['origin'],'Ziel'=>$r['destination'],'Hinfahrt'=>number_format($r['outbound_m']/1000,3,',','.').' km','Rückfahrt'=>number_format($r['return_m']/1000,3,',','.').' km','Gesamtstrecke'=>number_format($r['roundtrip_m']/1000,3,',','.').' km','Freikilometer'=>'10 km','Fahrtkosten netto'=>Mira_Domain::money($r['travel_cents']),'Beispiel: 2 Std. Reinigung netto'=>Mira_Domain::money($r['service_cents']),'Beispiel MwSt. (vorläufig 19%)'=>Mira_Domain::money($r['vat_cents']),'Beispiel brutto'=>Mira_Domain::money($r['gross_cents'])];
        foreach ($rows as $label=>$value) echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html($value).'</td></tr>';
        echo '</tbody></table><p>Vorläufiges Szenario, kein rechtsverbindliches Angebot. <a href="'.esc_url(admin_url('admin.php?page=mira-route-check')).'">Weiterer Test</a></p></div>';
    }
    public static function ors_key_save(): void {
        self::guard('mira_ors_key_save');
        if (!empty($_POST['delete_ors_secret'])) {
            delete_option('mira_ors_server_api_key');
        } else {
            $secret = isset($_POST['ors_secret']) && is_string($_POST['ors_secret'])
                ? trim(wp_unslash($_POST['ors_secret'])) : '';
            if (!preg_match('/^[A-Za-z0-9._~+\/=-]{12,2048}$/D', $secret)) {
                Mira_Quotes::fail('Ungültiges API-Schlüssel-Format. Nichts wurde gespeichert.');
            }
            // Keep API key out of HTML, URLs, email, and autoloaded options.
            update_option('mira_ors_server_api_key', $secret, false);
        }
        wp_safe_redirect(admin_url('admin.php?page=mira-route-check')); exit;
    }
    public static function guard(?string $nonce = null): void {
        Mira_Quotes::guard(); if (!current_user_can('manage_options')) { Mira_Quotes::fail('Keine Berechtigung.', 403); }
        if ($nonce !== null) { if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { Mira_Quotes::fail('POST erforderlich.', 405); } check_admin_referer($nonce); }
    }
    private static function input(string $key, string $label, $value, string $type = 'text'): void {
        echo '<p><label for="mira-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br><input style="width:min(100%,650px)" id="mira-' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '"></p>';
    }
    private static function area(string $key, string $label, string $value, int $rows = 6): void {
        echo '<p><label for="mira-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br><textarea style="width:min(100%,950px);font-family:monospace" id="mira-' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="' . $rows . '">' . esc_textarea($value) . '</textarea></p>';
    }
    public static function settings(): void {
        self::guard(); $s = Mira_Quotes::settings();
        echo '<div class="wrap"><h1>Mira · Konfiguration</h1><p><strong>Nur Staging. Mailmodus: ' . (Mira_Quotes::reroute() ? 'Umleitung auf konfigurierte Testadresse' : 'Versand gesperrt') . '.</strong> Alle Geldwerte in Cent, Steuer in Basispunkten (100 = 1 %), Mindestzeit in Viertelstunden. Leeres Feld = unbestätigt; 0 = ausdrücklich bestätigt, keine Gebühren / 0 %.</p><p>Diese Software speichert Annahmeevidenz. Rechtliche Wirksamkeit wird nicht zugesichert. Anbieter, Preise, Steuern, Umfang, Bedingungen und Widerruf müssen durch den Auftraggeber und seine Rechtsberatung geprüft werden.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_save">';
        wp_nonce_field('mira_save');
        self::input('admin_email', 'Benachrichtigung an (im Testmodus gesperrt / umgeleitet)', $s['admin_email'], 'email');
        self::input('privacy_page', 'ID der veröffentlichten Datenschutzseite (0 = WordPress-Einstellung)', $s['privacy_page'], 'number');
        self::input('imprint_page', 'ID der veröffentlichten Impressumseite (0 = beobachtete ID 25, im Klon prüfen)', $s['imprint_page'], 'number');
        echo '<p><label for="mira-vat"><strong>Steuerbehandlung von Stundensatz UND pauschalen Gebühren</strong></label><br><select id="mira-vat" name="vat_mode">';
        foreach (['unknown' => 'Bestätigung ausstehend', 'net' => 'Netto zzgl. bestätigter Steuer', 'gross' => 'Brutto inkl. bestätigter Steuer', 'exempt' => 'Steuerbefreit (Begründung in Bedingungen)'] as $k => $v) { echo '<option value="' . esc_attr($k) . '" ' . selected($s['vat_mode'], $k, false) . '>' . esc_html($v) . '</option>'; } echo '</select></p>';
        self::input('vat_bps', 'Vorläufiger Steuersatz in Basispunkten (bis Kundenbestätigung)', $s['vat_bps'], 'number');
        echo '<p><label><input type="checkbox" name="vat_approved" value="1" ' . checked(!empty($s['vat_approved']), true, false) . '> Steuersatz UND Steuerbehandlung der Fahrtkosten wurden ausdrücklich vom Auftraggeber bestätigt.</label></p>';
        self::input('fee_cents', 'Bestätigte Gesamtgebühr je Angebot in Cent (leer = unbekannt)', $s['fee_cents'], 'number');
        self::input('fee_label', 'Gebührenregel: inklusive Anfahrt / Kilometer / Zuschlägen; nur vollständig erfasste Fälle freigeben', $s['fee_label']);
        self::input('minimum_units', 'Mindestbuchung (nicht erforderlich – keine Mindestdauer)', $s['minimum_units'], 'number');
        self::input('ttl_hours', 'Gültigkeit neuer Angebotslinks in Stunden (1–720; technische Ablaufzeit)', $s['ttl_hours'], 'number');
        self::area('provider', 'Vollständiger Anbieter / Anschrift / Kontakt', $s['provider'], 4);
        self::area('terms', 'Geprüfte Bedingungen einschließlich Leistungs-/Zahlungs- und Steuerregelung', $s['terms']);
        echo '<p><strong>Vom Auftraggeber bestätigte freiwillige Stornierung:</strong> ' . esc_html(Mira_Quotes::cancellation_policy()) . '</p>';
        self::area('cancellation', 'Zusätzliche geprüfte gesetzliche Widerrufs-/Verbraucherinformationen und ggf. Musterformular', $s['cancellation']);
        self::area('services', 'Leistungen als JSON (cents: null für variabel; confirmed: true erst nach Prüfung; scope: genauer Leistungsumfang)', wp_json_encode($s['services'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 25);
        echo '<p><label><input type="checkbox" name="reviewed" value="1" ' . checked($s['reviewed'], true, false) . '> Auftraggeber / Rechtsberatung haben Preis-, Steuer- und Rechtstexte für diese Testfälle geprüft.</label></p><p><label><input type="checkbox" name="acceptance" value="1" ' . checked($s['acceptance'], true, false) . '> Testannahme für vollständige NEUE Angebote freigeben. Ausschalten sperrt auch offene Angebote.</label></p><p>Bestehende Snapshots bleiben unverändert. Neue Konditionen erfordern eine neue Anfrage. Variable Anfahrten/Preise benötigen ein individuelles, vollständig kalkuliertes Angebot; der Rechner erfindet dafür keinen Betrag.</p>';
        submit_button('Konfiguration speichern'); echo '</form></div>';
    }
    private static function integer(string $key, int $min, int $max, bool $nullable = false): ?int {
        $raw = Mira_Quotes::value($key); if ($raw === '' && $nullable) { return null; }
        if (!ctype_digit($raw) || strlen($raw) > 9 || (int) $raw < $min || (int) $raw > $max) { Mira_Quotes::fail('Ungültiger ganzzahliger Wert: ' . $key); } return (int) $raw;
    }
    public static function save(): void {
        self::guard('mira_save'); $s = Mira_Domain::defaults(); $email = Mira_Quotes::value('admin_email');
        if (!is_email($email) || strlen($email) > 254) { Mira_Quotes::fail('Ungültige Admin-E-Mail-Adresse.'); } $s['admin_email'] = sanitize_email($email);
        $s['privacy_page'] = self::integer('privacy_page', 0, 999999999);
        if ($s['privacy_page'] && (get_post_status($s['privacy_page']) !== 'publish' || get_post_type($s['privacy_page']) !== 'page')) { Mira_Quotes::fail('Datenschutzseite muss eine veröffentlichte Seite sein.'); }
        $s['imprint_page'] = self::integer('imprint_page', 0, 999999999);
        if ($s['imprint_page'] && (get_post_status($s['imprint_page']) !== 'publish' || get_post_type($s['imprint_page']) !== 'page')) { Mira_Quotes::fail('Impressum muss eine veröffentlichte Seite sein.'); }
        $s['vat_mode'] = 'net';
        $s['vat_bps'] = 1900; $s['vat_approved'] = Mira_Quotes::value('vat_approved') === '1'; $s['fee_cents'] = self::integer('fee_cents', 0, 10000000, true); $s['minimum_units'] = null; $s['ttl_hours'] = self::integer('ttl_hours', 1, 720);
        foreach (['provider', 'terms', 'cancellation', 'fee_label'] as $key) { $raw = Mira_Quotes::value($key); if (strlen($raw) > 30000) { Mira_Quotes::fail('Text zu lang: ' . $key); } $s[$key] = sanitize_textarea_field($raw); }
        $raw = Mira_Quotes::value('services'); if (strlen($raw) > 100000) { Mira_Quotes::fail('Leistungskatalog zu groß.'); }
        $catalog = json_decode($raw, true);
        if (!is_array($catalog) || !$catalog || count($catalog) > 50 || array_is_list($catalog)) { Mira_Quotes::fail('Leistungen müssen ein gültiges JSON-Objekt sein (1–50).'); }
        $services = [];
        foreach ($catalog as $id => $v) {
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $id) || !is_array($v) || !isset($v['name'], $v['scope'], $v['confirmed']) || !array_key_exists('cents', $v) || !is_string($v['name']) || !is_string($v['scope']) || !is_bool($v['confirmed']) || ($v['cents'] !== null && (!is_int($v['cents']) || $v['cents'] < 0 || $v['cents'] > 1000000)) || strlen($v['name']) > 120 || strlen($v['scope']) > 5000 || trim($v['name']) === '') { Mira_Quotes::fail('Ungültiger Leistungseintrag. Erforderlich: name, scope, confirmed (boolean), cents (integer/null).'); }
            $services[$id] = ['name' => sanitize_text_field($v['name']), 'scope' => sanitize_textarea_field($v['scope']), 'confirmed' => $v['confirmed'], 'cents' => $v['cents']];
        }
        foreach ($services as &$v) { $v['cents'] = 2000; $v['confirmed'] = true; } unset($v); $s['services'] = $services;
        $live_gate = !Mira_Quotes::allowed()
            && defined('MIRA_PRODUCTION_BOOKINGS_ENABLED') && MIRA_PRODUCTION_BOOKINGS_ENABLED === true
            && defined('MIRA_PRODUCTION_MAIL_APPROVED') && MIRA_PRODUCTION_MAIL_APPROVED === true;
        $s['reviewed'] = $live_gate && Mira_Quotes::value('reviewed') === '1';
        $s['acceptance'] = $live_gate && Mira_Quotes::value('acceptance') === '1';
        if ($s['acceptance'] && (!$s['vat_approved'] || !$s['reviewed'] || !$s['provider'] || !$s['terms'] || !$s['cancellation'])) { Mira_Quotes::fail('Vor Testannahme müssen Anbieter, Rechtstexte und Prüfung vollständig sein.'); }
        update_option('mira_settings', $s, false); wp_safe_redirect(admin_url('admin.php?page=mira-settings&saved=1')); exit;
    }
    /** Staging-only, one-recipient transport probe. Never sends a quotation or acceptance link. */
    public static function mail_test(): void {
        self::guard();
        $current = wp_get_current_user();
        $recipient = (string) $current->user_email;
        $result = get_transient('mira_mail_test_' . get_current_user_id());
        if ($result !== false) { delete_transient('mira_mail_test_' . get_current_user_id()); }
        echo '<div class="wrap"><h1>Service Mira · E-Mail-Test</h1>';
        echo '<p><strong>Nur Staging.</strong> Ein Test wird ausschließlich an die E-Mail-Adresse des angemeldeten WordPress-Administrators gesendet. Keine Kundendaten, keine Angebotsdetails und keine Annahmelinks.</p>';
        echo '<p>Normale Angebots-E-Mails bleiben ' . (Mira_Quotes::reroute() ? '<strong>auf die konfigurierte Testadresse umgeleitet</strong>' : '<strong>gesperrt</strong>') . '. Dieser Test ändert weder die Mailkonfiguration noch Preise oder Freigaben.</p>';
        if ($result !== false) {
            echo '<div class="notice notice-' . ($result === 'ok' ? 'success' : 'warning') . '"><p>' .
               ($result === 'ok' ? 'Der E-Mail-Transport hat die Testnachricht angenommen. Prüfen Sie Ihren Posteingang (auch Spam). Dies ist KEINE Zustellgarantie.' : 'Der E-Mail-Transport hat die Testnachricht nicht angenommen. SMTP-/Hosting-Konfiguration prüfen.') .
               '</p></div>';
        }
        echo '<p><strong>Empfänger:</strong> ' . esc_html($recipient) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_diagnostic_mail">';
        wp_nonce_field('mira_diagnostic_mail'); submit_button('Eine Test-E-Mail an mein WordPress-Admin-Konto senden', 'primary');
        echo '</form><hr><h2>PDF-Anhang testen (nur Staging)</h2>';
        echo '<p>Ein manuell ausgelöster Test sendet eine <strong>synthetische, unverbindliche</strong> PDF-Vorschau ausschließlich an <code>staging-test@example.invalid</code>. Keine Kundendaten und keine Annahmelinks. Normale Angebotsmails bleiben blockiert.</p>';
        $pdf_result = get_transient('mira_pdf_mail_test_' . get_current_user_id());
        if ($pdf_result !== false) { delete_transient('mira_pdf_mail_test_' . get_current_user_id()); echo '<div class="notice notice-' . ($pdf_result === 'ok' ? 'success' : 'warning') . '"><p>' . ($pdf_result === 'ok' ? 'Testmail mit PDF wurde vom Transport angenommen (Zustellung noch im Posteingang prüfen).' : 'PDF-Testmail wurde nicht angenommen. SMTP-/WordPress-Logs prüfen.') . '</p></div>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_diagnostic_pdf_mail">';
        wp_nonce_field('mira_diagnostic_pdf_mail'); submit_button('Synthetisches PDF an autorisierte Testadresse senden', 'secondary');
        echo '</form><hr><h2>Geschützte Annahme – Simulation (nur Staging)</h2>';
        echo '<p>Prüft einen synthetischen, nicht bindenden Nachweis mit Zeitstempel und Angebots-Hash. Versendet eine simulierte Bestätigung ausschließlich an die autorisierte Testadresse. Ändert keine Kundenangebote und erzeugt keine Buchung.</p>';
        $rehearsal = get_transient('mira_acceptance_rehearsal_' . get_current_user_id());
        if (is_array($rehearsal)) {
            echo '<div class="notice notice-info"><p>Letzte Simulation (keine Buchung): <code>' . esc_html($rehearsal['recorded_utc'] ?? '') . ' UTC</code>; E-Mail-Transport: <strong>' . (!empty($rehearsal['confirmation_transport_accepted']) ? 'angenommen (Empfang prüfen)' : 'nicht angenommen') . '</strong></p>';
            echo '<p>Snapshot-SHA256: <code>' . esc_html($rehearsal['quote_sha256'] ?? '') . '</code></p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_acceptance_rehearsal">';
        wp_nonce_field('mira_acceptance_rehearsal'); submit_button('Synthetische Annahme und Bestätigungs-E-Mail testen', 'secondary');
        echo '</form><hr><h2>Einmal-Link: geschützter End-to-End-Test</h2><p>Erstellt einen 15 Minuten gültigen Testlink. Öffnen, synthetische Konditionen prüfen, ausdrückliche Testbestätigung abgeben und die E-Mail kontrollieren. Keine Kundendaten, keine verbindliche Buchung.</p>';
        $last = get_transient('mira_rehearsal_link_url_' . get_current_user_id());
        if (is_string($last) && $last !== '') { echo '<p><a class="button button-primary" href="' . esc_url($last) . '" target="_blank" rel="noopener noreferrer">Geschützten Testlink öffnen</a> (nur 15 Minuten gültig)</p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_rehearsal_link">';
        wp_nonce_field('mira_rehearsal_link'); submit_button('Einmaligen Testlink generieren', 'secondary');
        echo '</form><hr><h2>Kompletter Staging-E-Mail-Test: PDF + Einmal-Link</h2>';
        echo '<p>Versendet eine synthetische PDF und einen auf 15 Minuten begrenzten Testlink ausschließlich an die autorisierte Testadresse. Nach der expliziten Testbestätigung folgt eine zweite Test-E-Mail. Kein Kundenangebot wird geändert; keine Buchung.</p>';
        $journey = get_transient('mira_full_journey_result_' . get_current_user_id());
        if ($journey !== false) { delete_transient('mira_full_journey_result_' . get_current_user_id()); echo '<div class="notice notice-' . ($journey === 'ok' ? 'success' : 'warning') . '"><p>' . ($journey === 'ok' ? 'PDF und Testlink wurden vom E-Mail-Transport angenommen. Posteingang prüfen und Testlink öffnen.' : 'Versand fehlgeschlagen. Bitte SMTP-Logs prüfen.') . '</p></div>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_full_journey">';
        wp_nonce_field('mira_full_journey'); submit_button('PDF + sicheren Testlink per E-Mail senden', 'primary');
        echo '</form><p>Zum Nachweis des Kunden-Workflows können die Angebotsanfrage, Datenbank-Speicherung, geschützte Angebotsansicht und die gesperrte Annahme demonstriert werden. Automatischer Versand an Kunden bleibt in Staging absichtlich gesperrt.</p></div>';
    }
    public static function diagnostic_mail(): void {
        self::guard('mira_diagnostic_mail');
        $current = wp_get_current_user();
        $accepted = Mira_Quotes::diagnostic_mail((string) $current->user_email);
        set_transient('mira_mail_test_' . get_current_user_id(), $accepted ? 'ok' : 'failed', 90);
        wp_safe_redirect(admin_url('admin.php?page=mira-mail-test')); exit;
    }
    public static function diagnostic_pdf_mail(): void {
        self::guard('mira_diagnostic_pdf_mail');
        // Rate-limit admin manual test sends, independent of customer submissions.
        $key = 'mira_pdf_test_limit_' . get_current_user_id();
        if (get_transient($key)) { Mira_Quotes::fail('Bitte mindestens 60 Sekunden bis zum nächsten PDF-Test warten.', 429); }
        set_transient($key, 1, 60);
        $accepted = Mira_Quotes::diagnostic_pdf_mail();
        set_transient('mira_pdf_mail_test_' . get_current_user_id(), $accepted ? 'ok' : 'failed', 120);
        wp_safe_redirect(admin_url('admin.php?page=mira-mail-test')); exit;
    }
    public static function full_journey(): void {
        self::guard('mira_full_journey');
        $key = 'mira_full_journey_limit_' . get_current_user_id();
        if (get_transient($key)) { Mira_Quotes::fail('Bitte 60 Sekunden zwischen Tests warten.', 429); }
        set_transient($key, 1, 60);
        $ok = Mira_Quotes::diagnostic_full_journey();
        set_transient('mira_full_journey_result_' . get_current_user_id(), $ok ? 'ok' : 'failed', 120);
        wp_safe_redirect(admin_url('admin.php?page=mira-mail-test')); exit;
    }
    public static function rehearsal_link(): void {
        self::guard('mira_rehearsal_link');
        $url = Mira_Quotes::issue_rehearsal_link();
        set_transient('mira_rehearsal_link_url_' . get_current_user_id(), $url, 15 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=mira-mail-test')); exit;
    }
    public static function acceptance_rehearsal(): void {
        self::guard('mira_acceptance_rehearsal');
        $key = 'mira_acceptance_rehearsal_limit_' . get_current_user_id();
        if (get_transient($key)) { Mira_Quotes::fail('Bitte 60 Sekunden zwischen Tests warten.', 429); }
        set_transient($key, 1, 60);
        Mira_Quotes::diagnostic_acceptance_rehearsal();
        wp_safe_redirect(admin_url('admin.php?page=mira-mail-test&acceptance_test=1')); exit;
    }
    public static function listing(): void {
        self::guard();
        if (isset($_GET['quote']) && is_string($_GET['quote'])) { self::detail(sanitize_text_field(wp_unslash($_GET['quote']))); return; }
        global $wpdb; $status = isset($_GET['status']) && is_string($_GET['status']) ? sanitize_key($_GET['status']) : ''; if (!in_array($status, ['pending', 'accepted'], true)) { $status = ''; }
        $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'created_at'; if (!in_array($sort, ['quote_id', 'status', 'created_at', 'accepted_at', 'expires_at'], true)) { $sort = 'created_at'; }
        $order = ($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC'; $page = max(1, absint($_GET['paged'] ?? 1));
        $where = $status ? $wpdb->prepare(' WHERE status=%s', $status) : '';
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Mira_Quotes::table() . $where);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Mira_Quotes::table() . $where . " ORDER BY $sort $order,id DESC LIMIT 25 OFFSET %d", ($page - 1) * 25));
        echo '<div class="wrap"><h1>Mira · Angebote</h1><p>Testbetrieb · Status: pending = offen, accepted = angenommen. Abgelaufene offene Angebote bleiben als pending archiviert, die Annahme ist gesperrt.</p><p><a href="' . esc_url(admin_url('admin.php?page=mira-quotes')) . '">Alle</a> · <a href="' . esc_url(admin_url('admin.php?page=mira-quotes&status=pending')) . '">Offen</a> · <a href="' . esc_url(admin_url('admin.php?page=mira-quotes&status=accepted')) . '">Angenommen</a></p><table class="widefat striped"><thead><tr>';
        foreach (['quote_id' => 'Angebots-ID', 'status' => 'Status', 'created_at' => 'Erstellt UTC', 'expires_at' => 'Ablauf UTC', 'accepted_at' => 'Annahme UTC'] as $k => $label) { echo '<th><a href="' . esc_url(add_query_arg(['page' => 'mira-quotes', 'status' => $status, 'sort' => $k, 'order' => $order === 'ASC' ? 'desc' : 'asc'], admin_url('admin.php'))) . '">' . esc_html($label) . '</a></th>'; }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) { echo '<tr><td><a href="' . esc_url(add_query_arg(['page' => 'mira-quotes', 'quote' => $r->quote_id], admin_url('admin.php'))) . '">' . esc_html($r->quote_id) . '</a></td><td>' . esc_html($r->status) . '</td><td>' . esc_html($r->created_at) . '</td><td>' . esc_html($r->expires_at) . '</td><td>' . esc_html($r->accepted_at ?? '—') . '</td></tr>'; }
        if (!$rows) { echo '<tr><td colspan="5">Noch keine Angebote.</td></tr>'; } echo '</tbody></table><p>' . esc_html((string) $total) . ' Angebote · Seite ' . esc_html((string) $page) . '</p>';
        if ($page > 1) { echo '<a class="button" href="' . esc_url(add_query_arg('paged', $page - 1)) . '">Zurück</a> '; }
        if ($page * 25 < $total) { echo '<a class="button" href="' . esc_url(add_query_arg('paged', $page + 1)) . '">Weiter</a>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_export">'; wp_nonce_field('mira_export'); submit_button('Alle Angebote als CSV exportieren', 'secondary'); echo '<p>CSV enthält personenbezogene Daten. Geschützt speichern und nach Nutzung löschen.</p></form></div>';
    }
    private static function detail(string $id): void {
        $r = Mira_Quotes::row($id); $s = Mira_Quotes::decode($r);
        echo '<div class="wrap"><h1>Service Mira · Angebotsdetails</h1><p><a href="' . esc_url(admin_url('admin.php?page=mira-quotes')) . '">← Zur Angebotsübersicht</a></p>';
        echo '<p><strong>Status:</strong> ' . esc_html($r->status) . ' · <strong>Annahme UTC:</strong> ' . esc_html($r->accepted_at ?? '—') . '</p>';
        echo '<style>.mira-admin-view{max-width:1080px}.mira-admin-view .mira-offer-two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.mira-admin-view .mira-offer-card{background:white;border:1px solid #dfe8ed;border-radius:14px;margin:0 0 18px;padding:20px}.mira-admin-view .mira-offer-row{display:grid;grid-template-columns:36% 1fr;border-bottom:1px solid #edf2f5;padding:8px 0;gap:10px}.mira-admin-view .mira-offer-row dt{color:#536d7b}.mira-admin-view .mira-offer-row dd{margin:0;font-weight:600;overflow-wrap:anywhere}.mira-admin-view h2{margin-top:0}.mira-admin-view .mira-offer-intro,.mira-admin-view .mira-offer-status{background:#e9f7fc;border-radius:12px;padding:14px;margin-bottom:17px}.mira-admin-view .mira-offer-amount{font-size:32px;font-weight:800;color:#057daa}.mira-admin-view .mira-offer-warning{font-size:13px;color:#805d27}.mira-admin-view details{max-width:1080px;overflow:hidden}.mira-admin-view pre{white-space:pre-wrap;overflow-wrap:anywhere}@media(max-width:850px){.mira-admin-view .mira-offer-two{grid-template-columns:1fr}}</style>';
        echo '<div class="mira-admin-view">' . Mira_Quotes::offer_view($s) . '</div>';
        if ($r->status === 'pending') {
            echo '<section style="max-width:1080px;background:white;border:1px solid #b8d1de;border-radius:12px;padding:20px;margin:18px 0"><h2>Interne Angebotsprüfung · nur Staging</h2>';
            echo '<p>Leistungsumfang und gewünschten Termin intern prüfen. Keine Preisfreigabe, keine Kundennachricht, keine verbindliche Buchung. Bisherige persönliche Testlinks werden bei Änderung ungültig.</p>';
            if (isset($_GET['reviewed']) && $_GET['reviewed'] === '1') { echo '<p style="color:#167348"><strong>Interne Prüfung gespeichert.</strong></p>'; }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_stage_review"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">';
            wp_nonce_field('mira_stage_review_' . $id);
            echo '<p><label for="mira-review-scope"><strong>Bestätigter Leistungsumfang (intern)</strong></label></p><textarea id="mira-review-scope" name="review_scope" rows="4" maxlength="1500" style="width:100%;max-width:780px" required>' . esc_textarea($s['price']['scope'] ?? '') . '</textarea>';
            echo '<p><label><input type="checkbox" name="confirm_date" value="1" ' . checked(!empty($s['schedule']['confirmed']), true, false) . ' required> Wunschtermin ' . esc_html($s['schedule']['preferred_date'] ?? '') . ' wurde intern geprüft (keine verbindliche Terminbuchung).</label></p>';
            submit_button('Interne Prüfung speichern · keine Buchung', 'secondary');
            echo '</form></section>';
        }
        if ($r->status === 'pending') {
            echo '<section style="max-width:1080px;background:#fff;border:1px solid #b8d1de;border-radius:12px;padding:20px;margin:18px 0"><h2>Fahrstrecke zum Angebot aktualisieren · nur Staging</h2>';
            echo '<p>Berechnet die echte Hin- und Rückfahrt anhand der gespeicherten Kundenadresse über OpenRouteService. Bei Erfolg werden Kilometer, Fahrtkosten, Steuer und Bruttogesamtbetrag im Angebotssnapshot gespeichert. Frühere persönliche Links werden widerrufen. Kein Versand, keine Freigabe und keine Buchung.</p>';
            if (isset($_GET['route_updated']) && $_GET['route_updated'] === '1') { echo '<p style="color:#167348"><strong>Fahrstrecke und Preisgrundlage gespeichert.</strong></p>'; }
            if (Mira_Routes::configured()) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_refresh_quote_route"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">';
                wp_nonce_field('mira_refresh_quote_route_' . $id);
                submit_button('OpenRouteService-Fahrstrecke prüfen und im Angebot speichern · keine Buchung', 'secondary'); echo '</form>';
            } else { echo '<p><strong>OpenRouteService API noch nicht konfiguriert.</strong> Keine geschätzte Strecke wird gespeichert.</p>'; }
            echo '</section>';
        }
        if ($r->status === 'pending') {
            echo '<section style="max-width:1080px;background:#fff;border:1px solid #b8d1de;border-radius:12px;padding:20px;margin:18px 0"><h2>Vertrags- und Stornierungsbedingungen · interne Staging-Prüfung</h2>';
            echo '<p>Nur vom Auftraggeber ausdrücklich genehmigte Texte eintragen. Keine automatisch erfundenen AGB. Speichern ersetzt den Angebotssnapshot und widerruft vorhandene Links; es versendet keine E-Mail und erzeugt keine Buchung.</p>';
            if (isset($_GET['terms_saved']) && $_GET['terms_saved'] === '1') { echo '<p style="color:#167348"><strong>Interne Vertragsprüfung gespeichert (keine Freigabe).</strong></p>'; }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_stage_terms"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">';
            wp_nonce_field('mira_stage_terms_' . $id);
            echo '<p><label for="mira-approved-terms"><strong>Genehmigte Vertrags- und Zahlungsbedingungen</strong></label></p><textarea id="mira-approved-terms" name="approved_terms" rows="5" maxlength="8000" style="width:100%;max-width:800px">' . esc_textarea($s['terms'] ?? '') . '</textarea>';
            echo '<p><label for="mira-approved-cancellation"><strong>Genehmigte Stornierungs-/Widerrufsinformation</strong></label></p><textarea id="mira-approved-cancellation" name="approved_cancellation" rows="5" maxlength="8000" style="width:100%;max-width:800px">' . esc_textarea($s['cancellation'] ?? '') . '</textarea>';
            echo '<p><label><input type="checkbox" name="terms_review_ack" value="1" required> Ich bestätige ausschließlich die interne Herkunftsprüfung der eingegebenen Texte; keine rechtliche oder buchungsbezogene Freigabe.</label></p>';
            submit_button('Texte intern speichern · keine Buchung', 'secondary');
            echo '</form></section>';
        }
        // Production only: a human-authorized, complete quote can be sent for explicit acceptance.
        // No action is rendered or accepted on staging, regardless of stored options.
        if ($r->status === 'pending' && !Mira_Quotes::allowed()
            && Mira_Quotes::settings()['acceptance'] && Mira_Quotes::settings()['reviewed']) {
            $required = Mira_Quotes::acceptance_requirements($s);
            if (!in_array(false, $required, true)) {
                echo '<section style="background:#fff;border:1px solid #b8cbd6;border-radius:10px;padding:18px;max-width:980px">';
                echo '<h2>Verbindliches Angebot zur Annahme freigeben</h2><p>Nur nach ausdrücklicher Freigabe durch Service Mira. Dadurch wird ein neuer persönlicher Link erzeugt und das bestätigte Angebot an den Kunden gesendet.</p>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_approve_live_quote"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">';
                wp_nonce_field('mira_approve_live_quote_' . $id);
                echo '<p><label><input type="checkbox" name="approve" value="1" required> Ich bin zur Freigabe im Namen von Service Mira berechtigt und bestätige Leistungsumfang, Termin, Endpreis einschließlich Anfahrt und Mehrwertsteuer sowie die genehmigten Vertrags- und Stornierungsbedingungen.</label></p>';
                submit_button('Verbindliches Angebot freigeben und an Kunden versenden', 'primary');
                echo '</form></section>';
            }
        }
        // Read-only production readiness report. Never authorizes mail, routing or booking.
        $route = isset($s['route']) && is_array($s['route']) ? $s['route'] : null;
        $price = isset($s['price']) && is_array($s['price']) ? $s['price'] : [];
        $checks = [
            'Steuersatz vom Auftraggeber genehmigt' => !empty($s['price']['vat_approved']),
            'Stundensatz: 20,00 EUR netto' => (int) ($price['hourly_cents'] ?? -1) === 2000,
            'Zwei echte Fahrstrecken aus OpenRouteService gespeichert' => $route !== null && isset($route['outbound_m'], $route['return_m'], $route['roundtrip_m']) && (int) $route['roundtrip_m'] === (int) $route['outbound_m'] + (int) $route['return_m'],
            'Fahrtkosten gesondert berechnet' => $route !== null && isset($price['fee_cents']) && (int) $price['fee_cents'] === (int) ($route['travel_cents'] ?? -1),
            'Bruttoendbetrag inklusive Fahrtkosten' => $route !== null && isset($price['gross_cents']) && (int) $price['gross_cents'] === (int) ($route['gross_cents'] ?? -1),
            'Konkreter Leistungsumfang bestätigt' => !empty($price['scope']),
            'Kundentermin verbindlich bestätigt' => !empty($s['schedule']['confirmed']),
            'Vertragliche Bedingungen hinterlegt' => !empty($s['terms']) && !empty($s['cancellation']),
            'Geschäftliche / rechtliche Freigabe' => false,
            'Produktions-E-Mail und Live-Buchung freigegeben' => false,
        ];
        echo '<section style="max-width:1080px;background:#fff;border:1px solid #c8d8e0;border-radius:12px;padding:20px;margin:18px 0"><h2>Produktionsbereitschaft · nur Prüfung</h2>';
        echo '<p>Dieser Bericht liest nur den gespeicherten Angebotssnapshot. Er versendet nichts und gibt keine Buchung frei.</p><ul style="line-height:2">';
        foreach ($checks as $label => $passed) {
            echo '<li><strong>' . ($passed ? '✓ ' : '— ') . esc_html($label) . '</strong> — ' . ($passed ? 'bestätigt' : 'offen') . '</li>';
        }
        echo '</ul>';
        if ($route !== null && isset($route['outbound_m'], $route['return_m'])) {
            echo '<p><strong>Strecke laut Snapshot:</strong> Hin ' . esc_html(number_format((int) $route['outbound_m'] / 1000, 2, ',', '.')) . ' km · Zurück ' . esc_html(number_format((int) $route['return_m'] / 1000, 2, ',', '.')) . ' km.</p>';
        } else {
            echo '<p><strong>Keine bestätigte OpenRouteService-Fahrstrecke im Snapshot.</strong> Ohne aktivierte Routes API darf keine Entfernung angenommen werden.</p>';
        }
        echo '<p><strong>Aktueller Status:</strong> keine rechtsverbindliche Buchung, Kundenzustellung im Staging gesperrt.</p></section>';
        echo '<details style="max-width:1080px;margin:18px 0;background:#fff;border:1px solid #c8d8e0;border-radius:12px;padding:16px"><summary><strong>Vorschau: Kunden-E-Mail (nur Staging, kein Versand)</strong></summary>';
        echo '<p>Vorschau mit aktuellen Angebotsdaten. Ohne echten Annahme-Link und ohne E-Mail-Versand.</p>';
        echo '<div style="max-width:660px;padding:12px;border:1px solid #d5e6ed;border-radius:8px">' . Mira_Quotes::customer_email_html($s) . '</div></details>';
        echo '<details><summary><strong>Technisches Prüfprotokoll und vollständiger Snapshot</strong></summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-width:1080px">' . esc_html(Mira_Quotes::summary($s)) . '</pre></details>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_pdf_preview"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">'; wp_nonce_field('mira_pdf_preview_' . $id); submit_button('PDF-Angebotsvorschau herunterladen (Staging)', 'secondary'); echo '</form>';
        if ($r->status === 'pending') {
            echo '<section style="background:#fff;border:1px solid #b8cbd6;border-radius:10px;padding:18px;max-width:1000px;margin:18px 0">';
            echo '<h2>Fahrtkosten · Staging-Rechenprobe</h2><p>10 km frei, danach 0,50 € netto/km. Manuell angenommene Strecke – keine automatische Entfernungsermittlung, keine Preisfreigabe.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_travel_scenario"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">';
            wp_nonce_field('mira_travel_scenario_' . $id);
            echo '<style>
                .mira-travel-test-form .mira-travel-fields{display:flex;align-items:flex-end;flex-wrap:wrap;gap:14px 16px;margin:18px 0 10px}
                .mira-travel-test-form .mira-travel-field{display:flex;flex-direction:column;gap:8px;flex:0 1 250px;max-width:100%;min-width:190px}
                .mira-travel-test-form .mira-travel-field label{font-weight:600;color:#18384a;font-size:13px;line-height:1.45}
                .mira-travel-test-form #mira-km{box-sizing:border-box;display:block;width:100%;max-width:100%;height:44px;min-height:44px;margin:0;padding:10px 13px;border:1px solid #bbd5e2;border-radius:9px;background:#fff;font-size:15px;line-height:1.3;box-shadow:none}
                .mira-travel-test-form #mira-km:focus{border-color:#0895bf;box-shadow:0 0 0 2px rgba(8,149,191,.16);outline:none}
                .mira-travel-test-form .mira-travel-submit{flex:0 1 auto;max-width:100%}
                .mira-travel-test-form .mira-travel-submit .button{box-sizing:border-box;min-height:44px;height:auto;margin:0;padding:9px 16px;border:1px solid #087eab;border-radius:9px;color:#fff;background:#087eab;font-weight:600;white-space:normal;text-align:center;line-height:1.45}
                .mira-travel-test-form .mira-travel-submit .button:hover,.mira-travel-test-form .mira-travel-submit .button:focus{background:#09678f;color:#fff;border-color:#09678f}
                @media(max-width:782px){.mira-travel-test-form .mira-travel-fields{display:flex;flex-direction:column;align-items:stretch;gap:12px}.mira-travel-test-form .mira-travel-field{flex:0 1 auto;width:100%;min-width:0}.mira-travel-test-form .mira-travel-submit,.mira-travel-test-form .mira-travel-submit .button{width:100%}}
            </style>';
            echo '<div class="mira-travel-test-form"><div class="mira-travel-fields"><div class="mira-travel-field"><label for="mira-km">Teststrecke (Kilometer)</label><input type="text" id="mira-km" name="distance_km" pattern="[0-9]+([.,][0-9]{1,2})?" inputmode="decimal" value="15" placeholder="z. B. 15,50" autocomplete="off" aria-describedby="mira-travel-helper" required></div><div class="mira-travel-submit">';
            submit_button('Unverbindliche Kalkulation anzeigen', 'secondary', 'submit', false);
            echo '</div></div><p id="mira-travel-helper" style="margin:0;color:#617d8a;font-size:12px">Manuelle Teststrecke in km – keine echte Routenberechnung und keine Preisfreigabe.</p></div>';
            echo '</form><p><strong>Fehlende Produktionsfreigaben:</strong> OpenRouteService API / tatsächliche Strecke falls nicht hinterlegt; steuerliche Behandlung; konkreter Leistungsumfang und Kundentermin; rechtliche Freigabe, verbindliches Angebot und Produktionsemail. Diese Funktion verändert keine Kundendaten.</p></section>';
        }
        echo '<h2>Annahmeevidenz</h2><pre style="white-space:pre-wrap">' . esc_html($r->evidence ?? 'Noch keine Annahme.') . '</pre>';
        echo '<h2>Mailstatus (Transportannahme ist keine Zustellbestätigung)</h2><pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html($r->mail_state ?? 'Nicht erfasst') . '</pre>';
        if ($r->status === 'pending' && strtotime($r->expires_at . ' UTC') > time()) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_test_link"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">'; wp_nonce_field('mira_test_link_' . $id); submit_button('Neuen Testlink erzeugen (alten Link widerrufen)', 'secondary'); echo '</form>';
        }
        echo '<hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mira_delete"><input type="hidden" name="quote_id" value="' . esc_attr($id) . '">'; wp_nonce_field('mira_delete_' . $id); echo '<label><input type="checkbox" name="confirm" value="1" required> Angebot einschließlich Evidenz endgültig löschen; Aufbewahrungspflichten wurden geprüft.</label>'; submit_button('Diesen Datensatz endgültig löschen', 'delete'); echo '</form></div>';
    }
    /** Record administrator-reviewed scope and proposed date; never enable booking or mailing. */
    public static function stage_review(): void {
        $id = Mira_Quotes::value('quote_id');
        self::guard('mira_stage_review_' . $id);
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungültige Angebots-ID.', 400); }
        $scope = sanitize_textarea_field(Mira_Quotes::value('review_scope'));
        $date_ok = Mira_Quotes::value('confirm_date') === '1';
        if (trim($scope) === '' || mb_strlen($scope) > 1500 || !$date_ok) { Mira_Quotes::fail('Leistungsumfang und Terminprüfung sind erforderlich.'); }
        global $wpdb;
        $row = Mira_Quotes::row($id);
        if (!$row || $row->status !== 'pending') { Mira_Quotes::fail('Nur offene Angebote dürfen geprüft werden.', 409); }
        $old_mac = $row->snapshot_mac;
        if (!hash_equals($old_mac, Mira_Quotes::mac($row->snapshot))) { Mira_Quotes::fail('Integritätsprüfung fehlgeschlagen.', 409); }
        $snapshot = Mira_Quotes::decode($row);
        $snapshot['price']['scope'] = $scope;
        $snapshot['schedule']['confirmed'] = true;
        $snapshot['review_history'][] = [
            'reviewed_utc' => gmdate('c'),
            'admin_user_id' => get_current_user_id(),
            'previous_sha256' => hash('sha256', $row->snapshot),
            'scope_confirmed' => true,
            'preferred_date_reviewed' => true,
            'staging_only' => true,
        ];
        // Preserve all safeguards: this remains an unapproved non-binding request.
        $snapshot['test'] = true;
        $snapshot['price']['eligible'] = false;
        $snapshot['price']['complete'] = false;
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) { Mira_Quotes::fail('Snapshot konnte nicht erstellt werden.', 500); }
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . Mira_Quotes::table() . " SET snapshot=%s, snapshot_mac=%s, token_hash='' WHERE quote_id=%s AND status='pending' AND snapshot_mac=%s",
            $json, Mira_Quotes::mac($json), $id, $old_mac
        ));
        if ($updated !== 1) { Mira_Quotes::fail('Zwischenzeitlich geändert. Bitte neu laden.', 409); }
        wp_safe_redirect(add_query_arg(['page' => 'mira-quotes', 'quote' => $id, 'reviewed' => '1'], admin_url('admin.php')), 303);
        exit;
    }
    /** Per-quotation terms staging review; never enables acceptance or delivery. */
    public static function stage_terms(): void {
        $id = Mira_Quotes::value('quote_id');
        self::guard('mira_stage_terms_' . $id);
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungültige Angebots-ID.', 400); }
        if (Mira_Quotes::value('terms_review_ack') !== '1') { Mira_Quotes::fail('Interne Prüfung muss bestätigt werden.', 400); }
        $terms = sanitize_textarea_field(Mira_Quotes::value('approved_terms'));
        $cancellation = sanitize_textarea_field(Mira_Quotes::value('approved_cancellation'));
        if (mb_strlen($terms) > 8000 || mb_strlen($cancellation) > 8000) { Mira_Quotes::fail('Text zu lang.', 400); }
        global $wpdb;
        $row = Mira_Quotes::row($id);
        if (!$row || $row->status !== 'pending') { Mira_Quotes::fail('Nur offene Angebote dürfen bearbeitet werden.', 409); }
        $old_mac = $row->snapshot_mac;
        if (!hash_equals($old_mac, Mira_Quotes::mac($row->snapshot))) { Mira_Quotes::fail('Integritätsprüfung fehlgeschlagen.', 409); }
        $snapshot = Mira_Quotes::decode($row);
        $snapshot['terms'] = $terms;
        $snapshot['cancellation'] = $cancellation;
        $snapshot['terms_review_history'][] = [
            'reviewed_utc' => gmdate('c'), 'admin_user_id' => get_current_user_id(),
            'previous_sha256' => hash('sha256', $row->snapshot),
            'terms_sha256' => hash('sha256', $terms),
            'cancellation_sha256' => hash('sha256', $cancellation),
            'staging_only' => true, 'booking_authorized' => false,
        ];
        $snapshot['test'] = true;
        $snapshot['price']['eligible'] = false;
        $snapshot['price']['complete'] = false;
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) { Mira_Quotes::fail('Snapshot konnte nicht erstellt werden.', 500); }
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . Mira_Quotes::table() . " SET snapshot=%s, snapshot_mac=%s, token_hash='' WHERE quote_id=%s AND status='pending' AND snapshot_mac=%s",
            $json, Mira_Quotes::mac($json), $id, $old_mac
        ));
        if ($updated !== 1) { Mira_Quotes::fail('Zwischenzeitlich geändert. Bitte neu laden.', 409); }
        wp_safe_redirect(add_query_arg(['page' => 'mira-quotes', 'quote' => $id, 'terms_saved' => '1'], admin_url('admin.php')), 303);
        exit;
    }
    /** Explicit, production-only human approval; never callable on staging. */
    public static function approve_live_quote(): void {
        $id = Mira_Quotes::value('quote_id');
        self::guard('mira_approve_live_quote_' . $id);
        $settings = Mira_Quotes::settings();
        if (Mira_Quotes::allowed() || !$settings['acceptance'] || !$settings['reviewed']
            || Mira_Quotes::value('approve') !== '1') {
            Mira_Quotes::fail('Produktionsfreigabe nicht erteilt.', 403);
        }
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungültige Angebots-ID.', 400); }
        $row = Mira_Quotes::row($id);
        if (!$row || $row->status !== 'pending' || strtotime($row->expires_at . ' UTC') <= time()) {
            Mira_Quotes::fail('Angebot abgelaufen oder nicht mehr offen.', 409);
        }
        $snapshot = Mira_Quotes::decode($row);
        if (in_array(false, Mira_Quotes::acceptance_requirements($snapshot), true)) {
            Mira_Quotes::fail('Konditionen noch nicht vollständig bestätigt.', 409);
        }
        // Approving a changed snapshot revokes earlier links, and does not itself book anything.
        $snapshot['test'] = false;
        $snapshot['price']['eligible'] = true;
        $snapshot['price']['complete'] = true;
        $snapshot['approved_utc'] = gmdate('c');
        $snapshot['approved_admin_id'] = get_current_user_id();
        $snapshot['approval_previous_sha256'] = hash('sha256', $row->snapshot);
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) { Mira_Quotes::fail('Snapshot konnte nicht gespeichert werden.', 500); }
        $token = bin2hex(random_bytes(32));
        global $wpdb;
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE " . Mira_Quotes::table() . " SET snapshot=%s,snapshot_mac=%s,token_hash=%s WHERE quote_id=%s AND status='pending' AND snapshot_mac=%s AND expires_at>%s",
            $json, Mira_Quotes::mac($json), hash('sha256', $token), $id, $row->snapshot_mac, gmdate('Y-m-d H:i:s')
        ));
        if ($changed !== 1) { Mira_Quotes::fail('Angebot wurde zwischenzeitlich verändert.', 409); }
        $link = add_query_arg(['action'=>'mira_accept','id'=>$id,'token'=>$token], admin_url('admin-post.php'));
        Mira_Quotes::mail($id, $snapshot, $link, false);
        wp_safe_redirect(add_query_arg(['page'=>'mira-quotes','quote'=>$id,'approved'=>'1'], admin_url('admin.php')), 303);
        exit;
    }
    /** Refresh a pending quotation from real OpenRouteService driving routes; fail closed on API errors. */
    public static function refresh_quote_route(): void {
        $id = Mira_Quotes::value('quote_id');
        self::guard('mira_refresh_quote_route_' . $id);
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungültige Angebots-ID.', 400); }
        if (!Mira_Routes::configured()) { Mira_Quotes::fail('OpenRouteService API nicht konfiguriert.', 503); }
        $row = Mira_Quotes::row($id);
        if (!$row || $row->status !== 'pending') { Mira_Quotes::fail('Nur offene Angebote dürfen aktualisiert werden.', 409); }
        $old_mac = $row->snapshot_mac;
        $snapshot = Mira_Quotes::decode($row);
        $base = $snapshot['price']['base_cents'] ?? null;
        $customer = $snapshot['customer'] ?? [];
        $address = trim((string) ($customer['address'] ?? ''));
        $postcode = trim((string) ($customer['postcode'] ?? ''));
        if (!is_int($base) || $base < 0 || !$address || !preg_match('/^[0-9]{5}$/D', $postcode)) { Mira_Quotes::fail('Adressdaten oder Basispreis unvollständig.', 400); }
        try { $route = Mira_Routes::quote($address . ', ' . $postcode . ', Germany', $base, 1900); }
        catch (Throwable $e) { error_log('[Service Mira] Admin route refresh failed.'); Mira_Quotes::fail('OpenRouteService-Fahrstrecke konnte nicht bestätigt werden. Keine Daten geändert.', 503); }
        $snapshot['route'] = $route;
        $snapshot['price']['fee_cents'] = $route['travel_cents'];
        $snapshot['price']['fee_label'] = 'Hin- und Rückfahrt: ' . number_format($route['roundtrip_m'] / 1000, 2, ',', '.') . ' km (10 km frei, danach 0,50 EUR/km)';
        $snapshot['price']['net_cents'] = $route['net_cents'];
        $snapshot['price']['tax_cents'] = $route['vat_cents'];
        $snapshot['price']['gross_cents'] = $route['gross_cents'];
        $snapshot['price']['eligible'] = false;
        $snapshot['price']['complete'] = false;
        $snapshot['test'] = true;
        $snapshot['review_history'][] = ['event'=>'staging_route_refresh', 'reviewed_utc'=>gmdate('c'), 'admin_user_id'=>get_current_user_id(), 'previous_sha256'=>hash('sha256', $row->snapshot), 'roundtrip_m'=>$route['roundtrip_m'], 'staging_only'=>true];
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) { Mira_Quotes::fail('Snapshot konnte nicht erstellt werden.', 500); }
        global $wpdb;
        $updated = $wpdb->query($wpdb->prepare("UPDATE " . Mira_Quotes::table() . " SET snapshot=%s, snapshot_mac=%s, token_hash='' WHERE quote_id=%s AND status='pending' AND snapshot_mac=%s", $json, Mira_Quotes::mac($json), $id, $old_mac));
        if ($updated !== 1) { Mira_Quotes::fail('Daten zwischenzeitlich geändert. Bitte neu laden.', 409); }
        wp_safe_redirect(add_query_arg(['page'=>'mira-quotes','quote'=>$id,'route_updated'=>'1'],admin_url('admin.php')),303);
        exit;
    }
    /** Staging calculator only: never modifies the saved snapshot or enables acceptance. */
    public static function travel_scenario(): void {
        $id = Mira_Quotes::value('quote_id');
        self::guard('mira_travel_scenario_' . $id);
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungültige Angebots-ID.'); }
        $row = Mira_Quotes::row($id);
        $snapshot = Mira_Quotes::decode($row);
        try {
            $price = Mira_Domain::travel_scenario((int) ($snapshot['price']['base_cents'] ?? -1), Mira_Quotes::value('distance_km'));
        } catch (InvalidArgumentException $e) { Mira_Quotes::fail($e->getMessage()); }
        Mira_Quotes::private_headers();
        echo '<div class="wrap" style="max-width:760px;margin:50px auto"><h1>Staging: unverbindliche Kilometer-Kalkulation</h1>';
        echo '<p><strong>Nur Rechenprobe.</strong> Keine Änderung am Angebot, keine E-Mail, keine Buchung. Entfernungsmethode und Steuerbehandlung müssen bestätigt werden.</p>';
        echo '<table class="widefat striped"><tbody>';
        foreach (['Fahrtstrecke (angenommen)' => $price['distance_km'] . ' km', 'Anfahrt netto' => Mira_Domain::money($price['travel_cents']), 'Service + Anfahrt netto' => Mira_Domain::money($price['net_cents']), 'MwSt. (vorläufig 19 %)' => Mira_Domain::money($price['vat_cents']), 'Brutto gesamt (nur Simulation)' => Mira_Domain::money($price['gross_cents'])] as $key => $value) {
            echo '<tr><th>' . esc_html($key) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table><p><a class="button" href="' . esc_url(admin_url('admin.php?page=mira-quotes&quote=' . rawurlencode($id))) . '">Zurück zum Angebot</a></p></div>';
        exit;
    }
    public static function pdf_preview(): void {
        self::guard('mira_pdf_preview_' . Mira_Quotes::value('quote_id'));
        $id = Mira_Quotes::value('quote_id');
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) { Mira_Quotes::fail('Ungueltige Angebots-ID.'); }
        $row = Mira_Quotes::row($id);
        $s = Mira_Quotes::decode($row); // MAC integrity verification before PDF rendering.
        $pdf = Mira_Pdf::render($s);
        Mira_Quotes::private_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Mira-Testangebot-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    public static function test_link(): void {
        $id = Mira_Quotes::value('quote_id'); self::guard('mira_test_link_' . $id); $r = Mira_Quotes::row($id); Mira_Quotes::decode($r);
        $token = bin2hex(random_bytes(32)); global $wpdb;
        $n = $wpdb->query($wpdb->prepare('UPDATE ' . Mira_Quotes::table() . " SET token_hash=%s WHERE quote_id=%s AND status='pending' AND token_hash=%s AND expires_at>%s", hash('sha256', $token), $id, $r->token_hash, gmdate('Y-m-d H:i:s')));
        if ($n !== 1) { Mira_Quotes::fail('Link kann nicht mehr erneuert werden.', 409); }
        $url = add_query_arg(['action' => 'mira_accept', 'id' => $id, 'token' => $token], admin_url('admin-post.php'));
        Mira_Quotes::page('Persönlicher Testlink', '<p>Der vorige Link ist jetzt ungültig. Dieser Link wird nur einmal angezeigt; ausschließlich mit Testdaten verwenden.</p><p><a href="' . esc_url($url) . '" rel="noreferrer">Testangebot öffnen</a></p>');
    }
    public static function delete(): void {
        $id = Mira_Quotes::value('quote_id'); self::guard('mira_delete_' . $id); if (Mira_Quotes::value('confirm') !== '1') { Mira_Quotes::fail('Löschbestätigung fehlt.'); }
        global $wpdb; $wpdb->delete(Mira_Quotes::table(), ['quote_id' => $id]); wp_safe_redirect(admin_url('admin.php?page=mira-quotes')); exit;
    }
    public static function export(): void {
        self::guard('mira_export'); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="mira-angebote.csv"');
        $out = fopen('php://output', 'w'); fputs($out, "\xEF\xBB\xBF");
        $write = function ($row) use ($out) { fputcsv($out, array_map(fn($v) => Mira_Domain::csv((string) ($v ?? '')), $row), ';', '"', ''); };
        $write(['quote_id', 'status', 'created_utc', 'expires_utc', 'accepted_utc', 'name', 'email', 'phone', 'address', 'service', 'hours', 'net_cents', 'tax_cents', 'gross_cents', 'snapshot_json', 'evidence_json']);
        global $wpdb; $after = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Mira_Quotes::table() . ' WHERE id>%d ORDER BY id ASC LIMIT 200', $after));
            foreach ($rows as $r) { $s = Mira_Quotes::decode($r); $c = $s['customer']; $p = $s['price']; $write([$r->quote_id, $r->status, $r->created_at, $r->expires_at, $r->accepted_at, $c['name'], $c['email'], $c['phone'], $c['address'], $p['service'], $p['units'] / 4, $p['net_cents'], $p['tax_cents'], $p['gross_cents'], $r->snapshot, $r->evidence]); $after = (int) $r->id; }
        } while (count($rows) === 200);
        fclose($out); exit;
    }
}
