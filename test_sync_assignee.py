import requests  # type: ignore
from lxml import etree  # type: ignore

BASE_URL = "http://127.0.0.1:5000"
LOGIN_URL = f"{BASE_URL}/login"
SYNC_URL = f"{BASE_URL}/api/sync_tasks"

# 1. Test Credentials (User 2 / ID 4)
payload = {
    "username": "user2",
    "password": "password123" # Assuming standard test password
}

session = requests.Session()

def test_assignee_sync():
    print("[Test] Attempting to login as user2 (Assignee)...")
    
    # Initial request to get CSRF cookie
    session.get(LOGIN_URL)
    csrf_token = session.cookies.get('csrf_token') # Flask-WTF often sets this
    
    # Login
    # Note: app.py handles JSON or Form. We'll use form.
    r = session.post(LOGIN_URL, data=payload)
    if r.status_code != 200 or "dashboard" not in r.url.lower() and "success" not in r.text:
        print("❌ Login failed. Check credentials.")
        return

    print("✅ Login successful.")

    # 2. Prepare XML Payload
    # Task 3 is owned by 5, but assigned to 4.
    xml_payload = """<?xml version='1.0' encoding='UTF-8'?>
    <tasks>
      <task>
        <id>3</id>
        <user_id>5</user_id>
        <assigned_to>4</assigned_to>
        <title>Fix bug in authentication - UPDATED BY ASSIGNEE</title>
        <status>in_progress</status>
      </task>
    </tasks>
    """

    print("[Test] Sending sync request for assigned task...")
    headers = {'Content-Type': 'text/xml'}
    response = session.post(SYNC_URL, data=xml_payload, headers=headers)

    print(f"Status Code: {response.status_code}")
    print(f"Response: {response.text}")

if __name__ == "__main__":
    test_assignee_sync()