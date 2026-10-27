<?php
/**
 * Plugin Name: Service Mira Studio — Unified Site Design
 * Description: Opt-in modern editorial website experience that reuses existing pages, Media Library assets and the current quotation plugin. Safe for the /yamato/ staging site.
 * Version: 2.0.51
 * Author: Service Mira Website Studio
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: service-mira-studio
 */
if (!defined('ABSPATH')) { exit; }

define('SM_STUDIO_VERSION', '2.0.51');
define('SM_STUDIO_PATH', plugin_dir_path(__FILE__));
define('SM_STUDIO_URL', plugin_dir_url(__FILE__));

function sm_studio_staging() {
    $path = (string) wp_parse_url(site_url('/'), PHP_URL_PATH);
    return wp_get_environment_type() === 'staging' || (bool) preg_match('~(?:^|/)yamato(?:/|$)~i', $path);
}
function sm_studio_defaults() {
    return array('home_enabled'=>0,'inner_enabled'=>0,'hero_id'=>832,'logo_id'=>0,'quote_id'=>0,'contact_id'=>0,'prices_id'=>0,'before_id'=>0,'after_id'=>0,'footer_credit'=>1);
}
function sm_studio_settings() { return wp_parse_args((array) get_option('sm_studio_settings', array()), sm_studio_defaults()); }
function sm_studio_find_page($names) {
    $lower = function($value) { return function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtolower($value); };
    $names = array_map($lower, (array) $names);
    $pages = get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>200,'orderby'=>'menu_order title','order'=>'ASC'));
    foreach ($pages as $page) {
        if (in_array($lower(trim($page->post_title)), $names, true)) return $page;
    }
    return null;
}
function sm_studio_page($key) {
    $s=sm_studio_settings();
    $ids=array('quote'=>'quote_id','contact'=>'contact_id','prices'=>'prices_id');
    if (isset($ids[$key]) && !empty($s[$ids[$key]])) {
        $p=get_post((int)$s[$ids[$key]]);
        if ($p && $p->post_status==='publish' && $p->post_type==='page') return $p;
    }
    if ($key==='quote') {
        // Discover the existing shortcode; do not invent a new quotation endpoint.
        foreach (get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>200)) as $p) {
            if (strpos($p->post_content,'[service_mira_quote]') !== false) return $p;
        }
        return sm_studio_find_page(array('Angebot anfordern – Test','Angebot anfordern - Test','Angebot anfordern'));
    }
    $names = array(
        'contact'=>array('Kontakte','Kontakt'),
        'prices'=>array('Leistungen & Preise'),
        'privacy'=>array('Datenschutzerklärung','Datenschutz'),
        'imprint'=>array('Impressum'),
    );
    return sm_studio_find_page(isset($names[$key])?$names[$key]:array());
}
function sm_studio_services() {
    $labels = array('Büroreinigung','Unterhaltsreinigung','Grundreinigung','Treppenhaus','Praxisreinigung','Bauendreinigung','Gartenarbeit','Einkaufshilfe in Bremen');
    $out=array();
    foreach ($labels as $label) {
        $post=sm_studio_find_page($label);
        if ($post) $out[]=$post;
    }
    return $out;
}
function sm_studio_icon($name,$size=22) {
    $paths=array(
        'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'arrow-up'=>'<path d="M7 17 17 7M7 7h10v10"/>',
        'spray'=>'<path d="M6 6h8l2 4v10H6V6Z"/><path d="M8 6V3h4m-1 0h5V1m0 2h5M8 11h6"/>',
        'stairs'=>'<path d="M2 21h20v-5h-6v-5h-6V6H2v15Z"/>',
        'medical'=>'<path d="M9 2h6v7h7v6h-7v7H9v-7H2V9h7V2Z"/>',
        'hardhat'=>'<path d="M4 16a8 8 0 0 1 16 0M2 16h20v4H2v-4ZM12 8v8"/>',
        'leaf'=>'<path d="M20 4C8 4 4 10 4 16a4 4 0 0 0 4 4c8 0 12-8 12-16Z"/><path d="M4 20c3-5 7-8 13-12"/>',
        'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 12a3 3 0 1 0-1-6M17 14a5 5 0 0 1 4 5v1"/>',
        'file'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-12 4h2m4 0h2"/>',
        'sparkle'=>'<path d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2Zm7 13 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'droplet'=>'<path d="M12 2c-3 4.6-7 8.3-7 13a7 7 0 0 0 14 0c0-4.7-4-8.4-7-13Z"/><path d="M8.5 15a3.5 3.5 0 0 0 3.5 3.5"/>',
        'shield'=>'<path d="M12 22c5-2.5 8-6.2 8-12V5l-8-3-8 3v5c0 5.8 3 9.5 8 12Z"/><path d="m9 12 2 2 4-4"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 7 9-7"/>',
        'location'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'=>'<path d="M5 5l14 14M19 5 5 19"/>',
        'chevron'=>'<path d="m6 9 6 6 6-6"/>',
        'building'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h2m4 0h2M8 11h2m4 0h2M8 15h2m4 0h2M10 21v-3h4v3"/>',
        'wind'=>'<path d="M3 8h11a3 3 0 1 0-3-3M2 12h17a3 3 0 1 1-3 3M5 16h5a3 3 0 1 1-3 3"/>',
        'home'=>'<path d="m3 11 9-8 9 8v9H3v-9Z"/><path d="M9 20v-7h6v7"/>',
        'star'=>'<path d="m12 2 3.1 6.6 7.2 1-5.2 5.1 1.2 7.2-6.3-3.4-6.3 3.4 1.2-7.2-5.2-5.1 7.2-1L12 2Z"/>',
    );
    $content=isset($paths[$name])?$paths[$name]:$paths['sparkle'];
    // Width/height SVG attributes are presentation hints: legacy WordPress themes can override
    // them with svg { width: 100%; height: auto } rules, making decorative icons gigantic.
    // Inline important dimensions win even against aggressive theme-wide important rules.
    $size=max(12,min(128,(int)$size));
    $dimensions='width:'.$size.'px !important;height:'.$size.'px !important;min-width:'.$size.'px !important;min-height:'.$size.'px !important;max-width:'.$size.'px !important;max-height:'.$size.'px !important;flex:0 0 '.$size.'px !important;display:inline-block !important;vertical-align:middle;';
    return '<svg class="sm-studio-icon" style="'.$dimensions.'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$content.'</svg>';
}
/**
 * Select the largest safely available native version of an existing Media Library
 * image. WordPress can generate a reduced "-scaled" full-size attachment when the
 * original is larger than its configured big-image threshold. Using that scaled
 * derivative across a desktop banner can soften the photograph.
 *
 * This does not manipulate or replace any media files, and gracefully falls back
 * to WordPress full-size when the original isn't present on the server.
 */
