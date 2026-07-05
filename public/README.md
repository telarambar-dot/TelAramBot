# Rubika webhook endpoint (public)

Place `hook.php` under your web server `public/` folder. Use the form at `hook.php` or call with query params to set webhook:

Example:

```
https://yourhost/hook.php?set=1&token=YOUR_BOT_TOKEN&url=https://yourhost/hook.php&type=ReceiveUpdate&auto_reply=1
```

Prefer putting `RUBIKA_BOT_TOKEN` in a `.env` file at the project root or set it as an environment variable.

If you install dependencies with Composer (`composer install`), the project will use `vendor/autoload.php` automatically.
