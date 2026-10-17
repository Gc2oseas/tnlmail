# Compact reader and modal composition

Compose always uses HTML. Jodit's source button switches between visual HTML and code without a separate message-format selector. Reply opens a clean editor and preserves RFC conversation metadata; forward retains the sanitized original HTML, including tables, with a bounded fallback for oversized content. Markdown reading support remains available.

Same-origin authenticated compose pages can open inside a native dialog iframe; only the application origin can embed those pages. Close and Escape return to the same reader; successful submission signals only its own parent window using an origin and frame-source check, then refreshes the conversation. CSRF, account ownership, routing and one-use external send tokens remain enforced.

Sanitization now retains empty cells, bounded column widths and a small allowlist of alignment/font-weight properties. It still strips scripts, arbitrary CSS, layout heights and active URLs. Hidden preheaders remain hidden. Reader quotes fold into details without changing the sent content or print rendering. Text/plain flowed/delsp and charset decoding produce safe structural quotes. Raster image heights no longer depend on iframe viewport height; reader frames have a maximum height with internal scrolling.

Validation: automated regression suites, an isolated browser fixture with a three-column table (including an empty cell), external modal reply and simulated send/close, and internal compose dialog. No real email was sent and the user's problematic original MIME was not available for reproduction.
