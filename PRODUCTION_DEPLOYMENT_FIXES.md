# 🚀 Production/UAT Deployment Fixes

This guide addresses common deployment issues that occur in production and UAT environments.

## 🚨 Critical Issues Fixed

### 1. Carbon Date Type Error
**Problem**: `Carbon::rawAddUnit(): Argument #3 ($value) must be of type int|float, string given`

**Root Cause**: Environment variables are always strings, but Carbon methods expect integers/floats.

**Solution**: ✅ **FIXED** - Added type casting in `config/booking.php`:
```php
'reservation_hold_duration_hours' => (int) env('BOOKING_RESERVATION_HOLD_HOURS', 2),
'scheduler_interval_minutes' => (int) env('BOOKING_SCHEDULER_INTERVAL_MINUTES', 30),
'proof_rejection_grace_period_days' => (int) env('BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS', 2),
```

### 2. Storage Permission Error
**Problem**: `Permission denied` when writing to `/var/www/html/storage/logs/laravel-*.log`

**Root Cause**: Web server user lacks write permissions to storage directory.

**Solution**: ✅ **FIXED** - Integrated into existing deployment scripts.

## 🔧 Quick Fix Commands

### For Docker Environments (Recommended)
```bash
# The permission fix is now automatically handled by deployment scripts
# Just run your normal deployment:
./scripts/deploy-backend-production.sh    # For production
./scripts/deploy-backend-uat.sh          # For UAT
```

### Manual Permission Fix (if needed)
```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/storage
sudo chown -R www-data:www-data /var/www/html/bootstrap/cache

# Set permissions
sudo chmod -R 775 /var/www/html/storage
sudo chmod -R 775 /var/www/html/bootstrap/cache

# Ensure log files are writable
sudo chmod 664 /var/www/html/storage/logs/laravel-*.log
```

## 📋 Environment Variables

Ensure these are set correctly in your `.env` file:

```env
# Booking Configuration
BOOKING_RESERVATION_HOLD_HOURS=2
BOOKING_SCHEDULER_INTERVAL_MINUTES=30
BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS=2
BOOKING_DOWNPAYMENT_PERCENT=0.5
```

## 🐳 Docker-Specific Notes

If using Docker, ensure your container has the correct user:

```dockerfile
# In your Dockerfile
USER www-data
# OR
RUN chown -R www-data:www-data /var/www/html/storage
```

## 🔍 Verification Steps

After applying fixes, verify:

1. **Config Values**: Check that config values are integers, not strings
   ```bash
   php artisan tinker
   >>> config('booking.reservation_hold_duration_hours')
   # Should return: 2 (integer, not "2")
   ```

2. **Storage Permissions**: Verify storage is writable
   ```bash
   ls -la storage/logs/
   # Should show: -rw-rw-r-- (664 permissions)
   ```

3. **Test Booking Creation**: Try creating a booking to ensure no Carbon errors

## 🚀 Deployment Checklist

- [ ] Deploy using updated scripts (permissions are fixed automatically)
- [ ] Verify config values are properly typed
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Test booking creation
- [ ] Check logs are being written
- [ ] Verify scheduler is working

## 📞 Troubleshooting

### Still Getting Carbon Errors?
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Restart your web server
sudo systemctl restart nginx
sudo systemctl restart apache2
# OR restart Docker containers
```

### Still Getting Permission Errors?
```bash
# Check current ownership
ls -la storage/

# Check web server user
ps aux | grep nginx
ps aux | grep apache
# OR for Docker
docker exec -it container-name id
```

## 🎯 Prevention

To prevent these issues in future deployments:

1. **Always use type casting** in config files for numeric values
2. **Set proper permissions** during initial deployment
3. **Use deployment scripts** that handle permissions automatically
4. **Test in staging** before production deployment

---

**Last Updated**: $(date)
**Status**: ✅ All critical issues resolved
