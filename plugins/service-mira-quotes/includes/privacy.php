<?php
defined('ABSPATH') || exit;
final class Mira_Privacy {
    public static function boot(): void {
        add_filter('wp_privacy_personal_data_exporters', function ($items) { $items['mira-quotes'] = ['exporter_friendly_name' => 'Service Mira Angebote', 'callback' => [self::class, 'export']]; return $items; });
        add_filter('wp_privacy_personal_data_erasers', function ($items) { $items['mira-quotes'] = ['eraser_friendly_name' => 'Service Mira Angebote', 'callback' => [self::class, 'erase']]; return $items; });
        add_action('admin_init', function () { if (function_exists('wp_add_privacy_policy_content')) { wp_add_privacy_policy_content('Service Mira Angebote', '<p>Vorschlag zur Prüfung: Angebotsanfragen speichern Namen, E-Mail, optional Telefon, Einsatzanschrift, Leistungs-/Preis-Snapshot, Datenschutzhinweis und bei Annahme Zeitpunkt sowie Zustimmungstext. Notwendige HttpOnly-Sitzungscookies laufen nach maximal zwei Stunden ab. Kurzzeitige HMAC-basierte Missbrauchszähler werden nach rund zwei Stunden bei Folgezuwriffen bereinigt. Persönliche Angebotslinks sind vertraulich. Aufbewahrungsfristen, Rechtsgrundlage, Verantwortlicher, Versanddienstleister und Betroffenenrechte müssen vom Betreiber ergänzt und geprüft werden. Keine automatische Löschung von Angebotsnachweisen; Löschung erfolgt kontrolliert über den Adminbereich bzw. bestätigte Datenschutzanfragen.</p>'); } });
    }
    public static function export(string $email, int $page = 1): array {
        if (!Mira_Quotes::allowed()) { return ['data' => [], 'done' => true]; }
        global $wpdb; $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Mira_Quotes::table() . ' WHERE customer_email=%s ORDER BY id ASC LIMIT 50 OFFSET %d', $email, (max(1, $page) - 1) * 50));
        $data = [];
        foreach ($rows as $r) { $s = Mira_Quotes::decode($r); $data[] = ['group_id' => 'mira-quotes', 'group_label' => 'Service Mira Angebote', 'item_id' => 'mira-' . $r->quote_id, 'data' => [['name' => 'Angebot', 'value' => Mira_Quotes::summary($s)], ['name' => 'Status', 'value' => $r->status], ['name' => 'Annahmezeit UTC', 'value' => $r->accepted_at ?? ''], ['name' => 'Evidenz', 'value' => $r->evidence ?? '']]]; }
        return ['data' => $data, 'done' => count($rows) < 50];
    }
    public static function erase(string $email, int $page = 1): array {
        if (!Mira_Quotes::allowed()) { return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true]; }
        global $wpdb;
        // One bounded DELETE query: no shifting pagination after deletion.
        $n = $wpdb->query($wpdb->prepare('DELETE FROM ' . Mira_Quotes::table() . " WHERE customer_email=%s AND status='pending' LIMIT 100", $email));
        $left = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Mira_Quotes::table() . " WHERE customer_email=%s AND status='pending'", $email));
        $retained = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Mira_Quotes::table() . " WHERE customer_email=%s AND status='accepted'", $email));
        return ['items_removed' => $n > 0, 'items_retained' => $retained > 0, 'messages' => $retained ? ['Angenommene Angebote zur Prüfung von Aufbewahrungspflichten zurückbehalten. Nach Prüfung im Mira-Adminbereich einzeln löschen.'] : [], 'done' => $left === 0];
    }
}