function sm_studio_hero_source($attachment_id) {
    $attachment_id=absint($attachment_id);
    if (!$attachment_id || !wp_attachment_is_image($attachment_id)) return false;
    $full=wp_get_attachment_image_src($attachment_id,'full');
    if (!$full || empty($full[0])) return false;
    $source=array('url'=>$full[0],'width'=>(int)$full[1],'height'=>(int)$full[2],
      'is_original'=>false,'original_available'=>false,'full_width'=>(int)$full[1],
      'full_height'=>(int)$full[2]);
    if (function_exists('wp_get_original_image_url') && function_exists('wp_get_original_image_path')) {
        $original_url=wp_get_original_image_url($attachment_id);
        $original_path=wp_get_original_image_path($attachment_id);
        if ($original_url && $original_path && is_file($original_path)) {
            $size=@getimagesize($original_path);
            if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
                $orig_width=(int)$size[0]; $orig_height=(int)$size[1];
                $source['original_available']=true;
                $source['original_width']=$orig_width;
                $source['original_height']=$orig_height;
                // Prefer genuine larger pixels. Cap unnecessary transfer for already
                // high-resolution full derivatives (>= 2400 pixels wide).
                if ($orig_width>$source['width'] && $source['width']<2400) {
                    $source['url']=$original_url;
                    $source['width']=$orig_width;
                    $source['height']=$orig_height;
                    $source['is_original']=true;
                }
            }
        }
    }
    return $source;
}

