<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

## Contact Form Configuration

The contact form system sends notification emails to a configurable admin email address. You can set this in your `.env` file:

```env
MAIL_CONTACT_FORM_ADMIN_EMAIL=your-admin@example.com
```

If not set, it defaults to `netaniadelaiya@gmail.com`.

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Mailgun Email Configuration

This application uses Mailgun HTTP API for sending emails with automatic failover to log-based email handling.

### Environment Configuration

Add the following environment variables to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=failover
MAIL_FROM_ADDRESS="no-reply@netaniadelaiya.com"
MAIL_FROM_NAME="Cloud Haven"

# Mailgun Configuration
MAILGUN_DOMAIN="mg.netaniadelaiya.com"
MAILGUN_SECRET="your-mailgun-api-key-here"
MAILGUN_ENDPOINT="api.mailgun.net"  # Use "api.eu.mailgun.net" for EU region
```

### Testing Email Sending

To test email sending in development:

1. Set `MAIL_MAILER=log` in your `.env` file to log emails instead of sending them
2. Check `storage/logs/laravel.log` to verify email content
3. For actual Mailgun testing, ensure your API key is valid and DNS is configured

### Production Deployment

After deploying to production:

1. Run `composer install --optimize-autoloader --no-dev`
2. Clear and optimize Laravel caches:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
3. Ensure the queue worker is running for processing queued emails:
   ```bash
   php artisan queue:work
   ```

### Rollback Strategy

If you need to rollback to SMTP:

1. Change `MAIL_MAILER=smtp` in `.env`
2. Configure SMTP settings in `.env`
3. Clear config cache: `php artisan config:clear`

The failover configuration ensures that if Mailgun is unreachable, emails will be logged instead of causing user-facing errors.

### DMARC Alignment

The application uses DMARC "relaxed" alignment, which allows emails to have a "From" address of `@netaniadelaiya.com` while being signed by the `mg.netaniadelaiya.com` subdomain. This is a standard practice for transactional email services and ensures proper email deliverability.
