param(
    [string]$MysqlPath = "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe",
    [string]$User = "root"
)

$ErrorActionPreference = "Stop"
if (!(Test-Path -LiteralPath $MysqlPath)) {
    throw "mysql.exe was not found at $MysqlPath"
}

$sql = @"
START TRANSACTION;
DELETE FROM time_zone_name WHERE Name IN ('GMT','UTC','Africa/Nairobi');
INSERT INTO time_zone (Use_leap_seconds) VALUES ('N'),('N'),('N');
SET @gmt := LAST_INSERT_ID();
SET @utc := @gmt + 1;
SET @nairobi := @gmt + 2;
INSERT INTO time_zone_name (Name, Time_zone_id)
VALUES ('GMT', @gmt),('UTC', @utc),('Africa/Nairobi', @nairobi);
INSERT INTO time_zone_transition_type (Time_zone_id, Transition_type_id, Offset, Is_DST, Abbreviation)
VALUES (@gmt,0,0,0,'GMT'),(@utc,0,0,0,'UTC'),(@nairobi,0,10800,0,'EAT');
INSERT INTO time_zone_transition (Time_zone_id, Transition_time, Transition_type_id)
VALUES (@gmt,-2147483648,0),(@utc,-2147483648,0),(@nairobi,-2147483648,0);
COMMIT;
SELECT Name, CONVERT_TZ('2000-01-01 00:00:00','GMT',Name) AS converted
FROM time_zone_name
WHERE Name IN ('GMT','UTC','Africa/Nairobi');
"@

$sql | & $MysqlPath --user=$User mysql
Write-Host "AfyaDesk local MySQL timezone seed completed. Run php bin/console database:enable_timezones after this script."
