# Service Mira WordPress — private source handover

**Staging preview:** https://www.service-mira.de/yamato/ (password protected)  
**Status:** STAGING ONLY. No permission to publish, enable live bookings or customer emails.

## Source packages

- `plugins/service-mira-studio/` — Service Mira Studio **2.0.51** (site templates, styling, navigation, legal-page *layout only*).
- `plugins/service-mira-quotes/` — Service Mira Angebote / Quotes **1.4.26** (quotation staging safeguards, route estimates, simulated acceptance and test mail).

These sources were extracted from the supplied plugin release ZIPs. The authorized staging test mailbox address was deliberately replaced with `staging-test@example.invalid` in the repository copy for privacy; that placeholder is non-deliverable. **Do not deploy this repository copy without explicitly configuring an authorized test recipient and revalidating the behavior.** The staging installation remains unchanged.

## Previously witnessed staging checks

- Route A: 5.926 km round trip, first 10 km free, travel €0.00.
- Route B: 14.696 km round trip, 4.696 km chargeable at €0.50/km, travel €2.35 net.
- 2-hour cleaning demonstration: €40.00 net; VAT 19% provisional; final travel/VAT dependent on individual quote.
- WordPress-generated synthetic PDF test and simulated acceptance confirmation were received in an authorized external test mailbox; simulation generated UTC timestamp and SHA-256 snapshot.
- The 24-hour voluntary cancellation period is represented in plugin rules and calculated from acceptance. **No automated cancellation processing workflow is claimed.** Applicable statutory rights remain unaffected.

## Restrictions and handover caveats

- The existing Impressum cleaning-contract text was intentionally **not changed or migrated**. Legal wording and privacy policy require client review.
- The full WP STAGING backup (roughly 500 MB) is **not included** here. Keep it in access-controlled storage; it may include production-like customer records, passwords and tokens.
- No `wp-config.php`, database dump, SMTP passwords, API keys, personal test mailbox or WordPress upload directories are included.
- Staging password protection, manual review, disabled real customer messaging and disabled binding bookings must remain in place until the client's express go-live authorization.
- GitHub is for source control, **not** a WordPress publication mechanism.

## Deployment

Use the originally approved, tested release plugins in staging; do not assume a GitHub copy with a placeholder test address is drop-in deployable. Test all route, SMTP, legal and acceptance controls before any production migration, and obtain explicit authorization before go-live.

No open-source licence is granted by this repository. Confirm client IP/ownership rights before sharing with anyone beyond authorized collaborators.
