<?php
/**
 * Plugin Name: Service Mira Angebote (Staging)
 * Description: Isolierter Angebotsrechner mit geschützter Annahme und sicherem Testbetrieb.
 * Version: 1.4.26
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Pointnova
 * License: GPL-2.0-or-later
 * Text Domain: service-mira-quotes
 */
defined('ABSPATH') || exit;
define('MIRA_QUOTES_FILE', __FILE__);
require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/plugin.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/routes.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/privacy.php';
register_activation_hook(__FILE__, ['Mira_Quotes', 'activate']);
Mira_Quotes::boot();
Mira_Admin::boot();
Mira_Privacy::boot();
