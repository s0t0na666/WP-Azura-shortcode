# WIP review checklist

- [ ] Audit every API path and response shape against the supplied AzuraCast 0.24.0 OpenAPI schema.
- [ ] Verify the public podcast endpoints; do not assume undocumented routes.
- [ ] Remove all status/update text from the Playing Next component, including WebSocket lifecycle messages.
- [ ] Verify WebSocket initial payload handling, publications, reconnect, and last-known-data behavior.
- [ ] Review WordPress capability checks, nonce handling, sanitization, escaping, and REST exposure.
- [ ] Test PHP syntax and compatibility on PHP 8.1 and PHP 8.5.
- [ ] Test shortcodes and iframe URLs in a clean WordPress installation.
- [ ] Test against the target AzuraCast 0.24.0 instance.
- [ ] Confirm log-directory protection on Apache/LiteSpeed and document Nginx handling.
- [ ] Build a fresh installable ZIP after the audit.

No item should be marked complete without an actual test or source review.
