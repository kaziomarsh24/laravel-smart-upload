# Security & File Sanitization

Smart Upload includes built-in security protections against common file upload vulnerabilities without requiring custom validation boilerplate.

---

### 2.1 Real MIME Magic-Bytes Inspection

File extensions can easily be spoofed (e.g. renaming `malicious.php` to `malicious.jpg`). Smart Upload inspects the true binary magic bytes of every uploaded file using PHP's `fileinfo` extension before permitting any operation.

---

### 2.2 Anti-Path Traversal & Null-Byte Protection

File names with directory traversal payloads (`../../etc/passwd`) or null bytes (`file.php\0.jpg`) are neutralized automatically.

---

### 2.3 Forbidden Script Extensions (Blacklist)

Server-side executable extensions are permanently blocked:
`php`, `php3`, `php4`, `php5`, `phtml`, `phar`, `sh`, `exe`, `bat`, `cgi`, `py`, `jar`, `asp`, `aspx`, `htaccess`.

Attempting to upload any of these triggers a `KaziOmar\SmartUpload\Exceptions\SecurityException` with a 422 status code.

---

### 2.4 SVG XSS Sanitization

SVG files can contain embedded `<script>` tags, event handlers (`onload=alert(1)`), or remote XML entities (XXE). Smart Upload scans SVG payloads and rejects unsanitized threat vectors.

---

### 2.5 EXIF & Geolocation Metadata Stripping

When converting images to WebP or re-encoding them, personal privacy metadata (such as GPS coordinates, camera models, and timestamps) is automatically stripped from the encoded binary.

