@echo off
rem Preview site on http://localhost:8001 using the mcu_nex_preview copy.
set DB_DATABASE=mcu_nex_preview
cd /d "%~dp0..\public"
php -S 127.0.0.1:8001 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
