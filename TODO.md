- [ ] Inspect failing login flow and server responses (already done: login.js, app.py, auth_service.py, login.html, users.xml, xml_service.py)
- [ ] Implement server hardening so XHR login always returns JSON and never crashes into Werkzeug HTML
- [ ] Validate stored password hash before calling check_password_hash
- [ ] Add broad try/except around /login POST JSON branch to return consistent JSON errors
- [ ] Restart app and test login with a known user
- [ ] Verify browser console shows JSON parse success and correct error handling

