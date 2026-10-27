<?php
if (!defined('ABSPATH')) exit;
$object=get_queried_object();
$is_quote=$object instanceof WP_Post && strpos((string)$object->post_content,'[service_mira_quote]')!==false;
$contact_page=sm_studio_page('contact');
$prices_page=sm_studio_page('prices');
$is_prices=(!$is_quote && $object instanceof WP_Post && $prices_page instanceof WP_Post && (int)$object->ID===(int)$prices_page->ID);
$is_contact=(!$is_quote && $object instanceof WP_Post && $contact_page instanceof WP_Post && (int)$object->ID===(int)$contact_page->ID);
sm_studio_body_start($is_quote?'quote':($is_contact?'contact':($is_prices?'prices':'inner')));
sm_studio_header();
$quote=sm_studio_url_for('quote');
$contact=sm_studio_url_for('contact');
?>
<?php if ($is_quote): ?>
<main id="content" class="sm-quote-main">
  <section class="sm-quote-head" aria-labelledby="sm-quote-heading">
    <div class="sm-shell sm-quote-head-inner">
      <div class="sm-quote-heading">
        <div class="sm-quote-kicker"><span class="sm-dot" aria-hidden="true"></span> SERVICE MIRA <span class="sm-quote-divider">/</span> ANGEBOTSANFRAGE</div>
        <h1 id="sm-quote-heading">Angebot anfragen</h1>
        <p>Wählen Sie die gewünschte Leistung und beschreiben Sie Ihren Bedarf. Wir freuen uns auf Ihre Anfrage.</p>
      </div>
      <div class="sm-quote-help">
        <span>Eine Frage vorab?</span>
        <?php if ($contact): ?><a href="<?php echo esc_url($contact); ?>">Kontakt aufnehmen</a><?php else: ?><a href="mailto:inf@service-mira.de">inf@service-mira.de</a><?php endif; ?>
      </div>
    </div>
  </section>
  <section class="sm-quote-section" aria-label="Angebotsformular">
    <div class="sm-shell sm-quote-layout">
      <article class="sm-entry-content sm-quote-content">
        <?php while (have_posts()): the_post(); the_content(); endwhile; ?>
      </article>
      <p class="sm-quote-disclaimer">Testbetrieb auf Staging. Eine Anfrage ist noch keine verbindliche Beauftragung. Offene Preis-, Steuer- und Vertragsfragen müssen vor einer verbindlichen Buchung geklärt werden.</p>
    </div>
  </section>
</main>
<?php elseif ($is_prices): ?>
<main id="content" class="sm-prices-main">
  <section class="sm-prices-hero" aria-labelledby="sm-prices-title">
    <div class="sm-shell sm-prices-hero-inner">
      <span class="sm-eyebrow">SERVICE MIRA · BREMEN &amp; UMGEBUNG</span>
      <h1 id="sm-prices-title">Leistungen <span>&amp;</span> Preise</h1>
      <p>Entdecken Sie unsere Reinigungsleistungen und die dazugehörigen Preisangaben. Gerne erstellen wir Ihnen ein individuelles Angebot.</p>
    </div>
  </section>
  <section class="sm-prices-section" aria-labelledby="sm-prices-content-heading">
    <div class="sm-shell sm-prices-shell">
      <div class="sm-prices-section-top">
        <div>
          <span class="sm-eyebrow">PREISÜBERSICHT</span>
          <h2 id="sm-prices-content-heading">Leistungen im Überblick</h2>
        </div>
        <?php if ($quote): ?><a class="sm-button sm-button-outline sm-prices-top-cta" href="<?php echo esc_url($quote); ?>">Individuelles Angebot anfragen</a><?php endif; ?>
      </div>
      <section class="sm-pricing-current" aria-label="Aktuelle Serviceübersicht">
        <div class="sm-pricing-rate">
          <span class="sm-pricing-rate-tag">REINIGUNGSLEISTUNGEN</span>
          <div class="sm-pricing-rate-line"><strong>20,00 €</strong><span>/ Stunde netto</span></div>
          <p>Zuzüglich 19 % MwSt. Anfahrtskosten werden anhand der Fahrstrecke individuell berechnet und im Angebot ausgewiesen.</p>
        </div>
        <h3 class="sm-pricing-list-title">Unsere Leistungen</h3>
        <div class="sm-pricing-service-grid">
          <?php foreach (array('Unterhaltsreinigung','Büroreinigung','Haushaltsreinigung','Treppenhausreinigung','Grundreinigung','Praxisreinigung') as $service_name): ?>
            <div class="sm-pricing-service"><span class="sm-pricing-service-check" aria-hidden="true">✓</span><span><?php echo esc_html($service_name); ?></span></div>
          <?php endforeach; ?>
        </div>
        <p class="sm-pricing-caveat">Weitere Leistungen und besondere Anforderungen auf Anfrage. Maßgeblich ist das individuell geprüfte Angebot; eine Anfrage ist keine Buchung.</p>
      </section>
      <?php /* Historical page content remains in WordPress and is accessible through the editor. Do not publish outdated rates. */ ?>
      <div class="sm-prices-end">
        <div>
          <span class="sm-eyebrow">NOCH FRAGEN?</span>
          <h2>Wir helfen Ihnen gerne weiter.</h2>
          <p>Sie suchen eine passende Reinigungsleistung? Beschreiben Sie uns Ihren Bedarf.</p>
        </div>
        <?php if ($quote): ?><a class="sm-button sm-button-primary" href="<?php echo esc_url($quote); ?>">Angebot anfordern</a><?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php elseif ($is_contact): ?>
