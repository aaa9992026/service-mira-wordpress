# Version 1.2.0 - PDF staging preview
- Admin-only in-memory PDF preview from immutable signed quotation snapshot.
- Download via nonce-protected POST in Mira Angebote > quotation details.
- Explicitly non-binding and no real price quoted where travel distance is unconfirmed.
- PDF never persisted; no private link embedded in download.
- No customer PDF emails enabled in staging. Production customer emails and binding acceptance remain blocked until the client confirms distance methodology, price/tax treatment and authorized production email and the full workflow receives live end-to-end review.
