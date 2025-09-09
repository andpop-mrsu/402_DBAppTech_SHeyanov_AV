@echo off
set DB=logger.db
set PROGRAM=self-logger.bat
set USERNAME=%USERNAME%
for /f "tokens=1-3 delims=:. " %%a in ("%date% %time%") do (
    set DATETIME=%date% %time:~0,8%
)


sqlite3 %DB% "CREATE TABLE IF NOT EXISTS logs(user TEXT, date TEXT);"


sqlite3 %DB% "INSERT INTO logs(user, date) VALUES('%USERNAME%', '%DATETIME%');"


for /f "tokens=* delims=" %%a in ('sqlite3 %DB% "SELECT COUNT(*) FROM logs;"') do set COUNT=%%a
for /f "tokens=* delims=" %%a in ('sqlite3 %DB% "SELECT date FROM logs ORDER BY date ASC LIMIT 1;"') do set FIRST=%%a

echo Имя программы: %PROGRAM%
echo Количество запусков: %COUNT%
echo Первый запуск: %FIRST%
echo ---------------------------------------------
echo User      ^| Date
echo ---------------------------------------------
sqlite3 %DB% "SELECT user, date FROM logs;"
pause