<main id="content" class="sm-contact-main">
  <section class="sm-contact-hero" aria-labelledby="sm-contact-h1">
    <div class="sm-shell sm-contact-hero-inner">
      <div>
        <span class="sm-eyebrow">SERVICE MIRA &middot; BREMEN &amp; UMGEBUNG</span>
        <h1 id="sm-contact-h1">Kontakt</h1>
        <p>Sie haben Fragen zu unseren Reinigungsleistungen? Wir sind gerne für Sie da.</p>
      </div>
      <a class="sm-contact-home" href="<?php echo esc_url(sm_studio_url()); ?>">Startseite</a>
    </div>
  </section>
  <section class="sm-contact-section" aria-label="Kontaktinformationen">
    <div class="sm-shell sm-contact-layout">
      <article class="sm-contact-information">
        <span class="sm-eyebrow">SO ERREICHEN SIE UNS</span>
        <?php
        // Read the original WP page as data; do not output its legacy HTML.
        $source = (string) get_post_field('post_content', get_queried_object_id());
        $plain = html_entity_decode(wp_strip_all_tags(strip_shortcodes(preg_replace('/<\/(?:p|div|li|h[1-6]|br|tr)>/i', ' $0 ', $source))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $plain));
        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $plain, $found_email);
        preg_match('/(?:Tel(?:efon)?\s*[:.]?\s*)(\+?[0-9][0-9 \/()\-]{7,})/iu', $plain, $found_phone);
        $email = 'inf@service-mira.de'; // Client-confirmed mailbox; legacy contact-page data remains untouched.
        $phone = isset($found_phone[1]) ? trim($found_phone[1]) : '';
        $web_url = '';
        $web_url = 'https://service-mira.de';
        // Extract weekday and weekend opening hours independently from the saved page.
        // WordPress legacy markup may separate day labels and times using spans,
        // line breaks, or non-breaking spaces. Never guess missing hours.
        $hours_source = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hours_source = preg_replace('~<br\s*/?>~iu', ' ', $hours_source);
        $hours_source = wp_strip_all_tags(preg_replace('~<[^>]+>~u', ' ', $hours_source));
        $hours_source = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $hours_source));
        $schedule = array();
        foreach (array(
            'Mo–Fr' => 'Mo\.?\s*[-–—]\s*Fr\.?',
            'Sa–So' => 'Sa\.?\s*[-–—]\s*So\.?',
        ) as $label => $day_pattern) {
            $expression = '~(?:'.$day_pattern.')\s*(?:von\s*)?(\d{1,2})\s*[:.]\s*(\d{2})\s*(?:Uhr\s*)?(?:bis|[-–—])\s*(\d{1,2})\s*[:.]\s*(\d{2})~iu';
            // Legacy editor occasionally inserts other elements and punctuation between day and time.
            // Use a bounded fallback for the *same* day label; never insert made-up business hours.
            if (!preg_match($expression, $hours_source, $hours)) {
                $fallback = '~(?:'.$day_pattern.')[^0-9]{0,100}(\d{1,2})\s*[:.]\s*(\d{2})[^0-9]{0,30}(\d{1,2})\s*[:.]\s*(\d{2})~iu';
                preg_match($fallback, $hours_source, $hours);
            }
            if (!empty($hours)) {
                $start_hour = (int) $hours[1]; $start_min = (int) $hours[2];
                $end_hour = (int) $hours[3]; $end_min = (int) $hours[4];
                if ($start_hour <= 23 && $end_hour <= 23 && $start_min <= 59 && $end_min <= 59) {
                    $schedule[$label] = sprintf('%02d:%02d–%02d:%02d Uhr', $start_hour, $start_min, $end_hour, $end_min);
                }
            }
        }
        ?>
        <div class="sm-contact-modern sm-contact-source-rendered">
          <?php if ($email): ?>
          <div class="sm-contact-modern-row"><span class="sm-contact-modern-icon" aria-hidden="true">✉</span><div class="sm-contact-modern-copy"><span class="sm-contact-modern-label">E-Mail</span><a class="sm-contact-modern-value" href="<?php echo esc_url('mailto:'.$email); ?>"><?php echo esc_html($email); ?></a></div></div>
          <?php endif; ?>
          <?php if ($phone): ?>
          <div class="sm-contact-modern-row"><span class="sm-contact-modern-icon" aria-hidden="true">☎</span><div class="sm-contact-modern-copy"><span class="sm-contact-modern-label">Telefon</span><a class="sm-contact-modern-value" href="<?php echo esc_url('tel:'.preg_replace('/[^+0-9]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></div></div>
          <?php endif; ?>
          <?php if ($web_url): ?>
          <div class="sm-contact-modern-row"><span class="sm-contact-modern-icon" aria-hidden="true">↗</span><div class="sm-contact-modern-copy"><span class="sm-contact-modern-label">Website</span><a class="sm-contact-modern-value" href="<?php echo esc_url($web_url); ?>"><?php echo esc_html($web_url); ?></a></div></div>
          <?php endif; ?>
          <?php if ($schedule): ?>
          <section class="sm-contact-modern-hours" aria-label="Öffnungszeiten"><div class="sm-contact-modern-hours-title">Öffnungszeiten</div>
          <?php foreach ($schedule as $label => $time): ?><div class="sm-contact-modern-hours-line"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($time); ?></strong></div><?php endforeach; ?>
          </section>
          <?php endif; ?>
        </div>
        <?php // Original WordPress content remains saved in the editor; it is not duplicated on the public page. ?>
      </article>
      <aside class="sm-contact-action" aria-labelledby="sm-contact-action-title">
        <span class="sm-eyebrow">UNVERBINDLICH ANFRAGEN</span>
        <h2 id="sm-contact-action-title">Wie können wir helfen?</h2>
        <p>Erzählen Sie uns, welche Reinigung Sie benötigen. Wir freuen uns auf Ihre Anfrage.</p>
        <?php if ($quote): ?><a class="sm-button sm-button-primary" href="<?php echo esc_url($quote); ?>">Angebot anfordern</a><?php endif; ?>
      </aside>
    </div>
  </section>
