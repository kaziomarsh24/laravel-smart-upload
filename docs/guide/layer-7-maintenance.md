# Layer 7: Automated Garbage Collection & Maintenance

### The Prune Command

Temporary uploads (from two-step flows) or incomplete chunk sessions that are abandoned by users are automatically pruned:

```bash
php artisan smart-upload:prune
```

By default, any temporary file or incomplete chunk directory older than 24 hours is deleted.

---

### Custom Expiration Hours

You can specify a custom expiration threshold (in hours):

```bash
# Prune files older than 6 hours:
php artisan smart-upload:prune --hours=6
```

---

### Automatic Scheduler

Smart Upload automatically registers this command with Laravel's Task Scheduler to run daily. As long as your server has the standard Laravel cron entry (`* * * * * php artisan schedule:run`), maintenance is completely hands-free!

