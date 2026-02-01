@echo off
:loop
echo Running Laravel Scheduler...
php artisan schedule:run
timeout /t 60 /nobreak >nul
goto loop
