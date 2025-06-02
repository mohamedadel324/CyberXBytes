#!/bin/bash
echo "Laravel Scheduler Status"
echo "----------------------"
echo "Cron job status:"
crontab -l | grep "artisan schedule:run"
echo ""
echo "Systemd service status:"
systemctl status laravel-scheduler.service
echo ""
echo "Systemd timer status:"
systemctl status laravel-scheduler.timer
echo ""
echo "Recent log entries:"
tail -n 20 /var/www/Laravel/storage/logs/scheduler.log