function sm_studio_previewing() {
    return is_user_logged_in() && current_user_can('manage_options') && isset($_GET['sm_studio_preview'])
        && isset($_GET['_sm_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_sm_nonce'])),'sm_studio_preview');
}
function sm_studio_url($post=null) {
    $url = $post ? get_permalink($post) : home_url('/');
    if (sm_studio_previewing()) {
        $url=add_query_arg(array('sm_studio_preview'=>'1','_sm_nonce'=>wp_create_nonce('sm_studio_preview')),$url);
    }
    return $url;
}
function sm_studio_url_for($key) { $p=sm_studio_page($key); return $p ? sm_studio_url($p) : ''; }
function sm_studio_is_allowed_page($post) {
    if (!$post || !($post instanceof WP_Post) || $post->post_status !== 'publish' || $post->post_type !== 'page') return false;
    $ids=array();
    foreach (array('quote','contact','prices','imprint','privacy') as $key) {
        $found=sm_studio_page($key);
        if ($found) $ids[]=(int)$found->ID;
    }
    foreach (sm_studio_services() as $service) $ids[]=(int)$service->ID;
    foreach (array('Gebäudereinigung','Service Mira Gebäudereinigung in Bremen und Umgebung','Service Mira Einkaufshilfe in Bremen und Umgebung') as $title) {
        $found=sm_studio_find_page($title);
        if ($found) $ids[]=(int)$found->ID;
    }
    if (in_array((int)$post->ID,array_unique($ids),true)) return true;
    // Consistent presentation for all other PUBLIC standard WordPress pages opened
    // from original site links. Never override a page-builder template or protected page.
    if (!empty($post->post_password)) return false;
    $custom = get_page_template_slug($post->ID);
    if ($custom && $custom !== 'default') return false;
    if (get_post_meta($post->ID,'_elementor_edit_mode',true) === 'builder') return false;
    return true;
}
function sm_studio_page_opt($id,$value) { return selected((int)$id,(int)$value,false); }

