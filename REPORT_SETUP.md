# Automated Email Reporting System - Complete Setup Guide

## ✅ What's Been Created

### Backend (Laravel)
1. **Database Migration**: `report_settings` table with email, frequency, and status fields
2. **ReportSetting Model**: Eloquent model with user relationship
3. **ReportSettingsController**: API endpoints for managing settings
4. **ExpenseReportMail Mailable**: Professional HTML email template
5. **SendScheduledReports Command**: Automated command to send reports
6. **Kernel.php Scheduler**: Cron jobs for daily/weekly/monthly reports
7. **API Routes**: Three new endpoints for report management

### Frontend (Next.js)
1. **Settings Page**: `/dashboard/settings` with Shadcn UI components
2. **Form Inputs**: Email, frequency dropdown, enable/disable toggle
3. **Test Email Button**: Send test emails to preview the design
4. **Toast Notifications**: Success/error feedback using Sonner

---

## 🔧 Step 1: Gmail SMTP Configuration

### Generate Google App Password

1. **Enable 2-Step Verification**:
   - Go to [myaccount.google.com/security](https://myaccount.google.com/security)
   - Click "2-Step Verification"
   - Follow the prompts

2. **Generate App Password**:
   - Go back to Security page
   - Scroll down to "App passwords"
   - Select "Mail" and "Windows Computer" (or your OS)
   - Click "Generate"
   - Copy the 16-character password

### Update Your `.env` File

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=xxxx xxxx xxxx xxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

# Queue Configuration (Optional but Recommended)
QUEUE_CONNECTION=database
```

**Replace these values:**
- `MAIL_USERNAME`: Your Gmail address
- `MAIL_PASSWORD`: The 16-character password from Step 2
- `MAIL_FROM_ADDRESS`: Same as MAIL_USERNAME
- `MAIL_FROM_NAME`: Your app name (e.g., "Expense Tracker")

---

## 🗄️ Step 2: Run Database Migration

```bash
cd c:\Users\milan_m\Music\APP-NEW\backend

# Run the new migration
php artisan migrate

# Or if you need to refresh (development only)
php artisan migrate:refresh --seed
```

---

## 📧 Step 3: Test Email Configuration

### Option A: Send Test Email from Settings Page

1. Go to `/dashboard/settings` in your Next.js app
2. Enter your email address
3. Click "Send Test Email"
4. Check your inbox for the professional report

### Option B: Test via Artisan Command

```bash
php artisan tinker

# In the Tinker shell:
$user = User::find(1);
Mail::to('your-email@gmail.com')->send(new \App\Mail\ExpenseReportMail([
    'user_name' => $user->name,
    'period' => 'Test Period',
    'total_expenses' => 5000,
    'total_income' => 10000,
    'net_savings' => 5000,
    'top_expenses' => $user->expenses()->limit(3)->get(),
    'category_breakdown' => [],
    'bank_balance' => 25000,
    'fund_balance' => 15000,
]));
```

---

## ⏰ Step 4: Set Up Task Scheduler

### For Development (Manual Testing)

```bash
# Run the scheduler in the foreground
php artisan schedule:work
```

This will continuously check and execute scheduled tasks. Leave this running while developing.

### For Production (Server)

Add this to your server's crontab (run `crontab -e`):

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

This runs the Laravel scheduler every minute, which then decides what to execute based on the schedule.

---

## 🚀 Step 5: Enable Queue Processing (Recommended)

Email sending works without this, but queues prevent lag when sending to multiple users.

### Create Queue Table

```bash
php artisan queue:table
php artisan migrate
```

### Start Queue Worker

```bash
# In a separate terminal window, keep this running:
php artisan queue:work --timeout=300
```

### Update Mailable (if using queues)

The `ExpenseReportMail` already supports queuing. If you want to queue emails:

```php
// In ReportSettingsController or SendScheduledReports
Mail::to($email)->queue(new ExpenseReportMail($data));
```

---

## 📋 Step 6: Configure Sidebar Link (Optional)

Add the Settings link to your sidebar navigation:

```tsx
// In your BottomNav.tsx or Sidebar component:
{
  icon: Settings,
  label: 'Settings',
  href: '/dashboard/settings',
}
```

---

## 🔄 Automated Sending Schedule

The reports will automatically send at:

- **Daily**: Every day at **9:00 PM** (21:00)
- **Weekly**: Every **Monday at 9:00 AM**
- **Monthly**: **1st of each month at 9:00 AM**

*(Timezone: Asia/Colombo - Adjust in `Kernel.php` if needed)*

---

## 📊 API Endpoints

### Get Settings
```bash
GET /api/report-settings
```

### Update Settings
```bash
POST /api/report-settings
Content-Type: application/json

{
  "report_email": "user@example.com",
  "frequency": "weekly",
  "is_enabled": true
}
```

### Send Test Email
```bash
POST /api/report-settings/send-test
Content-Type: application/json

{
  "report_email": "user@example.com"
}
```

---

## 🐛 Troubleshooting

### "SMTP authentication failed"
- Verify MAIL_USERNAME and MAIL_PASSWORD in `.env`
- Check that the Gmail app password is correct (should be 16 characters)
- Make sure 2-Step Verification is enabled on your Google Account

### "Mailable not found"
- Run `php artisan make:mail ExpenseReportMail` if the file is missing
- Ensure the view file exists at `resources/views/emails/expense-report.blade.php`

### Emails not sending automatically
- Check that the scheduler is running (`php artisan schedule:work`)
- Verify the command: `php artisan reports:send --frequency=daily`
- Check Laravel logs: `storage/logs/laravel.log`

### Queue not working
- Start the queue worker: `php artisan queue:work`
- Check queue jobs: `php artisan queue:failed`

---

## ✨ What Users Will Receive

The professional email includes:

✅ **Header** - "Your Daily/Weekly/Monthly Spending Summary"
✅ **Summary Box** - Total expenses & net savings
✅ **Category Breakdown** - Table with spending per category
✅ **Top 3 Expenses** - Highest spending items
✅ **Account Status** - Bank & fund source balances
✅ **View Dashboard Link** - Direct link to the app
✅ **Responsive Design** - Looks great on mobile & desktop
✅ **Professional Styling** - Gradient headers, clean tables

---

## 📝 Database Schema

```sql
CREATE TABLE report_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL UNIQUE,
    report_email VARCHAR(255) NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
    is_enabled BOOLEAN DEFAULT false,
    last_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 🎯 Next Steps

1. ✅ Update `.env` with Gmail SMTP credentials
2. ✅ Run `php artisan migrate` to create the table
3. ✅ Test by going to `/dashboard/settings` and clicking "Send Test Email"
4. ✅ Keep `php artisan schedule:work` running in development
5. ✅ Deploy to production with proper crontab configuration

**You're all set! 🎉 Your users will now receive beautiful automated spending reports!**
