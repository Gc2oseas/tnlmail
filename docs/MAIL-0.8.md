# Mail 0.8

Jodit 4.18.4 is bundled locally under its MIT license. Select HTML to use the visual editor and source button. Markdown uses Parsedown 1.8.0 safe mode followed by the same HTML sanitizer as the reader. Outgoing rich messages include a plain alternative; the internal router and local sent copies preserve their format.

The reader supports CID, MIME Content-Location, validated raster data images, and HTTPS external images. External images now load when a message is opened; the reader offers Hide external images. HTTP image URLs are upgraded to HTTPS and require the image host to support TLS. No server proxy fetches arbitrary image URLs. Active content and source layout heights remain removed.

Sent copies open in a dedicated account-scoped view, with reply, forward and print. RFC Message-ID, In-Reply-To and References relate received messages in the current selected folder (at most 10 downloaded) and the latest 300 local sent copies. Internal conversations use reply_to_id and each participant's visible copies (latest 500). Identical subjects alone never merge conversations. Older local sent copies do not contain reply references and cannot always be reconstructed. The external sent list remains local copies, not the provider Sent folder.

MIME text/markdown is formatted automatically. A plain-text message can be explicitly shown as Markdown using the reader button; arbitrary plain text is not guessed as Markdown.

Validation: PHP unit/protocol suites with simulated SMTP/IMAP; browser tests using isolated fictitious accounts and a no-network SMTP stub. The original affected real email was not available for reproduction.
