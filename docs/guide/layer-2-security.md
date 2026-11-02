# Layer 2: Security & Threat Sanitization

Web applications face continuous threats from malicious file uploads. Laravel Smart Upload enforces defense-in-depth protections on every upload automatically, without requiring custom controller validation rules.

---

### 2.1 Real MIME Magic-Bytes Inspection

Attackers frequently rename executable files (e.g., renaming `backdoor.php` to `photo.jpg`) to bypass client-side file pickers.

* Smart Upload inspects the true binary file header bytes using PHP's `fileinfo` buffer before touching disk.
* Executable MIME signatures (`application/x-php`, `text/x-php`, `application/x-executable`, `application/x-sh`) are immediately rejected.
* Even if an attacker names a file `avatar.webp`, an invalid binary header triggers a `SecurityException` with an HTTP 422 response.

---

### 2.2 Anti-Path Traversal & Null-Byte Protection

File paths containing directory traversal patterns (`../`, `..\`) or null-byte injections (`\0`) designed to escape target directories or overwrite system files are caught and rejected prior to disk writes.

---

### 2.3 Multi-Extension Spoofing Guard

Web servers with legacy or default multi-view configurations can misinterpret multi-part extensions (such as `invoice.php.png` or `avatar.sh.jpg`), executing the script rather than serving the static asset.

Smart Upload parses each filename segment and rejects inputs containing blacklisted extensions anywhere before the final extension.

---

### 2.4 SVG XSS Sanitization

SVG files are XML text documents capable of executing arbitrary JavaScript when rendered directly in browsers.

Smart Upload parses SVG payloads and blocks:
* Embedded `<script>` elements.
* Inline JavaScript event handlers (such as `onload=`, `onerror=`, `onclick=`).
* Malicious `javascript:` URIs and suspicious XML entities.

---

### 2.5 EXIF & Geolocation Metadata Stripping

Photographs taken on smartphones and digital cameras contain embedded EXIF metadata containing exact GPS coordinates (latitude/longitude), device serial numbers, and capture timestamps.

During image processing and WebP conversion, Smart Upload strips private EXIF metadata by default, protecting user privacy.

---

### 2.6 Cryptographic Entropy & Safe Slug Naming

Original filenames uploaded by clients may contain spaces, non-ASCII characters, or predictable sequences that attackers can exploit.

Smart Upload renames files using a sanitized URL slug combined with a 12-character high-entropy cryptographic token (`{slug}_{token}.{ext}`). This guarantees:
* Zero filename collisions in storage buckets.
* Unpredictable URLs that prevent enumeration attacks.
* Safe filenames across Linux, macOS, and Windows file systems.

---

### 2.7 Executable Extension Blacklist & Whitelist Overrides

Smart Upload enforces a strict baseline blacklist in `config/smart-upload.php`:

```text
php, php3, php4, php5, php7, php8, pht, phps, phtml, phar,
exe, sh, bash, bat, cmd, bin, cgi, pl, py, jar, vbs, js,
asp, aspx, jsp, htaccess, html, htm
```

#### Customizing Disallowed Extensions

You can add extra project-specific extensions to forbid in `config/smart-upload.php`:

```php
'disallowed_extensions' => ['vbs', 'com'],
```

#### Whitelist Overrides (`allowed_extensions`)

For platforms such as developer tutorials, coding schools, or script repositories, specific extensions can be explicitly allowed:

```php
// config/smart-upload.php
'allowed_extensions' => ['py'], // Allows Python script uploads
```

When an extension is whitelisted:
* It bypasses standard extension and multi-extension checks.
* For `sh` and `bash`, shell script MIME checks are relaxed.
* For `html` and `htm`, HTML MIME checks are relaxed.

::: danger Unbypassable Exploit Guard
Critical server execution exploits (`php`, `phar`, `htaccess`) can **never** be whitelisted. Smart Upload permanently rejects them even if added to `allowed_extensions`.
:::

::: warning Security Notice on HTML Files
Allowing `html` or `htm` file uploads introduces stored Cross-Site Scripting (XSS) risks if files are served from the same domain. If your application must support HTML uploads, serve them from an isolated storage subdomain (e.g. `files.example.com`) or force downloads using `Content-Disposition: attachment`.
:::
