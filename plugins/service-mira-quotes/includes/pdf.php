<?php
 defined('ABSPATH') || exit;
/** Restricted, in-memory, single-page PDF rendering for staging proposals. */
final class Mira_Pdf {
    private static function safe(string $text): string {
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $text ?: '');
    }
    public static function render(array $s): string {
        $p = $s['price']; $customer = $s['customer']; $schedule = $s['schedule'];
        $date = $schedule['preferred_date'] ?? '';
        if (preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $date, $m)) { $date = "$m[3].$m[2].$m[1]"; }
        $rows = [
            'SERVICE MIRA - ANGEBOTSVORSCHAU (STAGING)',
            'NICHT VERBINDLICH - Keine Buchung oder abschließende Preiszusage',
            'Angebots-ID: ' . ($s['quote_id'] ?? ''),
            'Kunde: ' . ($customer['name'] ?? ''),
            'Service: ' . ($p['service'] ?? ''),
            'Stunden: ' . number_format(($p['units'] ?? 0) / 4, 2, ',', '.') . ' | Netto-Stundensatz: ' . Mira_Domain::money($p['hourly_cents']),
            'Gewünschtes Datum: ' . $date . ' (noch nicht bestätigt)',
            'Gewünschtes Zeitfenster: ' . ($schedule['preferred_time'] ?? ''),
            'Leistungsumfang: ' . (!empty($p['scope']) ? mb_substr((string) $p['scope'], 0, 250) : 'Wird vor verbindlichem Angebot abgestimmt'),
            'Leistungsbetrag netto: ' . Mira_Domain::money($p['base_cents']),
            'Anfahrt: 10 km frei, danach 0,50 EUR je zusätzlichem Kilometer',
            'Fahrstrecke (Hin- und Rückfahrt): ' . (isset($s['route']['roundtrip_m']) ? number_format($s['route']['roundtrip_m'] / 1000, 2, ',', '.') . ' km' : 'Bestätigung ausstehend'),
            'Entfernung / Fahrtkosten: ' . Mira_Domain::money($p['fee_cents']),
            'MwSt.-Satz: ' . ($p['vat_bps'] === null ? 'Noch zu bestätigen' : number_format($p['vat_bps']/100, 2, ',', '.') . '%'),
            'MwSt.-Betrag: ' . Mira_Domain::money($p['tax_cents']),
            'Gesamtpreis brutto: ' . Mira_Domain::money($p['gross_cents']),
            'Vertragsbedingungen (Staging): ' . (!empty($s['terms']) ? mb_substr((string) $s['terms'], 0, 120) . (mb_strlen((string) $s['terms']) > 120 ? ' [Auszug]' : '') : 'Noch nicht hinterlegt'),
            '24-Stunden-Stornierung: ' . Mira_Quotes::cancellation_policy(),
            ...(Mira_Quotes::additional_cancellation_info($s['cancellation'] ?? '') !== '' ? ['Weitere Widerrufsinformationen (Staging): ' . mb_substr(Mira_Quotes::additional_cancellation_info($s['cancellation']), 0, 120) . (mb_strlen(Mira_Quotes::additional_cancellation_info($s['cancellation'])) > 120 ? ' [Auszug]' : '')] : []),
            'Fahrkosten und Gesamtpreis gelten bis zur Freigabe nur als Vorschau.',
            'Der Wunschtermin wird erst nach ausdrücklicher Annahme verbindlich.',
            'Keine Buchung; Annahme in der Vorschau gesperrt.',
            'Routendaten: (c) openrouteservice.org by HeiGIT | Kartendaten (c) OpenStreetMap-Mitwirkende',
        ];
        $commands = "BT /F1 14 Tf 48 782 Td (" . self::safe(array_shift($rows)) . ") Tj ET\n";
        $y = 754;
        foreach ($rows as $line) {
            $wrapped = wordwrap($line, 88, "\n", true);
            foreach (explode("\n", $wrapped) as $ln) { $commands .= "BT /F1 10 Tf 48 $y Td (" . self::safe($ln) . ") Tj ET\n"; $y -= 22; }
        }
        $objs = [
           '<< /Type /Catalog /Pages 2 0 R >>',
           '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
           '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
           '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
           '<< /Length ' . strlen($commands) . " >>\nstream\n" . $commands . "endstream",
        ];
        $data = "%PDF-1.4\n%MIRA\n"; $offsets = [0];
        foreach ($objs as $i => $obj) { $offsets[] = strlen($data); $data .= ($i+1) . " 0 obj\n" . $obj . "\nendobj\n"; }
        $xref = strlen($data); $data .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) { $data .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n"; }
        return $data . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
