# Quick Start Guide - Admin Authentication

## 🚀 Quick Setup

### 1. Create Super Admin
```bash
php artisan migrate
php artisan module:seed Admin
```

### 2. Login as Super Admin
**Email**: `amit@orbitflow.in`  
**Phone**: `+918830231066`

### 3. Request OTP
```bash
curl -X POST http://localhost:8000/api/v1/admin/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{
    "email": "amit@orbitflow.in",
    "method": "email",
    "device_fingerprint": "test-device-123",
    "user_agent": "Test Client",
    "ip": "127.0.0.1"
  }'
```

### 4. Verify OTP (Login)
```bash
curl -X POST http://localhost:8000/api/v1/admin/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{
    "email": "amit@orbitflow.in",
    "otp": "123456",
    "device_fingerprint": "test-device-123",
    "user_agent": "Test Client",
    "ip": "127.0.0.1"
  }'
```

## 📱 Test with Frontend

```javascript
// 1. Send OTP
const response = await fetch('/api/v1/admin/auth/send-otp', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'amit@orbitflow.in',
    method: 'email',
    device_fingerprint: 'web-device-123',
    user_agent: navigator.userAgent,
    ip: '127.0.0.1'
  })
});

// 2. Verify OTP
const loginResponse = await fetch('/api/v1/admin/auth/verify-otp', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'amit@orbitflow.in',
    otp: '123456', // Check your email/SMS
    device_fingerprint: 'web-device-123',
    user_agent: navigator.userAgent,
    ip: '127.0.0.1'
  })
});

// 3. Store tokens
if (loginResponse.ok) {
  const data = await loginResponse.json();
  localStorage.setItem('admin_token', data.data.token);
  localStorage.setItem('session_id', data.data.session_id);
}
```

## 🔐 Security Features

- ✅ **No passwords** - OTP-only authentication
- ✅ **Device tracking** - New device alerts
- ✅ **Rate limiting** - Prevents brute force
- ✅ **Session management** - Concurrent session limits
- ✅ **Security monitoring** - Login history & events

## 📊 Monitor Security

```bash
# Check login history
curl -X GET http://localhost:8000/api/v1/admin/security/login-history \
  -H "Authorization: Bearer {token}" \
  -H "X-Session-ID: {session_id}"

# Check active sessions
curl -X GET http://localhost:8000/api/v1/admin/security/active-sessions \
  -H "Authorization: Bearer {token}" \
  -H "X-Session-ID: {session_id}"
```

## 🚨 Important Notes

1. **OTP expires in 10 minutes**
2. **5 failed attempts = 1 hour lockout**
3. **Max 3 concurrent sessions**
4. **New devices require verification**
5. **All login attempts are logged**

## 📞 Need Help?

- Check the full documentation: `/docs/admin-authentication.md`
- Monitor security events in admin dashboard
- Contact system administrator for account issues

---

**Ready to go! 🎉** Your secure admin authentication system is now set up!
