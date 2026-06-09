 
 - *This project is self-maintaining via Cron.* 

**Daily (e.g., 03:00 AM):**
  - `xml_sync_optimizer.py --compact`
  - `xml_sync_optimizer.py --prune 30`
- **Weekly (Sunday 04:00 AM):**
  - *(No specific weekly tasks are defined for the XML-Only Python Flask application at this time.)*

@echo off
:: Navigate to your project directory
cd /d "C:\xampp\htdocs\itech55\task-tracker-by-ag-golosino"

:: Execute the daily tasks
python xml_sync_optimizer.py --compact >> maintenance.log 2>&1
python xml_sync_optimizer.py --prune 30 >> maintenance.log 2>&1

:: Execute the weekly check (Optional: You could split these into two different files if you want to run weekly tasks only on Sundays)
:: The `verify_sync.py` script is no longer relevant in the XML-Only architecture.