<?php
/** Staging display-only copy corrections. Never edits the WordPress database. */
if (!defined('ABSPATH')) exit;
function sm_studio_clean_legacy_service_html($html) {
    if (!sm_studio_staging() || !is_page() || !is_main_query() || !in_the_loop()) return $html;
    $post=get_post();
    if (!$post || !($post instanceof WP_Post)) return $html;
    $legal_ids=array();
    foreach (array('imprint','privacy') as $key) { $p=sm_studio_page($key); if ($p) $legal_ids[]=(int)$p->ID; }
    if (in_array((int)$post->ID,$legal_ids,true) || strpos($post->post_content,'[service_mira_quote]')!==false) return $html;
    if (!class_exists('DOMDocument')) return $html;
    $prior=libxml_use_internal_errors(true);
    $dom=new DOMDocument('1.0','UTF-8');
    $ok=$dom->loadHTML('<?xml encoding="utf-8"?><div id="mira-copy-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    if (!$ok) { libxml_clear_errors(); libxml_use_internal_errors($prior); return $html; }
    $root=$dom->getElementById('mira-copy-root');
    if (!$root) { libxml_clear_errors(); libxml_use_internal_errors($prior); return $html; }
    $xpath=new DOMXPath($dom);
    $replacement='Unsere Unterhaltsreinigung berechnen wir mit 20,00 € pro Stunde zzgl. 19 % MwSt. Wir sorgen zuverlässig für saubere und gepflegte Räumlichkeiten. Zu unseren Leistungen gehören unter anderem die Reinigung von Böden, Waschbecken, Türen, Sanitärbereichen, Küchen sowie Tischen und Stühlen. Etwaige Anfahrtskosten werden separat berechnet.';
    // The approved copy may already be saved in WordPress. Never inject a second copy.
    $approved_copy_already_present = strpos(preg_replace('/\s+/u', ' ', $root->textContent), $replacement) !== false;
    // Replace only legacy pricing text, keeping existing approved content intact.
    foreach ($xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::p or self::strong or self::u]', $root) as $node) {
        $text=trim(preg_replace('/\s+/u',' ',$node->textContent));
        if (preg_match('/Unterhalt(?:s)?reinigung\s*[-–]?\s*preise/iu',$text) && mb_strlen($text,'UTF-8') < 90) {
            $node->textContent='Unterhaltsreinigung – Preise';
            $parent=$node;
            if (in_array(strtolower($parent->nodeName),array('strong','u'))) $parent=$parent->parentNode;
            $cursor=$parent->nextSibling;
            $steps=0;
            while ($cursor && $steps++<8) {
                $next=$cursor->nextSibling;
                if ($cursor->nodeType===XML_ELEMENT_NODE && preg_match('/^h[1-6]$/i',$cursor->nodeName)) break;
                if ($cursor->nodeType===XML_ELEMENT_NODE && in_array(strtolower($cursor->nodeName),array('p','div','blockquote'))) {
                    $body=trim(preg_replace('/\s+/u',' ',$cursor->textContent));
                    if (preg_match('/17\s*[,\.]\s*50|18\s*[,\.]\s*50|Kosten dem Unternehmen|Reinigungsfirma zu Reinigungsfirma|100 und 200 Quadratmeter|Späslitat|Nievo/iu',$body)) $parent->parentNode->removeChild($cursor);
                    else break;
                }
                $cursor=$next;
            }
            if (!$approved_copy_already_present) {
                $para=$dom->createElement('p'); $para->appendChild($dom->createTextNode($replacement));
                if ($parent->nextSibling) $parent->parentNode->insertBefore($para,$parent->nextSibling); else $parent->parentNode->appendChild($para);
            }
            break;
        }
    }
    foreach ($xpath->query('.//text()',$root) as $n) {
        $v=$n->nodeValue;
        $v=preg_replace('/\bUnterhaltreinigung\b/u','Unterhaltsreinigung',$v);
        $v=str_replace(array('Späslitat','Nievo','Amaturen','Späsialität','Niveo'),array('Spezialität','Niveau','Armaturen','Spezialität','Niveau'),$v);
        // Only convert explicit legacy *hourly* cleaning rates; preserve other amounts and travel tariffs.
        $v=preg_replace('/(?:€\s*17[,.]50|17[,.]50\s*€|€\s*18[,.]50|18[,.]50\s*€|€\s*18[,.]00|18[,.]00\s*€)(?=\s*(?:\/\s*h|\/\s*Std|pro\s*Std|pro\s*Stunde|je\s*Stunde|\-|–))/iu','20,00 €',$v);
        $v=preg_replace('/(?:zwischen\s*)?€\s*17[,.]50\s*[-–]\s*€\s*18[,.]50/iu','20,00 € pro Stunde zzgl. 19 % MwSt.',$v);
        $n->nodeValue=$v;
    }
    $out=''; foreach ($root->childNodes as $child) $out.=$dom->saveHTML($child);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    return $out;
}
add_filter('the_content','sm_studio_clean_legacy_service_html',90);
