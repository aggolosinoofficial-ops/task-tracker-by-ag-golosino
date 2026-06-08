import json
import urllib.request
import urllib.parse

BASE = 'http://127.0.0.1:5000'

# 1) Get login page to obtain CSRF token
html = urllib.request.urlopen(BASE + '/login').read().decode('utf-8', errors='ignore')
start = html.find('id="csrf_token"')
if start == -1:
    raise SystemExit('Could not find csrf_token field in login HTML')
# value="..." after csrf_token
val_idx = html.find('value="', start)
if val_idx == -1:
    raise SystemExit('Could not find csrf_token value attribute')
val_start = val_idx + len('value="')
val_end = html.find('"', val_start)
csrf = html[val_start:val_end]
print('CSRF token obtained:', csrf[:10] + '...')

# 2) POST XHR login attempt
payload = urllib.parse.urlencode({
    'username': 'admin123',
    'password': 'wrong',
    'csrf_token': csrf,
}).encode('utf-8')

req = urllib.request.Request(
    BASE + '/login',
    data=payload,
    headers={
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    method='POST',
)

try:
    resp = urllib.request.urlopen(req)
    body = resp.read().decode('utf-8', errors='ignore')
    print('Status:', resp.status)
    print('Content-Type:', resp.getheader('Content-Type'))
    print('Body snippet:', body[:200])
except urllib.error.HTTPError as e:
    body = e.read().decode('utf-8', errors='ignore')
    print('HTTPError Status:', e.code)
    print('HTTPError Content-Type:', e.headers.get('Content-Type'))
    print('HTTPError Body snippet:', body[:400])
    raise


try:
    data = json.loads(body)
    print('Parsed JSON:', data)
except Exception as e:
    raise SystemExit('Failed to parse JSON from server: ' + str(e))

