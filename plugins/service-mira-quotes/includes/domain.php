<?php
// Pure calculations, independent of WordPress for tests. Monetary amounts are integer cents.
final class Mira_Domain {
    public static function defaults(): array {
        $services = [];
        foreach (['unterhalt' => 'Unterhaltsreinigung', 'einkauf' => 'Einkaufshilfe', 'garten' => 'Gartenarbeit', 'haushalt' => 'Haushaltsreinigung', 'treppen' => 'Treppenreinigung'] as $id => $name) {
            $services[$id] = ['name' => $name, 'cents' => 2000, 'confirmed' => true, 'scope' => ''];
        }
        foreach (['buero' => 'Büroreinigung', 'praxis' => 'Praxisreinigung', 'bau' => 'Bauendreinigung', 'grund' => 'Grundreinigung', 'fenster' => 'Fensterreinigung'] as $id => $name) {
            $services[$id] = ['name' => $name, 'cents' => 2000, 'confirmed' => true, 'scope' => ''];
        }
        return ['services' => $services, 'vat_mode' => 'net', 'vat_bps' => 1900, 'vat_approved' => false, 'fee_cents' => null, 'fee_label' => '', 'minimum_units' => null, 'acceptance' => false, 'reviewed' => false, 'terms' => '', 'cancellation' => '', 'provider' => '', 'admin_email' => 'inf@service-mira.de', 'privacy_page' => 0, 'imprint_page' => 0, 'ttl_hours' => 72];
    }
    public static function units(string $hours): int {
        if (!preg_match('/^\d{1,3}(?:[.,](?:00|25|50|75|0|5))?$/D', $hours)) { throw new InvalidArgumentException('Bitte Stunden in Viertelstunden angeben.'); }
        $n = (int) round((float) str_replace(',', '.', $hours) * 4);
        if ($n < 1 || $n > 400) { throw new InvalidArgumentException('Technischer Bereich: 0,25 bis 100 Stunden.'); }
        return $n;
    }
    public static function price(array $s, string $id, int $units): array {
        if (!isset($s['services'][$id]) || $units < 1 || $units > 400) { throw new InvalidArgumentException('Ungültige Leistung oder Stundenanzahl.'); }
        $v = $s['services'][$id];
        if ($s['minimum_units'] !== null && $units < $s['minimum_units']) { throw new InvalidArgumentException('Die bestätigte Mindestbuchung beträgt ' . ($s['minimum_units'] / 4) . ' Stunden.'); }
        $base = $v['cents'] === null ? null : intdiv($v['cents'] * $units + 2, 4);
        $complete = $base !== null && $v['confirmed'] && trim($v['scope']) !== '' && $s['fee_cents'] !== null && trim($s['fee_label']) !== '' && in_array($s['vat_mode'], ['net', 'gross', 'exempt'], true) && ($s['vat_mode'] === 'exempt' || $s['vat_bps'] !== null);
        $net = $tax = $gross = null;
        // Show VAT separately on the confirmed hourly subtotal even when travel is pending.
        // A fully binding gross total requires final travel and terms confirmation.
        if (!$complete && $base !== null && $s['vat_mode'] === 'net' && $s['vat_bps'] !== null) {
            $net = $base; $tax = intdiv($net * $s['vat_bps'] + 5000, 10000);
        }
        if ($complete) {
            $subtotal = $base + $s['fee_cents'];
            if ($s['vat_mode'] === 'net') { $net = $subtotal; $tax = intdiv($net * $s['vat_bps'] + 5000, 10000); $gross = $net + $tax; }
            elseif ($s['vat_mode'] === 'gross') { $gross = $subtotal; $net = intdiv($gross * 10000 + intdiv(10000 + $s['vat_bps'], 2), 10000 + $s['vat_bps']); $tax = $gross - $net; }
            else { $gross = $net = $subtotal; $tax = 0; }
        }
        return ['service_id' => $id, 'service' => $v['name'], 'scope' => $v['scope'], 'units' => $units, 'hourly_cents' => $v['cents'], 'base_cents' => $base, 'vat_mode' => $s['vat_mode'], 'vat_bps' => $s['vat_mode'] === 'exempt' ? 0 : $s['vat_bps'], 'vat_approved' => !empty($s['vat_approved']), 'fee_cents' => $s['fee_cents'], 'fee_label' => $s['fee_label'], 'minimum_units' => $s['minimum_units'], 'net_cents' => $net, 'tax_cents' => $tax, 'gross_cents' => $gross, 'complete' => (bool) $complete, 'eligible' => (bool) ($complete && !empty($s['vat_approved']) && $s['acceptance'] && $s['reviewed'] && trim($s['terms']) !== '' && trim($s['cancellation']) !== '' && trim($s['provider']) !== '')];
    }
    /** Indicative one-way distance scenario; no quote mutation or binding offer. */
    public static function travel_scenario(int $base_cents, string $distance, int $vat_bps = 1900): array {
        if (!preg_match('/^(?:0|[1-9][0-9]{0,3})(?:[.,][0-9]{1,2})?$/D', $distance)) {
            throw new InvalidArgumentException('Entfernung muss eine positive Zahl mit maximal zwei Nachkommastellen sein.');
        }
        $km100 = (int) round((float) str_replace(',', '.', $distance) * 100);
        if ($km100 > 100000 || $base_cents < 0 || $vat_bps < 0 || $vat_bps > 10000) {
            throw new InvalidArgumentException('Ungültige Entfernung oder Beträge.');
        }
        $excess100 = max(0, $km100 - 1000);
        $travel_cents = intdiv($excess100 * 50 + 50, 100); // €0.50/km, cent rounding half up
        $net_cents = $base_cents + $travel_cents;
        $vat_cents = intdiv($net_cents * $vat_bps + 5000, 10000);
        return ['distance_km' => number_format($km100 / 100, 2, ',', '.'), 'free_km' => 10,
            'travel_cents' => $travel_cents, 'net_cents' => $net_cents, 'vat_cents' => $vat_cents,
            'gross_cents' => $net_cents + $vat_cents, 'vat_bps' => $vat_bps];
    }
    public static function money(?int $cents): string { return $cents === null ? 'Bestätigung ausstehend' : number_format($cents / 100, 2, ',', '.') . ' €'; }
    public static function csv(string $value): string { return preg_match('/^[\s]*[=+\-@\t\r\n]/u', $value) ? "'" . $value : $value; }
}
