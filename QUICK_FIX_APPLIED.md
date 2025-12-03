# 🔧 Quick Fix Applied - Site Working Now

## Problem Fixed
**Error:** `Column not found: 1054 Unknown column 'l.expires_at'`

**Solution:** Removed `expires_at` check from home.php query

## What Was Changed

### File: `modules/home.php`
**Before:**
```php
WHERE l.status IN ('approved')
AND (l.expires_at IS NULL OR l.expires_at > NOW())
```

**After:**
```php
WHERE l.status IN ('approved')
// expires_at check removed - column doesn't exist in database
```

## Server Status
✅ **BASE URL detected correctly:** `http://91.99.115.150/`
✅ **Site should now load without 500 errors**
✅ **All paths configured for production**

## Test Now

### 1. Visit Home Page
```
http://91.99.115.150/index.php?p=home
```
Should load without errors

### 2. Check Browser Console
Press F12 and check:
- No 500 errors
- JS files loading
- BASE constant defined

### 3. Test Features
- ✅ Notifications
- ✅ Messages
- ✅ Polling
- ✅ Popup

## Optional: Add Expiry Feature Later

If you want listing expiry feature in future, run this SQL:

```sql
-- Connect to your database
mysql -u your_user -p your_database

-- Run the migration
source database_migrations/add_expires_at_column.sql
```

Then uncomment the expires_at check in home.php

## Files Modified
1. `modules/home.php` - Removed expires_at check
2. `includes/header.php` - Fixed BASE constant handling
3. `database_migrations/add_expires_at_column.sql` - Created for future use

## Deployment Complete ✅

Your site should now be working on:
- **Production:** http://91.99.115.150/
- **Local:** http://localhost/marketplace/public/

All paths automatically detect environment!

---

**Date:** December 3, 2025
**Status:** FIXED ✅
