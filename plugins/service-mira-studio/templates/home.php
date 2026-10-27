<?php
/**
 * Service Mira Bright Edition, based on the approved visual reference.
 * No page, rate or booking content is changed. Quote requests use the existing page.
 */
if (!defined('ABSPATH')) exit;
sm_studio_body_start('home');
sm_studio_header();
$settings=sm_studio_settings();
$hero_info=$settings['hero_id'] ? sm_studio_hero_source($settings['hero_id']) : false;
$hero=$hero_info ? $hero_info['url'] : SM_STUDIO_URL.'assets/office-bright.webp';
$quote=sm_studio_url_for('quote');
$prices=sm_studio_url_for('prices');
$contact=sm_studio_url_for('contact');
$services=sm_studio_services();
$cleaning=[]; $other=[];
foreach ($services as $service) {
  $name=wp_strip_all_tags($service->post_title);
  if (stripos($name,'Gartenarbeit')!==false || stripos($name,'Einkaufshilfe')!==false) $other[]=$service;
  else $cleaning[]=$service;
}
$service_descriptions=[
  'Büroreinigung'=>'Gepflegte Arbeitsplätze und angenehme Räume.',
  'Unterhaltsreinigung'=>'Regelmäßige Reinigung im Alltag.',
  'Grundreinigung'=>'Wenn es besonders gründlich sein soll.',
  'Treppenhaus'=>'Saubere und einladende Gemeinschaftsbereiche.',
  'Praxisreinigung'=>'Reinigung für Praxis- und Behandlungsräume.',
  'Bauendreinigung'=>'Reinigung nach Bau- und Umbauarbeiten.'
];
$service_icons=[
  'Büroreinigung'=>'building','Unterhaltsreinigung'=>'spray',
  'Grundreinigung'=>'sparkle','Treppenhaus'=>'stairs',
  'Praxisreinigung'=>'medical','Bauendreinigung'=>'hardhat'
];
$before_id=isset($settings['before_id'])?absint($settings['before_id']):0;
$after_id=isset($settings['after_id'])?absint($settings['after_id']):0;
$before_photo=$before_id?wp_get_attachment_image_url($before_id,'large'):false;
$after_photo=$after_id?wp_get_attachment_image_url($after_id,'large'):false;
$real_comparison=$before_photo && $after_photo;
$compare_left=$real_comparison?$before_photo:$hero;
$compare_right=$real_comparison?$after_photo:$hero;
?>
<main id="content" class="mx-site" data-mx-root>
 <section class="mx-hero" aria-labelledby="mx-hero-title">
  <div class="mx-hero-ripple" aria-hidden="true"></div>
  <div class="mx-container mx-hero-layout">
   <div class="mx-hero-copy">
    <span class="mx-overline">SERVICE MIRA · BREMEN &amp; UMGEBUNG</span>
    <h1 id="mx-hero-title">Für Räume, in denen<br><span>Sie sich wohlfühlen.</span></h1>
    <p>Ob gepflegte Arbeitsplätze, saubere Treppenhäuser oder ein angenehmes Zuhause: Wir sind Ihr Ansprechpartner für Reinigungsleistungen in Bremen und Umgebung.</p>
    <div class="mx-hero-buttons">
      <?php if($quote):?><a class="mx-btn mx-btn-primary" href="<?php echo esc_url($quote); ?>">Angebot anfordern</a><?php endif; ?>
      <a class="mx-btn mx-btn-outline" href="#leistungen">Unsere Leistungen entdecken</a>
    </div>
    <div class="mx-trust-row" aria-label="Service Mira im Überblick">
      <div><span class="mx-trust-icon mx-trust-icon--shield" aria-hidden="true"></span><span>Zuverlässig<br>&amp; gründlich</span></div>
      <div><span class="mx-trust-icon mx-trust-icon--leaf" aria-hidden="true"></span><span>In Bremen<br>&amp; Umgebung</span></div>
      <div><span class="mx-trust-icon mx-trust-icon--users" aria-hidden="true"></span><span>Für Unternehmen<br>&amp; Privathaushalte</span></div>
    </div>
   </div>
   <div class="mx-hero-visual">
      <img src="<?php echo esc_url($hero); ?>" alt="Heller und gepflegter Büroarbeitsbereich" loading="eager" fetchpriority="high" width="1672" height="941">
      <div class="mx-glass-curve" aria-hidden="true"></div>
      <div class="mx-hero-float"><img src="<?php echo esc_url(SM_STUDIO_URL.'assets/service-mira-drop.png'); ?>" alt="" width="35" height="35"><span><strong>Saubere Räume.</strong><small>Für ein gutes Gefühl.</small></span></div>
   </div>
  </div>
 </section>

 <section class="mx-services" id="leistungen" aria-labelledby="mx-services-title">
  <div class="mx-container">
   <div class="mx-section-head mx-services-head">
    <div><span class="mx-overline">UNSERE LEISTUNGEN</span><h2 id="mx-services-title">Professionelle Reinigung. <span>Für jeden Bedarf.</span></h2></div>
    <div class="mx-head-aside"><p>Von der regelmäßigen Pflege bis zur gründlichen Reinigung. Entdecken Sie, welche Leistungen Service Mira anbietet.</p>
    <?php if($prices):?><a href="<?php echo esc_url($prices); ?>" class="mx-textlink">Alle Leistungen &amp; Preise</a><?php endif; ?></div>
   </div>
   <?php if ($cleaning): ?><div class="mx-services-grid">
    <?php foreach($cleaning as $service):
      $name=wp_strip_all_tags($service->post_title);
      $icon=isset($service_icons[$name])?$service_icons[$name]:'sparkle';
      $summary=isset($service_descriptions[$name])?$service_descriptions[$name]:'Entdecken Sie unsere Leistungen.';
    ?><a class="mx-service-tile" href="<?php echo esc_url(sm_studio_url($service)); ?>">
       <span class="mx-service-symbol mx-service-symbol--<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
       <span class="mx-service-info"><strong><?php echo esc_html($name); ?></strong><small><?php echo esc_html($summary); ?></small></span>
       <span class="mx-service-go" aria-hidden="true">→</span>
     </a><?php endforeach; ?></div><?php else: ?><p class="mx-no-services">Unsere Reinigungsleistungen finden Sie auf der Seite „Leistungen & Preise“.</p><?php endif; ?>
   <?php if($other):?><div class="mx-secondary-services"><span>Weitere Leistungen</span><?php foreach($other as $entry):?><a href="<?php echo esc_url(sm_studio_url($entry)); ?>"><?php echo esc_html($entry->post_title); ?> ↗</a><?php endforeach;?></div><?php endif; ?>
  </div>
 </section>

 <section class="mx-quote-section" id="anfrage" aria-labelledby="mx-quote-heading">
   <div class="mx-container mx-quote-panel">
    <div class="mx-quote-intro"><span class="mx-overline">SCHNELL &amp; UNKOMPLIZIERT</span><h2 id="mx-quote-heading">Jetzt unverbindlich<br>ein Angebot anfordern.</h2>
       <p>Wählen Sie eine Reinigungsleistung und die gewünschte Dauer aus. Anschließend können Sie Ihre unverbindliche Anfrage über das Formular senden.</p>
       <span class="mx-hand-note">In wenigen Schritten zur Anfrage</span>
    </div>
    <div class="mx-quote-widget" aria-label="Anfrage vorbereiten">
      <div class="mx-form-top"><div class="mx-field"><label for="mx-service-select">Welche Unterstützung wünschen Sie?</label>
       <select id="mx-service-select"><option value="">Leistung wählen</option><?php foreach($cleaning as $entry):?><option value="<?php echo esc_attr($entry->post_title); ?>"><?php echo esc_html($entry->post_title); ?></option><?php endforeach; ?></select></div>
       <div class="mx-field"><label for="mx-hours">Stunden</label><input id="mx-hours" type="number" min="0.25" max="1000" step="0.25" value="2" inputmode="decimal"></div></div>
      <p class="mx-quote-status" id="mx-quote-status" role="status" aria-live="polite">Unverbindliche Vorabauswahl. Die endgültigen Preise und Konditionen werden vor einer Buchung bestätigt.</p>
      <?php if($quote):?><a class="mx-btn mx-btn-primary mx-quote-go" href="<?php echo esc_url($quote); ?>">Zur Angebotsanfrage</a><?php else:?><span class="mx-quote-help">Bitte Angebotsseite in den Plugin-Einstellungen auswählen.</span><?php endif; ?>
      <div class="mx-widget-benefits"><span>✓ Unverbindlich</span><span>✓ Transparente Angaben</span><span>✓ Persönliche Klärung</span></div>
    </div>
    <div class="mx-clean-visual" aria-hidden="true">
     <span class="mx-clean-halo"></span><div class="mx-spray"><i class="mx-spray-nozzle"></i><i class="mx-spray-handle"></i><i class="mx-spray-neck"></i><i class="mx-spray-bottle"></i></div><div class="mx-towels"><i></i><i></i><i></i></div>
    </div>
   </div>
 </section>

 <section class="mx-how" id="ablauf" aria-labelledby="mx-how-title"><div class="mx-container">
  <span class="mx-overline">SO EINFACH GEHT'S</span><h2 id="mx-how-title">In 3 Schritten zu Ihrer Anfrage.</h2>
  <div class="mx-steps">
   <div class="mx-step"><span class="mx-step-marker"><span class="mx-step-icon mx-step-icon--file" aria-hidden="true"></span><span class="mx-step-count" aria-hidden="true">01</span></span><div class="mx-step-copy"><h3>Anfrage vorbereiten</h3><p>Leistung und gewünschte Stunden auswählen.</p></div></div>
   <span class="mx-steps-arrow" aria-hidden="true">→</span>
   <div class="mx-step"><span class="mx-step-marker"><span class="mx-step-icon mx-step-icon--mail" aria-hidden="true"></span><span class="mx-step-count" aria-hidden="true">02</span></span><div class="mx-step-copy"><h3>Angaben übermitteln</h3><p>Kontaktdaten im Anfrageformular eingeben und absenden.</p></div></div>
   <span class="mx-steps-arrow" aria-hidden="true">→</span>
   <div class="mx-step"><span class="mx-step-marker"><span class="mx-step-icon mx-step-icon--calendar" aria-hidden="true"></span><span class="mx-step-count" aria-hidden="true">03</span></span><div class="mx-step-copy"><h3>Details abstimmen</h3><p>Leistung und Konditionen gemeinsam klären.</p></div></div>
  </div>
 </div></section>

 <section class="mx-benefits" id="vorteile" aria-labelledby="mx-benefits-title"><div class="mx-container">
  <span class="mx-overline">UNSER ANSPRUCH</span><h2 id="mx-benefits-title">Darauf kommt es uns an.</h2>
  <div class="mx-benefit-grid">
   <div><span class="mx-benefit-icon mx-benefit-icon--check" aria-hidden="true"></span><span><strong>Klare Informationen</strong><small>Verständliche Informationen zu Leistungen und Preisen.</small></span></div>
   <div><span class="mx-benefit-icon mx-benefit-icon--clock" aria-hidden="true"></span><span><strong>Individuelle Absprache</strong><small>Leistungsumfang und Termin gemeinsam abstimmen.</small></span></div>
   <div><span class="mx-benefit-icon mx-benefit-icon--shield" aria-hidden="true"></span><span><strong>Sorgfältige Reinigung</strong><small>Sorgfältige Reinigung verschiedener Räumlichkeiten.</small></span></div>
   <div><span class="mx-benefit-icon mx-benefit-icon--users" aria-hidden="true"></span><span><strong>Direkter Kontakt</strong><small>Fragen klären und unverbindlich anfragen.</small></span></div>
  </div>
 </div></section>

 <section class="mx-showcase" aria-labelledby="mx-showcase-heading"><div class="mx-container mx-showcase-panel">
   <div class="mx-showcase-text"><span class="mx-overline"><?php echo $real_comparison?'BILDER IM VERGLEICH':'EINBLICKE'; ?></span><h2 id="mx-showcase-heading"><?php echo $real_comparison?'Zwei Ansichten.':'Räume aus einer'; ?><br><span><?php echo $real_comparison?'Ein Unterschied.':'neuen Perspektive.'; ?></span></h2>
    <p><?php echo $real_comparison?'Verschieben Sie den Regler, um die vom Website-Team ausgewählten Originalaufnahmen zu vergleichen.':'Entdecken Sie Details einer hellen Büroumgebung. Der Bildregler zeigt zwei Darstellungen desselben Fotos und keine tatsächlichen Vorher-Nachher-Ergebnisse.'; ?></p>
    <div class="mx-showcase-controls" role="group" aria-label="Bildansicht schrittweise verschieben"><span class="mx-showcase-controls-label">Ansicht verschieben</span><button type="button" class="mx-circle-btn mx-circle-btn--prev" data-mx-nudge="-20" aria-label="Bildansicht nach links verschieben"><span aria-hidden="true">←</span></button><button type="button" class="mx-circle-btn mx-circle-btn--next" data-mx-nudge="20" aria-label="Bildansicht nach rechts verschieben"><span aria-hidden="true">→</span></button></div>
   </div>
   <div class="mx-compare" data-mx-compare style="--mx-split:50%">
    <img class="mx-compare-left" src="<?php echo esc_url($compare_left); ?>" alt="<?php echo $real_comparison?'Erste vom Website-Team ausgewählte Aufnahme':'Erste illustrative Ansicht der Büroumgebung'; ?>" loading="lazy">
    <div class="mx-compare-reveal"><img class="mx-compare-right" src="<?php echo esc_url($compare_right); ?>" alt="<?php echo $real_comparison?'Zweite vom Website-Team ausgewählte Aufnahme':'Zweite illustrative Ansicht derselben Büroumgebung'; ?>" loading="lazy"></div>
    <span class="mx-compare-tag mx-tag-left"><?php echo $real_comparison?'Vorher':'Sanfter Look'; ?></span><span class="mx-compare-tag mx-tag-right"><?php echo $real_comparison?'Nachher':'Klarer Look'; ?></span>
    <div class="mx-comparison-divider" aria-hidden="true"><span>‹ ›</span></div>
    <input type="range" class="mx-compare-range" min="0" max="100" step="1" value="50" aria-label="Bildvergleich verschieben">
   </div>
 </div></section>

 <section class="mx-closing" aria-labelledby="mx-closing-heading"><div class="mx-container mx-closing-panel">
   <div class="mx-closing-message"><span class="mx-overline">SERVICE MIRA · BREMEN &amp; UMGEBUNG</span><h2 id="mx-closing-heading">Lassen Sie uns gemeinsam<br>für saubere Räume sorgen.</h2></div>
   <div class="mx-closing-mark">
     <?php if($quote):?><a class="mx-drop-action" href="<?php echo esc_url($quote); ?>" aria-label="Angebot anfordern – zur Angebotsseite" title="Zur unverbindlichen Angebotsanfrage">
     <?php elseif($contact):?><a class="mx-drop-action" href="<?php echo esc_url($contact); ?>" aria-label="Kontakt mit Service Mira aufnehmen" title="Service Mira kontaktieren">
     <?php else:?><span class="mx-drop-action" aria-hidden="true"><?php endif; ?>
       <img class="mx-drop-hires-art" src="<?php echo esc_url(SM_STUDIO_URL.'assets/water-drop-badge-420.webp'); ?>"
            srcset="<?php echo esc_url(SM_STUDIO_URL.'assets/water-drop-badge-420.webp'); ?> 420w, <?php echo esc_url(SM_STUDIO_URL.'assets/water-drop-badge-840.webp'); ?> 840w"
            sizes="(max-width: 480px) 56px, (max-width: 760px) 70px, (max-width: 1050px) 80px, 100px"
            alt="" width="840" height="840" decoding="async" loading="lazy">
       <span class="mx-drop-action-hint" aria-hidden="true"><?php echo $quote?'Jetzt anfragen':($contact?'Kontakt':'Saubere Räume'); ?></span>
     <?php if($quote || $contact):?></a><?php else:?></span><?php endif; ?>
   </div>
   <div class="mx-closing-cta"><h3>Jetzt unverbindlich anfragen.</h3><p>Wir freuen uns auf Ihre Nachricht und besprechen Ihre Anforderungen persönlich.</p>
    <?php if($quote):?><a class="mx-btn mx-btn-primary" href="<?php echo esc_url($quote); ?>">Angebot anfordern</a><?php elseif($contact):?><a class="mx-btn mx-btn-primary" href="<?php echo esc_url($contact); ?>">Kontakt aufnehmen</a><?php endif; ?>
    <span class="mx-closing-small">Persönlich · Bremen und Umgebung</span>
   </div>
 </div></section>
</main>
<?php sm_studio_footer(); sm_studio_body_end(); ?>
