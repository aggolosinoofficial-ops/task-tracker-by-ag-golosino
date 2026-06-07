 
 - *This project is self-maintaining via Cron.* 

**Daily (03:00 AM):** - `xml_sync_optimizer.py --compact`
  - `xml_sync_optimizer.py --prune 30`
- **Weekly (Sunday 04:00 AM):**
  - `verify_sync.py`


@echo off
:: Navigate to your project directory
cd /d "C:\xampp\htdocs\itech55\task-tracker-by-ag-golosino"

:: Create a log file if it doesn't exist
echo --- Maintenance Started: %date% %time% --- >> maintenance.log

:: Execute the daily tasks
python xml_sync_optimizer.py --compact >> maintenance.log 2>&1
python xml_sync_optimizer.py --prune 30 >> maintenance.log 2>&1

:: Execute the weekly check (Optional: You could split these into two different files if you want to run weekly tasks only on Sundays)
python verify_sync.py >> maintenance.log 2>&1

echo --- Maintenance Finished: %date% %time% --- >> maintenance.log