</main>
<?php else: ?>
<?php
  // Reuse WordPress pages as-is; all typography/layout changes are CSS-only.
  $page_name = get_the_title();
  $is_legal = $object instanceof WP_Post && in_array((int)$object->ID,array_filter(array(
      ($p=sm_studio_page('imprint')) ? (int)$p->ID : 0,
      ($p=sm_studio_page('privacy')) ? (int)$p->ID : 0
  )),true);
?>
<main id="content" class="sm-inner-main<?php echo $is_legal ? ' sm-legal-page' : ' sm-service-page'; ?>">
  <section class="sm-inner-hero"><div class="sm-shell sm-inner-hero-layout">
    <div>
      <nav class="sm-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(sm_studio_url()); ?>">Startseite</a> <span aria-hidden="true">/</span> <span><?php echo esc_html($page_name); ?></span></nav>
      <span class="sm-eyebrow">SERVICE MIRA · BREMEN &amp; UMGEBUNG</span>
      <h1><?php echo esc_html($page_name); ?></h1>
      <p><?php echo $is_legal ? 'Informationen und rechtliche Hinweise.' : 'Informationen zu dieser Leistung im Überblick.'; ?></p>
    </div>
    <?php if (!$is_legal && $quote): ?>
      <?php
      $sm_service_ids = array('Büroreinigung'=>'buero','Praxisreinigung'=>'praxis','Bauendreinigung'=>'bau','Grundreinigung'=>'grund','Fensterreinigung'=>'fenster','Unterhaltsreinigung'=>'unterhalt','Haushaltsreinigung'=>'haushalt','Gartenarbeit'=>'garten','Einkaufshilfe'=>'einkauf','Treppenhaus'=>'treppen','Treppenreinigung'=>'treppen');
      $sm_page_title = $object instanceof WP_Post ? $object->post_title : '';
      $sm_quote_link = isset($sm_service_ids[$sm_page_title]) ? add_query_arg('mira_service', $sm_service_ids[$sm_page_title], $quote) : $quote;
      ?>
      <a class="sm-button sm-button-outline sm-inner-hero-cta" href="<?php echo esc_url($sm_quote_link); ?>">Angebot anfragen</a>
    <?php endif; ?>
  </div></section>
  <section class="sm-standard-section" aria-label="Seiteninhalt"><div class="sm-shell">
    <div class="sm-detail-grid<?php echo $is_legal ? ' sm-legal-grid' : ' sm-service-grid-detail'; ?>">
      <article class="sm-entry-content sm-standard-content" id="sm-article-content">
        <?php while (have_posts()): the_post(); the_content(); endwhile; ?>
      </article>
      <?php if (!$is_legal): ?>
        <aside class="sm-content-aside sm-service-aside" aria-label="Service Mira kontaktieren">
          <div class="sm-content-aside-card sm-service-aside-card">
            <span class="sm-eyebrow">FÜR IHRE RÄUME</span>
            <h2>Wir sind für Sie da.</h2>
            <p>Möchten Sie mehr über diese Leistung erfahren oder ein Angebot anfordern?</p>
            <?php if ($quote): ?><a class="sm-button sm-button-primary" href="<?php echo esc_url($quote); ?>">Angebot anfragen</a><?php endif; ?>
            <?php if ($contact): ?><a class="sm-aside-link" href="<?php echo esc_url($contact); ?>">Kontakt aufnehmen</a><?php endif; ?>
          </div>
          <?php if ($prices_page): ?><a class="sm-service-prices-link" href="<?php echo esc_url(sm_studio_url($prices_page)); ?>">Leistungen &amp; Preisangaben</a><?php endif; ?>
        </aside>
      <?php endif; ?>
    </div>
    <?php if (!$is_legal && $quote): ?>
      <aside class="sm-standard-bottom-cta"><div>
        <span class="sm-eyebrow">WIR SIND FÜR SIE DA</span>
        <h2>Einfach unverbindlich anfragen.</h2>
        <p>Beschreiben Sie Ihren Reinigungsbedarf. Offene Preise und Konditionen klären wir vor einer Beauftragung.</p>
      </div><a class="sm-button sm-button-primary" href="<?php echo esc_url(isset($sm_quote_link) ? $sm_quote_link : $quote); ?>">Angebot anfordern</a></aside>
    <?php endif; ?>
  </div></section>
</main>
<?php endif; ?>
<?php sm_studio_footer(); sm_studio_body_end(); ?>