add_action('admin_menu',function() {
    add_theme_page('Service Mira Studio','Service Mira Studio','manage_options','service-mira-studio','sm_studio_admin_page');
});
add_action('admin_init',function() {
    register_setting('sm_studio_group','sm_studio_settings',array('type'=>'array','sanitize_callback'=>'sm_studio_sanitize'));
});
function sm_studio_sanitize($input) {
    $old=sm_studio_settings();
    $out=$old;
    foreach(array('hero_id','logo_id','quote_id','contact_id','prices_id','before_id','after_id') as $key) {
        $out[$key]=isset($input[$key])?absint($input[$key]):0;
    }
    foreach(array('home_enabled','inner_enabled','footer_credit') as $key) {
        $out[$key]=empty($input[$key])?0:1;
    }
    // Zero unintended possibility of changing production site through this plugin's switches.
    if (!sm_studio_staging()) { $out['home_enabled']=0; $out['inner_enabled']=0; }
    return $out;
}
function sm_studio_admin_select($key,$name) {
    $pages=get_posts(array('post_type'=>'page','post_status'=>'publish','numberposts'=>200,'orderby'=>'title','order'=>'ASC'));
    $s=sm_studio_settings();
    echo '<label class="sm-admin-field" style="display:grid;grid-template-columns:minmax(140px,190px) minmax(0,1fr);align-items:center;gap:14px;max-width:750px;margin:0 0 12px"><strong>'.esc_html($name).'</strong><select style="width:100%;min-width:0;max-width:100%" name="sm_studio_settings['.esc_attr($key).']"><option value="0">Automatisch erkennen</option>';
    foreach($pages as $page) echo '<option value="'.esc_attr($page->ID).'" '.sm_studio_page_opt($page->ID,$s[$key]).'>'.esc_html($page->post_title).'</option>';
    echo '</select></label>';
}
function sm_studio_admin_page() {
    if (!current_user_can('manage_options')) return;
    $s=sm_studio_settings();
    $preview=wp_nonce_url(add_query_arg('sm_studio_preview',1,home_url('/')),'sm_studio_preview','_sm_nonce');
    $staging=sm_studio_staging();
    ?>
    <div class="wrap" style="max-width:940px"><h1>Service Mira Studio — Sitewide Design v<?php echo esc_html(SM_STUDIO_VERSION); ?></h1>
    <p>Eigenständige Gestaltung. Originaltheme, Seiten, Beiträge, Preise und die bestehende Angebotsfunktion bleiben unangetastet.</p>
    <div class="notice <?php echo $staging?'notice-success':'notice-error'; ?>"><p><strong><?php echo $staging?'Staging erkannt.':'Sicherheitsmodus: keine Aktivierung außerhalb Staging.'; ?></strong> Live-Website wird durch dieses Plugin nicht verändert.</p></div>
    <p><a class="button button-primary" href="<?php echo esc_url($preview); ?>" target="_blank" rel="noopener">Private Designvorschau öffnen</a></p>
    <form method="post" action="options.php">
    <?php settings_fields('sm_studio_group'); ?>
    <table class="form-table"><tbody>
    <tr><th scope="row">Bestehendes Hauptfoto</th><td><input type="number" min="0" name="sm_studio_settings[hero_id]" value="<?php echo esc_attr($s['hero_id']); ?>"/> <p class="description">Mediathek-ID des Startseitenfotos (aktuell ist das neue, vom Kunden gewählte Foto ID 832).</p><?php if ($s['hero_id']) {
        echo wp_get_attachment_image($s['hero_id'],'medium',false,array('style'=>'max-width:330px;height:auto;display:block;margin-top:10px'));
        $hero_info=sm_studio_hero_source($s['hero_id']);
        if ($hero_info) {
            echo '<p class="description"><strong>Verwendete Bildauflösung: '.esc_html($hero_info['width']).' × '.esc_html($hero_info['height']).' Pixel</strong> ('.($hero_info['is_original']?'größeres Originalbild':'WordPress Vollbild').').</p>';
            if ($hero_info['width']<1800) {
                echo '<p class="notice notice-warning inline" style="padding:9px 12px;max-width:620px">Das Foto ist für einen großen Desktop-Banner möglicherweise zu klein. Für ein scharfes Ergebnis verwenden Sie möglichst ein echtes Original mit mindestens 1800–2400 Pixel Breite. CSS kann fehlende Bilddetails nicht wiederherstellen.</p>';
            }
        } else {
            echo '<p class="notice notice-warning inline" style="padding:9px 12px;max-width:620px">Bilddatei nicht verfügbar. Bitte eine gültige Mediathek-ID wählen.</p>';
        }
    } ?></td></tr>
    <tr><th scope="row">Bestehendes Logo (optional)</th><td><input type="number" min="0" name="sm_studio_settings[logo_id]" value="<?php echo esc_attr($s['logo_id']); ?>"/> <p class="description">0 = Theme Custom Logo oder neutrales Tropfen-Icon.</p></td></tr>
    <tr><th scope="row">Bildvergleich (optional)</th><td>
      <p>Nur echte, rechtmäßig verwendbare Vorher-/Nachher-Fotos eintragen. Ohne beide Bilder wird der Abschnitt als illustrative Fotoansicht mit entsprechendem Hinweis angezeigt.</p>
      <label>Vorher-Mediathek-ID <input type="number" min="0" name="sm_studio_settings[before_id]" value="<?php echo esc_attr(isset($s['before_id'])?$s['before_id']:0); ?>" /></label><br><br>
      <label>Nachher-Mediathek-ID <input type="number" min="0" name="sm_studio_settings[after_id]" value="<?php echo esc_attr(isset($s['after_id'])?$s['after_id']:0); ?>" /></label>
    </td></tr>
    <tr><th scope="row">Seiten auswählen</th><td>
    <?php sm_studio_admin_select('quote_id','Angebot / Rechner'); sm_studio_admin_select('contact_id','Kontakt'); sm_studio_admin_select('prices_id','Leistungen & Preise'); ?>
    </td></tr>
    <tr><th scope="row">Design einschalten</th><td>
    <label><input type="checkbox" name="sm_studio_settings[home_enabled]" value="1" <?php checked($s['home_enabled'],1); disabled(!$staging); ?>/> Neue Startseite auf Staging anzeigen</label><br><br>
    <label><input type="checkbox" name="sm_studio_settings[inner_enabled]" value="1" <?php checked($s['inner_enabled'],1); disabled(!$staging); ?>/> Standard-Seiten einheitlich gestalten (inkl. Rechner; sichere Seitentypen)</label>
    <p class="description">Zuerst nur privat testen. Beide Schalter sind nach Erstinstallation deaktiviert.</p></td></tr>
    <tr><th scope="row">Footer-Credit</th><td><label><input type="checkbox" name="sm_studio_settings[footer_credit]" value="1" <?php checked($s['footer_credit'],1); ?>/> Designed by Pointnova anzeigen</label></td></tr>
    </tbody></table><?php submit_button('Einstellungen speichern'); ?>
    </form>
    <hr><h2>Kompatibilität &amp; Rückgängig</h2>
    <p>Der Entwurf nutzt eigene Templates ohne Änderungen an Store Shopline oder Shopline. Um alle Änderungen sofort rückgängig zu machen, beide Schalter ausschalten oder dieses Plugin deaktivieren.</p>
    <p>Der Angebotsrechner wird nicht nachgebildet: Vorhandene <code>[service_mira_quote]</code>-Einbindung bleibt erhalten. E-Mail- und Annahme-Einstellungen werden nicht berührt.</p>
    </div><?php
}
add_action('template_redirect', function() { if (sm_studio_previewing()) { nocache_headers(); } }, 1);
add_filter('template_include',function($template) {
    if (is_admin() || is_feed() || is_embed() || is_preview() && !sm_studio_previewing()) return $template;
    if (!sm_studio_staging()) return $template;
    $s=sm_studio_settings();
    $preview=sm_studio_previewing();
    if ((is_front_page() || is_home()) && ($preview || $s['home_enabled'])) return SM_STUDIO_PATH.'templates/home.php';
    if (is_page() && ($preview || $s['inner_enabled'])) {
        $p=get_queried_object();
        if ($p && (sm_studio_previewing() || sm_studio_is_allowed_page($p))) return SM_STUDIO_PATH.'templates/page.php';
    }
    return $template;
}, 99);
add_action('wp_enqueue_scripts',function() {
    if (!sm_studio_staging() || !(is_front_page() || is_home() || is_page())) return;
    $s=sm_studio_settings();
    if (!(sm_studio_previewing() || $s['home_enabled'] || $s['inner_enabled'])) return;
    wp_enqueue_style('sm-studio-style',SM_STUDIO_URL.'assets/site.css',array(),(string)filemtime(SM_STUDIO_PATH.'assets/site.css'));
    wp_enqueue_script('sm-studio-js',SM_STUDIO_URL.'assets/site.js',array(),(string)filemtime(SM_STUDIO_PATH.'assets/site.js'),true);
    // The contact page now renders from the WP source in PHP; no JS DOM replacement.
    wp_enqueue_style('sm-studio-bright',SM_STUDIO_URL.'assets/bright-edition.css',array('sm-studio-style'),(string)filemtime(SM_STUDIO_PATH.'assets/bright-edition.css'));
    if (is_front_page() || is_home()) wp_enqueue_script('sm-studio-bright-js',SM_STUDIO_URL.'assets/bright-edition.js',array(),(string)filemtime(SM_STUDIO_PATH.'assets/bright-edition.js'),true);
    // Pricing presentation only; the WordPress page content and values are never modified.
    $prices_page = sm_studio_page('prices');
    if (is_page() && $prices_page instanceof WP_Post && is_page((int)$prices_page->ID)) {
        wp_enqueue_style('sm-studio-prices-polish',SM_STUDIO_URL.'assets/prices-polish.css',array('sm-studio-bright'),(string)filemtime(SM_STUDIO_PATH.'assets/prices-polish.css'));
        wp_enqueue_script('sm-studio-prices-polish',SM_STUDIO_URL.'assets/prices-polish.js',array('sm-studio-js'),(string)filemtime(SM_STUDIO_PATH.'assets/prices-polish.js'),true);
    }
}, 100);
function sm_studio_body_start($page_type='home') {
    echo '<!doctype html><html '.get_language_attributes().'><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1">';
    wp_head();
    echo '</head><body ';
    body_class('sm-studio-body sm-page-'.$page_type);
    echo '><a class="sm-skip" href="#content">Zum Inhalt springen</a>';
    wp_body_open();
}
function sm_studio_logo() {
    $s=sm_studio_settings();
    $logo=$s['logo_id']?:get_theme_mod('custom_logo');
    if ($logo && wp_get_attachment_image_url($logo,'thumbnail')) return wp_get_attachment_image($logo,'thumbnail',false,array('class'=>'sm-brand-img','alt'=>'Service Mira'));
    return '<img class="sm-brand-img" src="'.esc_url(SM_STUDIO_URL.'assets/service-mira-drop.png').'" width="44" height="48" alt="Service Mira Tropfen-Symbol">';
}
function sm_studio_header() {
    $prices_post=sm_studio_page('prices');
    $contact_post=sm_studio_page('contact');
    $quote_post=sm_studio_page('quote');
    $prices=sm_studio_url_for('prices'); $contact=sm_studio_url_for('contact'); $quote=sm_studio_url_for('quote');
    $on_home=is_front_page() || is_home();
    $on_prices=$prices_post && is_page($prices_post->ID);
    $on_contact=$contact_post && is_page($contact_post->ID);
    $on_quote=$quote_post && is_page($quote_post->ID);
    $service_ids = array_map(function($p) { return (int)$p->ID; }, sm_studio_services());
    $queried = get_queried_object_id();
    $on_service = in_array((int)$queried,$service_ids,true);
    // Read the WordPress queried page so the active link works on preview URLs, too.
    $services_link=$on_home ? '#leistungen' : sm_studio_url();
    ?>
    <header class="sm-header"><div class="sm-shell sm-header-inner">
      <a class="sm-brand" href="<?php echo esc_url(sm_studio_url()); ?>" aria-label="Service Mira – Startseite">
        <?php echo sm_studio_logo(); ?><span class="sm-brand-text"><strong>Service Mira</strong><span>Gebäudereinigung <small>Bremen &amp; Umgebung</small></span></span>
      </a>
      <button type="button" class="sm-mobile-toggle" aria-expanded="false" aria-controls="sm-main-nav" aria-label="Menü öffnen"><span class="sm-toggle-symbol" aria-hidden="true"></span><span>Menü</span></button>
      <nav class="sm-nav" id="sm-main-nav" aria-label="Hauptnavigation">
        <a class="sm-nav-link<?php echo $on_home ? ' is-active' : ''; ?>"<?php if ($on_home): ?> aria-current="page"<?php endif; ?> href="<?php echo esc_url(sm_studio_url()); ?>">Startseite</a>
        <?php if ($prices): ?><a class="sm-nav-link<?php echo ($on_prices || $on_service) ? ' is-active' : ''; ?>"<?php if ($on_prices): ?> aria-current="page"<?php endif; ?> href="<?php echo esc_url($prices); ?>">Leistungen &amp; Preise</a>
        <?php else: ?><a class="sm-nav-link" href="<?php echo esc_url($services_link); ?>">Leistungen</a><?php endif; ?>
        <?php if ($contact): ?><a class="sm-nav-link<?php echo $on_contact ? ' is-active' : ''; ?>"<?php if ($on_contact): ?> aria-current="page"<?php endif; ?> href="<?php echo esc_url($contact); ?>">Kontakt</a><?php endif; ?>
        <?php if ($quote): ?><a class="sm-button sm-button-primary sm-nav-cta<?php echo $on_quote ? ' is-active' : ''; ?>"<?php if ($on_quote): ?> aria-current="page"<?php endif; ?> href="<?php echo esc_url($quote); ?>">Angebot anfordern</a><?php endif; ?>
      </nav>
    </div></header>
    <?php
    // Legal pages are available in the footer; no redundant legal subnavigation.

}
function sm_studio_footer() {
    $contact=sm_studio_url_for('contact'); $prices=sm_studio_url_for('prices'); $quote=sm_studio_url_for('quote');
    $imprint=sm_studio_url_for('imprint'); $privacy=sm_studio_url_for('privacy'); $s=sm_studio_settings();
    $current=(int)get_queried_object_id();
    $is_current=function($page) use ($current) { return $page instanceof WP_Post && (int)$page->ID === $current; };
    $current_imprint=$is_current(sm_studio_page('imprint'));
    $current_privacy=$is_current(sm_studio_page('privacy'));
    $current_contact=$is_current(sm_studio_page('contact'));
    $current_prices=$is_current(sm_studio_page('prices'));
    $current_quote=$is_current(sm_studio_page('quote'));
    ?>
    <footer class="sm-footer" id="footer"><div class="sm-shell">
       <div class="sm-footer-top"><div class="sm-footer-brand">
           <a class="sm-brand" href="<?php echo esc_url(sm_studio_url()); ?>"><?php echo sm_studio_logo(); ?><span class="sm-brand-text"><strong>Service Mira</strong><span>Gebäudereinigung</span></span></a>
           <p>Reinigungsservice in Bremen und Umgebung – für Räume, in denen Sie sich wohlfühlen können.</p>
           <a class="sm-mail-link" href="mailto:inf@service-mira.de"><?php echo sm_studio_icon('mail',18); ?> inf@service-mira.de</a>
       </div>
       <nav class="sm-footer-column" aria-label="Dienstleistungen"><h3>Leistungen</h3>
         <?php foreach(array_slice(sm_studio_services(),0,6) as $service): ?><a href="<?php echo esc_url(sm_studio_url($service)); ?>"<?php if ($is_current($service)): ?> class="is-active" aria-current="page"<?php endif; ?>><?php echo esc_html($service->post_title); ?></a><?php endforeach; ?>
       </nav>
       <nav class="sm-footer-column" aria-label="Website und rechtliche Hinweise"><h3>Informationen</h3>
          <a href="<?php echo esc_url(sm_studio_url()); ?>"<?php if (is_front_page() || is_home()): ?> class="is-active" aria-current="page"<?php endif; ?>>Startseite</a>
          <?php if ($prices): ?><a href="<?php echo esc_url($prices); ?>"<?php if ($current_prices): ?> class="is-active" aria-current="page"<?php endif; ?>>Leistungen &amp; Preise</a><?php endif; ?>
          <?php if ($contact): ?><a href="<?php echo esc_url($contact); ?>"<?php if ($current_contact): ?> class="is-active" aria-current="page"<?php endif; ?>>Kontakt</a><?php endif; ?>
          <?php if ($quote): ?><a href="<?php echo esc_url($quote); ?>"<?php if ($current_quote): ?> class="is-active" aria-current="page"<?php endif; ?>>Angebot anfragen</a><?php endif; ?>
          <?php if ($imprint): ?><a href="<?php echo esc_url($imprint); ?>"<?php if ($current_imprint): ?> class="is-active" aria-current="page"<?php endif; ?>>Impressum</a><?php endif; ?>
          <?php if ($privacy): ?><a href="<?php echo esc_url($privacy); ?>"<?php if ($current_privacy): ?> class="is-active" aria-current="page"<?php endif; ?>>Datenschutz</a><?php endif; ?>
       </nav>
       </div>
       <div class="sm-footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> Service Mira Gebäudereinigung</span>
       <?php if ($s['footer_credit']): ?><a href="https://pointnova.de" target="_blank" rel="noopener noreferrer">Designed by Pointnova <?php echo sm_studio_icon('arrow-up',14); ?></a><?php endif; ?>
       </div>
    </div></footer>
    <?php
}
function sm_studio_body_end() { wp_footer(); echo '</body></html>'; }

// Staging-only copy corrections for legacy service pages.
require_once SM_STUDIO_PATH . 'includes-content-cleanup.php';
