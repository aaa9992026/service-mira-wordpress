<?php
defined('ABSPATH') || exit;
/** OpenRouteService / HeiGIT API: server-side geocoding and separately routed driving legs. */
final class Mira_Routes {
    public const ORIGIN = 'Tannenbergstraße 7, 28211 Bremen, Germany';
    private const API = 'https://api.heigit.org';
    private static function api_key(): string {
        if (defined('MIRA_ORS_API_KEY') && is_string(MIRA_ORS_API_KEY) && trim(MIRA_ORS_API_KEY) !== '') {
            return trim(MIRA_ORS_API_KEY); // Hosting configuration takes precedence.
        }
        $stored = get_option('mira_ors_server_api_key', '');
        return is_string($stored) ? trim($stored) : '';
    }
    public static function configured(): bool {
        return strlen(self::api_key()) >= 12;
    }
    private static function reserve_request(): void {
        $limit = defined('MIRA_ORS_MAX_REQUESTS_PER_DAY') ? (int) MIRA_ORS_MAX_REQUESTS_PER_DAY : 30;
        $limit = max(0, min(1000, $limit));
        $key = 'mira_ors_daily_' . gmdate('Ymd');
        $used = (int) get_option($key, 0);
        if ($used >= $limit) throw new RuntimeException('Lokales Tageslimit für OpenRouteService erreicht (UTC). Bitte nach dem nächsten UTC-Tageswechsel erneut testen; keine Fahrstrecke oder Preisfreigabe.');
        // Best effort cap (not atomic): also enforce provider-side quotas.
        update_option($key, $used + 1, false);
    }
    private static function request(string $url, ?array $body = null): array {
        if (!self::configured()) throw new RuntimeException('OpenRouteService API-Schlüssel fehlt.');
        self::reserve_request();
        $args = ['timeout'=>20, 'redirection'=>0, 'headers'=>[
            'Authorization'=>self::api_key(), 'Accept'=>'application/json',
            'Content-Type'=>'application/json',
        ]];
        if ($body === null) $response = wp_remote_get($url, $args);
        else { $args['body'] = wp_json_encode($body); $response = wp_remote_post($url, $args); }
        if (is_wp_error($response)) {
            // Safe administrator diagnostic: never disclose the URL, submitted address or API key.
            $stage = str_contains($url, '/pelias/') ? 'Adresssuche' : 'Fahrtroute';
            $error_code = (string) $response->get_error_code();
            $category = match (true) {
                str_contains($error_code, 'timeout') => 'Zeitüberschreitung',
                str_contains($error_code, 'resolve'), str_contains($error_code, 'dns') => 'DNS-Auflösung',
                str_contains($error_code, 'ssl'), str_contains($error_code, 'certificate') => 'TLS/Zertifikat',
                default => 'Netzwerk/Verbindung',
            };
            throw new RuntimeException('OpenRouteService-Verbindung fehlgeschlagen (' . $stage . '; ' . $category . '; WP-Code: ' . sanitize_key($error_code) . '). Bitte Hosting-Netzwerk und externe HTTPS-Anfragen prüfen.');
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $stage = str_contains($url, '/pelias/') ? 'Adresssuche' : 'Fahrtroute';
            throw new RuntimeException('OpenRouteService HTTP ' . $code . ' (' . $stage . '). Bei 429 API-Kontingent prüfen; bei 401/403 Schlüssel/Berechtigung; bei 5xx später erneut prüfen.');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) throw new RuntimeException('Ungültige Antwort des Routingdienstes.');
        return $data;
    }
    /** Normalize provider street names for comparison without weakening house-number/ZIP checks. */
    private static function street_token(string $value): string {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['straße', 'strasse', 'str.', 'str ', 'ß'], ['str', 'str', 'str', 'str', 'ss'], $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
        return is_string($value) ? $value : '';
    }
    /**
     * Secondary postcode evidence from the European Commission GISCO Address API.
     * No automatic acceptance of fuzzy matches, missing fields or remote failures.
     * This is only used for Pelias coordinates whose street and house already match.
     */
    private static function gisco_confirm(string $street_raw, string $house, string $postcode, string $city, array $pelias_coords): array {
        $status = 'keine Übereinstimmung';
        $url = 'https://gisco-services.ec.europa.eu/addressapi/search?' . http_build_query([
            'country'=>'DE', 'city'=>$city, 'road'=>$street_raw,
            'housenumber'=>$house, 'postcode'=>$postcode,
        ], '', '&', PHP_QUERY_RFC3986);
        $response = wp_remote_get($url, [
            'timeout'=>12, 'redirection'=>0,
            'headers'=>['Accept'=>'application/json'],
            'limit_response_size'=>262144,
        ]);
        if (is_wp_error($response)) return [[], 'Dienst nicht erreichbar'];
        if ((int) wp_remote_retrieve_response_code($response) !== 200) return [[], 'Dienst antwortet nicht mit HTTP 200'];
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return [[], 'Antwortformat ungültig'];
        // GISCO /search can return a root JSON array of address records, not only
        // GeoJSON FeatureCollection. Neither format is proof of a valid address.
        // Every returned record must still pass all independent exact-field checks.
        $features = $data['features'] ?? null;
        if (!is_array($features) && array_is_list($data)) $features = $data;
        if (!is_array($features) && isset($data['results']) && is_array($data['results'])) $features = $data['results'];
        if (!is_array($features)) return [[], 'Unbekanntes JSON-Ergebnisformat'];
        if (count($features) === 0) return [[], 'Keine Adressdatensätze zurückgegeben'];
        $matches = [];
        $stats = ['datensaetze'=>0, 'land'=>0, 'plz_vorhanden'=>0, 'plz'=>0,
            'haus'=>0, 'strasse'=>0, 'alle'=>0, 'koordinaten'=>0, 'naehe'=>0];
        foreach (array_slice($features, 0, 100) as $feature) {
            if (!is_array($feature)) continue;
            $props = isset($feature['properties']) ? $feature['properties'] : $feature;
            if (!is_array($props)) continue;
            $stats['datensaetze']++;
            $code = trim((string)($props['PC'] ?? ''));
            $number = mb_strtolower(trim((string)($props['LD'] ?? '')), 'UTF-8');
            $street = self::street_token((string)($props['TF'] ?? ''));
            $country = mb_strtoupper(trim((string)($props['L0'] ?? '')), 'UTF-8');
            if ($country === 'DE') $stats['land']++;
            if ($code !== '') $stats['plz_vorhanden']++;
            if (hash_equals($postcode, $code)) $stats['plz']++;
            if (hash_equals($house, $number)) $stats['haus']++;
            if (hash_equals(self::street_token($street_raw), $street)) $stats['strasse']++;
            if ($country !== 'DE' || !hash_equals($postcode, $code) ||
                !hash_equals($house, $number) || !hash_equals(self::street_token($street_raw), $street)) continue;
            $stats['alle']++;
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coords) || count($coords) < 2) {
                // The Address API documents XY and X/Y coordinates for search records.
                $xy = $props['XY'] ?? null;
                if (is_array($xy) && count($xy) >= 2) $coords = array_values($xy);
                elseif (is_string($xy) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[,; ]\s*(-?\d+(?:\.\d+)?)\s*$/', $xy, $m)) $coords = [$m[1], $m[2]];
                else $coords = [$props['X'] ?? null, $props['Y'] ?? null];
            }
            if (!is_numeric($coords[0] ?? null) || !is_numeric($coords[1] ?? null)) continue;
            $lon = (float)$coords[0]; $lat = (float)$coords[1];
            if ($lat < 47 || $lat > 56 || $lon < 5 || $lon > 16) continue;
            $stats['koordinaten']++;
            // Independent provider must locate the SAME house, not merely a matching label.
            foreach ($pelias_coords as $reference) {
                $dy = ($lat - $reference[1]) * 111320;
                $dx = ($lon - $reference[0]) * 111320 * cos(deg2rad($lat));
                if (hypot($dx, $dy) > 30) continue;
                $stats['naehe']++;
                $matches[implode(',', [round($reference[0], 5), round($reference[1], 5)])] = $reference;
            }
        }
        if (count($matches) === 1) $status = 'Hausadresse inklusive PLZ bestätigt';
        elseif (count($matches) > 1) $status = 'mehrdeutige Koordinaten';
        else $status = sprintf('Treffer %d; DE %d; PLZ vorhanden %d/passend %d; Hausnummer passend %d; Straße passend %d; alle Felder %d; Koordinaten gültig %d; Nähe bis 30m %d',
            $stats['datensaetze'], $stats['land'], $stats['plz_vorhanden'], $stats['plz'],
            $stats['haus'], $stats['strasse'], $stats['alle'], $stats['koordinaten'], $stats['naehe']);
        return [$matches, $status];
    }
    private static function geocode(string $address): array {
        $address = trim($address);
        if (mb_strlen($address) < 8 || mb_strlen($address) > 300) throw new InvalidArgumentException('Vollständige Adresse erforderlich.');
        // Require the complete street + building number + German postcode before geocoding.
        if (!preg_match('/^\s*(.+?)\s+(\d+[a-zA-Z]?)\s*,\s*(\d{5})\s+([^,]+)(?:,.*)?$/u', $address, $parts)) {
            throw new InvalidArgumentException('Bitte Straße, Hausnummer, fünfstellige PLZ und Ort eingeben.');
        }
        $street = self::street_token($parts[1]);
        $house = mb_strtolower($parts[2], 'UTF-8');
        $postcode = $parts[3];
        $key = 'mira_ors_geo_v4_' . hash_hmac('sha256', mb_strtolower($address), wp_salt('auth'));
        $cached = get_transient($key);
        if (is_array($cached) && count($cached) === 2) return $cached;
        // Cache only a confirmed no-match outcome briefly to avoid consuming repeated API
        // requests during manual staging tests. Never cache transport, quota, or server errors.
        $failure_key = $key . '_no_match';
        if (get_transient($failure_key) === '1') {
            throw new RuntimeException('Adresse kürzlich ohne eindeutig bestätigte Postleitzahl geprüft. Erneute Prüfung nach 20 Minuten möglich; keine Preisfreigabe.');
        }
        // A free-text search can rank unrelated addresses above the requested postcode.
        // Use Pelias structured search as a further attempt; never accept an unverified hit.
        $locality = trim(preg_replace('/\s*,.*$/u', '', trim($parts[4])));
        $queries = [
            ['endpoint'=>'search', 'params'=>['text'=>$address, 'size'=>20, 'boundary.country'=>'DE']],
            ['endpoint'=>'search', 'params'=>['text'=>$parts[1] . ' ' . $parts[2] . ', ' . $parts[3] . ' ' . $locality, 'size'=>20, 'boundary.country'=>'DE', 'layers'=>'address']],
            ['endpoint'=>'search/structured', 'params'=>['address'=>$parts[1] . ' ' . $parts[2], 'postalcode'=>$postcode, 'locality'=>$locality, 'country'=>'Germany', 'size'=>20]],
        ];
        $found = 0;
        $structured_checked = false;
        $checks = ['plz' => 0, 'hausnummer' => 0, 'strasse' => 0, 'koordinaten' => 0, 'plz_vorhanden' => 0, 'hausnummer_vorhanden' => 0, 'strasse_vorhanden' => 0, 'alle_felder_passend' => 0];
        $candidates = [];
        $missing_postcode_candidates = [];
        $reverse_attempts = 0;
        $reverse_confirmed = 0;
        $secondary_status = 'nicht benötigt';
        foreach ($queries as $query) {
            if ($query['endpoint'] === 'search/structured') $structured_checked = true;
            $result = self::request(self::API . '/pelias/v1/' . $query['endpoint'] . '?' . http_build_query($query['params'], '', '&', PHP_QUERY_RFC3986));
            foreach (($result['features'] ?? []) as $feature) {
                $p = $feature['properties'] ?? [];
                $geo = $feature['geometry']['coordinates'] ?? null;
                if (($p['layer'] ?? '') !== 'address' || !is_array($geo) || count($geo) < 2) continue;
                $found++;
                // Check the three components independently. Earlier diagnostic counters
                // were conditional on postcode matching, so zero street/number matches
                // could be misleading when Pelias supplied a different postcode.
                $provider_postcode = trim((string)($p['postalcode'] ?? ''));
                $provider_house = mb_strtolower(trim((string)($p['housenumber'] ?? '')), 'UTF-8');
                $provider_street = self::street_token((string)($p['street'] ?? ''));
                if ($provider_postcode !== '') $checks['plz_vorhanden']++;
                if ($provider_house !== '') $checks['hausnummer_vorhanden']++;
                if ($provider_street !== '') $checks['strasse_vorhanden']++;
                $zip_match = hash_equals($postcode, $provider_postcode);
                $house_match = $provider_house !== '' && hash_equals($house, $provider_house);
                $street_match = $provider_street !== '' && hash_equals($street, $provider_street);
                if ($zip_match) $checks['plz']++;
                if ($house_match) $checks['hausnummer']++;
                if ($street_match) $checks['strasse']++;
                // Pelias may return the correct house and street while omitting its postcode.
                // Hold the candidate for a strict reverse-geocoding confirmation; do not
                // accept or price it based on the forward result alone.
                if ($house_match && $street_match && $provider_postcode === '' &&
                    is_numeric($geo[0]) && is_numeric($geo[1]) &&
                    (float)$geo[1] >= 47 && (float)$geo[1] <= 56 &&
                    (float)$geo[0] >= 5 && (float)$geo[0] <= 16) {
                    $coord_key = implode(',', [round((float)$geo[0], 6), round((float)$geo[1], 6)]);
                    $missing_postcode_candidates[$coord_key] = [(float)$geo[0], (float)$geo[1]];
                }
                if (!$zip_match || !$house_match || !$street_match) continue;
                $checks['alle_felder_passend']++;
                $lon = $geo[0]; $lat = $geo[1];
                if (!is_numeric($lon) || !is_numeric($lat) || (float)$lat < 47 || (float)$lat > 56 || (float)$lon < 5 || (float)$lon > 16) continue;
                $checks['koordinaten']++;
                $coords = [(float)$lon, (float)$lat];
                $candidates[implode(',', [round($coords[0], 5), round($coords[1], 5)])] = $coords;
            }
            if (count($candidates) === 1) break;
        }
        // Use the same authorized geocoding provider to independently check an exact
        // postcode omitted from otherwise matching house-address results. Reverse results
        // must explicitly confirm ALL three requested components. Never infer a postcode
        // from city, proximity, or a search query echoed in a result label.
        if (count($candidates) === 0 && count($missing_postcode_candidates) > 0) {
            foreach (array_slice($missing_postcode_candidates, 0, 5) as $coord) {
                $reverse_attempts++;
                $reverse = self::request(self::API . '/pelias/v1/reverse?' . http_build_query([
                    'point.lat'=>$coord[1], 'point.lon'=>$coord[0],
                    'size'=>10, 'layers'=>'address',
                ], '', '&', PHP_QUERY_RFC3986));
                foreach (($reverse['features'] ?? []) as $feature) {
                    $rp = $feature['properties'] ?? [];
                    $reverse_geo = $feature['geometry']['coordinates'] ?? null;
                    if (($rp['layer'] ?? '') !== 'address' || !is_array($reverse_geo) || count($reverse_geo) < 2) continue;
                    if (!hash_equals($postcode, trim((string)($rp['postalcode'] ?? ''))) ||
                        !hash_equals($house, mb_strtolower(trim((string)($rp['housenumber'] ?? '')), 'UTF-8')) ||
                        !hash_equals($street, self::street_token((string)($rp['street'] ?? '')))) continue;
                    if (!is_numeric($reverse_geo[0]) || !is_numeric($reverse_geo[1])) continue;
                    $rlon = (float)$reverse_geo[0]; $rlat = (float)$reverse_geo[1];
                    if ($rlat < 47 || $rlat > 56 || $rlon < 5 || $rlon > 16) continue;
                    // Confirmed location must be the same mapped house, within 25m.
                    $dy = ($rlat - $coord[1]) * 111320;
                    $dx = ($rlon - $coord[0]) * 111320 * cos(deg2rad($coord[1]));
                    if (hypot($dx, $dy) > 25) continue;
                    $reverse_confirmed++;
                    $candidates[implode(',', [round($rlon, 5), round($rlat, 5)])] = [$rlon, $rlat];
                }
            }
        }
        // Consult GISCO only if Pelias provides the correct house and street but no PLZ.
        // GISCO can confirm the requested postcode; require its coordinates to coincide
        // with that same Pelias house (30m) to avoid fuzzy-search false positives.
        if (count($candidates) === 0 && count($missing_postcode_candidates) > 0) {
            [$secondary_candidates, $secondary_status] = self::gisco_confirm(
                trim($parts[1]), $house, $postcode, $locality, array_values($missing_postcode_candidates)
            );
            $candidates = $secondary_candidates;
        }
        if (count($candidates) === 1) {
            $coords = reset($candidates);
            set_transient($key, $coords, DAY_IN_SECONDS);
            return $coords;
        }
        if (count($candidates) > 1) throw new RuntimeException('Mehrere passende Hausadressen gefunden. Keine automatische Preisfreigabe.');
        // Administrator-only, aggregated diagnostics: never print returned labels, addresses, coordinates or API credentials.
        $diagnosis = sprintf('Geocoding-Prüfung: Hausadress-Treffer %d; PLZ vorhanden %d / passend %d; Hausnummer vorhanden %d / passend %d; Straße vorhanden %d / passend %d; alle drei passend %d; Koordinaten gültig %d. Alle Komponenten unabhängig geprüft.',
            $found, $checks['plz_vorhanden'], $checks['plz'], $checks['hausnummer_vorhanden'], $checks['hausnummer'], $checks['strasse_vorhanden'], $checks['strasse'], $checks['alle_felder_passend'], $checks['koordinaten']);
        set_transient($failure_key, '1', 20 * MINUTE_IN_SECONDS);
        throw new RuntimeException(($found ? 'Keine eindeutig bestätigte Hausadresse. ' : 'Keine Hausadress-Treffer. ') . $diagnosis . ($structured_checked ? ' Strukturierte Suche ebenfalls geprüft.' : '') . ' PLZ-Nachprüfung: ' . $reverse_attempts . ' Rückwärtsabfragen; bestätigte Adressen: ' . $reverse_confirmed . '. GISCO: ' . $secondary_status . '.' . ' Keine Fahrstrecke oder Preisfreigabe.');
    }
    private static function leg(array $from, array $to): int {
        $key = 'mira_ors_leg_' . hash_hmac('sha256', wp_json_encode([$from,$to]), wp_salt('auth'));
        $cached = get_transient($key);
        if (is_int($cached) && $cached > 0) return $cached;
        $data = self::request(self::API . '/openrouteservice/v2/directions/driving-car/json', [
            'coordinates'=>[$from, $to], 'instructions'=>false,
        ]);
        $meters = $data['routes'][0]['summary']['distance'] ?? null;
        if (!is_numeric($meters) || !is_finite((float)$meters) || (float)$meters <= 0 || (float)$meters > 2000000) {
            throw new RuntimeException('Keine gültige Straßenroute gefunden.');
        }
        $distance = (int) round((float)$meters);
        set_transient($key, $distance, DAY_IN_SECONDS);
        return $distance;
    }
    public static function quote(string $destination, int $service_cents, int $vat_bps = 1900): array {
        $destination = trim($destination);
        if (mb_strlen($destination) < 8 || mb_strlen($destination) > 300 || $service_cents < 0 || $vat_bps !== 1900) {
            throw new InvalidArgumentException('Ungültige Angebots- oder Adressdaten.');
        }
        try { $start = self::geocode(self::ORIGIN); }
        catch (RuntimeException $e) { throw new RuntimeException('Startadresse: ' . $e->getMessage(), 0, $e); }
        try { $end = self::geocode($destination); }
        catch (RuntimeException $e) { throw new RuntimeException('Zieladresse: ' . $e->getMessage(), 0, $e); }
        $out = self::leg($start, $end);
        $back = self::leg($end, $start);
        $total = $out + $back;
        $travel = intdiv(max(0, $total - 10000) * 50 + 500, 1000);
        $net = $service_cents + $travel;
        $vat = intdiv($net * $vat_bps + 5000, 10000);
        return [
            'provider'=>'openrouteservice', 'origin'=>self::ORIGIN, 'destination'=>$destination,
            'outbound_m'=>$out, 'return_m'=>$back, 'roundtrip_m'=>$total, 'free_m'=>10000,
            'travel_cents'=>$travel, 'service_cents'=>$service_cents,
            'net_cents'=>$net, 'vat_cents'=>$vat, 'gross_cents'=>$net+$vat,
            'vat_bps'=>$vat_bps,
        ];
    }
}
