#!/usr/bin/env bash
# Makes mcu_nex_preview, a throwaway copy of mcu_nex for trying pages in the
# browser without touching real data. Every account in the copy gets the test
# password below. Run the preview site with scripts/preview-serve.cmd.
set -e
BIN="${MYSQL_BIN:-/c/xampp/mysql/bin}"
TEST_PASSWORD="preview-test-2026"
"$BIN/mysqldump.exe" -u root --routines --triggers --single-transaction mcu_nex > /tmp/mcu_nex_preview.sql
"$BIN/mysql.exe" -u root -e "DROP DATABASE IF EXISTS mcu_nex_preview; CREATE DATABASE mcu_nex_preview CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
"$BIN/mysql.exe" -u root mcu_nex_preview < /tmp/mcu_nex_preview.sql
rm -f /tmp/mcu_nex_preview.sql
HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$TEST_PASSWORD")
"$BIN/mysql.exe" -u root mcu_nex_preview -e "UPDATE users SET password_hash='$HASH'"
echo "mcu_nex_preview ready"
