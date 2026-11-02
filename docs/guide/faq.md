# Frequently Asked Questions & Troubleshooting

### How does Smart Upload differ from Spatie MediaLibrary?
Spatie MediaLibrary is an Eloquent-centric solution that requires database migrations, multiple tables (`media`), and couples media handling to Eloquent models.

**Laravel Smart Upload takes a Zero-Database approach:**
- No database migrations, no extra tables, zero query overhead.
- Media paths are stored directly in your model's native string or JSON columns.
- Provides built-in atomic database rollback protection, resumable chunk streaming, and decoupled two-step temporary uploads.

---

### Does WebP conversion work with transparent PNGs?
**Yes.** Modern WebP formats fully support alpha channel transparency with significantly smaller byte size than PNG. If you want to keep the exact original format (e.g. for brand logos or vector assets), you can explicitly chain `->keepOriginalFormat()`.

---

### What happens if an uploaded image is smaller than `scaleDown()` dimensions?
When using `->scaleDown($width, $height)`, Smart Upload **never upscales** smaller images. It preserves original dimensions to prevent pixelation, while still applying security sanitization and WebP compression.

If you want the image forced to exact dimensions with center cropping, use `->fit($width, $height)` instead.

---

### How do I disable WebP conversion for a single upload?
Simply chain `->keepOriginalFormat()`:

```php
Upload::file($request->file('icon'))
    ->keepOriginalFormat()
    ->save('icons');
```

Or set `'auto_webp' => false` in `config/smart-upload.php` to disable it globally.

---

### What happens to uploaded files if `DB::transaction()` fails?
With **Layer 3 (Atomic DB Rollback Shield)**, any file saved during an active database transaction is automatically registered with Laravel's transaction lifecycle. If the transaction rolls back due to an unhandled exception:
- The uploaded file and any generated variants are immediately purged from the storage disk (local or cloud S3/R2).
- Zero orphan or ghost files remain on your storage.

---

### How do I clean up orphaned temporary files?
Temporary files created via `Upload::temp()` that are abandoned by users (never promoted via `Upload::fromTemp()`) are automatically purged by running:

```bash
php artisan smart-upload:prune
```

You can automate this by adding it to your application's console schedule in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('smart-upload:prune')->daily();
```

