# Admin Authentication System Documentation

## Overview

This document explains how to use the secure OTP-based admin authentication system for the Ticket Management application.

## Features

- **OTP-based authentication** (no passwords)
- **Multi-factor authentication** (email & SMS)
- **Device fingerprinting** and tracking
- **Session management** with concurrent limits
- **Advanced security monitoring**
- **Rate limiting** and brute force protection
- **Geolocation tracking**
- **Security event logging**

## Setup

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Run Seeder (Create Super Admin)

```bash
php artisan module:seed Admin
```

This creates the super admin account:
- **Email**: `amit@orbitflow.in`
- **Phone**: `+918830231066`
- **Name**: Amit Super Admin

## API Endpoints

### Authentication Endpoints

#### Send OTP
```http
POST /api/v1/admin/auth/send-otp
```

**Request Body:**
```json
{
  "email": "amit@orbitflow.in",
  "method": "email", // or "sms", "both"
  "device_fingerprint": "unique_device_id",
  "user_agent": "Mozilla/5.0...",
  "ip": "192.168.1.100"
}
```

**Response:**
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "delivery_methods": ["email"],
  "expires_at": "2024-03-18T10:30:00Z"
}
```

#### Verify OTP (Login)
```http
POST /api/v1/admin/auth/verify-otp
```

**Request Body:**
```json
{
  "email": "amit@orbitflow.in",
  "otp": "123456",
  "device_fingerprint": "unique_device_id",
  "user_agent": "Mozilla/5.0...",
  "ip": "192.168.1.100",
  "location": {
    "country": "United States",
    "country_code": "US",
    "region": "California",
    "city": "San Francisco",
    "latitude": 37.7749,
    "longitude": -122.4194,
    "timezone": "America/Los_Angeles"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin": {
      "id": 1,
      "uuid": "uuid-here",
      "email": "amit@orbitflow.in",
      "first_name": "Amit",
      "last_name": "Super Admin",
      "full_name": "Amit Super Admin",
      "is_verified": true,
      "last_login_at": "2024-03-18T09:00:00Z",
      "security_score": 95
    },
    "token": "jwt_token_here",
    "session_id": "session_uuid_here",
    "expires_at": "2024-03-19T09:00:00Z",
    "new_device": false,
    "security_warnings": []
  }
}
```

#### Logout
```http
POST /api/v1/admin/auth/logout
```

**Headers:**
```
Authorization: Bearer {token}
X-Session-ID: {session_id}
```

### Protected Endpoints

All protected endpoints require:
- `Authorization: Bearer {token}` header
- `X-Session-ID: {session_id}` header

#### Get Profile
```http
GET /api/v1/admin/auth/profile
```

#### Login History
```http
GET /api/v1/admin/security/login-history
```

#### Security Events
```http
GET /api/v1/admin/security/security-events
```

#### Active Sessions
```http
GET /api/v1/admin/security/active-sessions
```

#### Revoke Session
```http
DELETE /api/v1/admin/security/sessions/{session_id}
```

## Security Features

### Rate Limiting

- **OTP Requests**: 10 per day, 1-minute cooldown
- **Failed Logins**: 5 attempts = 1-hour lock
- **Brute Force**: 10 attempts = 15-minute block
- **Session Limits**: Configurable per admin

### Device Security

- **Device Fingerprinting**: Unique device identification
- **Known Devices**: Trusted device management
- **New Device Alerts**: Notifications for unknown devices
- **Device Verification**: Optional requirement for new devices

### Monitoring

- **Login History**: Last 50 login attempts
- **Security Events**: Comprehensive event logging
- **Geolocation**: IP-based location tracking
- **Suspicious Activity**: Automated threat detection

## Usage Examples

### 1. Basic Login Flow

```javascript
// Step 1: Request OTP
const sendOTP = async () => {
  const response = await fetch('/api/v1/admin/auth/send-otp', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: 'amit@orbitflow.in',
      method: 'both',
      device_fingerprint: generateDeviceFingerprint(),
      user_agent: navigator.userAgent,
      ip: await getClientIP()
    })
  });
  
  const data = await response.json();
  console.log('OTP sent:', data);
};

// Step 2: Verify OTP and Login
const verifyOTP = async (otp) => {
  const response = await fetch('/api/v1/admin/auth/verify-otp', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: 'amit@orbitflow.in',
      otp: otp,
      device_fingerprint: generateDeviceFingerprint(),
      user_agent: navigator.userAgent,
      ip: await getClientIP(),
      location: await getLocation()
    })
  });
  
  const data = await response.json();
  if (data.success) {
    // Store token and session ID
    localStorage.setItem('admin_token', data.data.token);
    localStorage.setItem('session_id', data.data.session_id);
    console.log('Login successful:', data.data.admin);
  }
};

// Step 3: Make authenticated requests
const getProfile = async () => {
  const response = await fetch('/api/v1/admin/auth/profile', {
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
      'X-Session-ID': localStorage.getItem('session_id')`
    }
  });
  
  const data = await response.json();
  console.log('Profile:', data.data.admin);
};
```

### 2. Device Fingerprinting

```javascript
function generateDeviceFingerprint() {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  ctx.textBaseline = 'top';
  ctx.font = '14px Arial';
  ctx.fillText('Device fingerprint', 2, 2);
  
  const fingerprint = [
    navigator.userAgent,
    navigator.language,
    screen.width + 'x' + screen.height,
    new Date().getTimezoneOffset(),
    canvas.toDataURL()
  ].join('|');
  
  return btoa(fingerprint);
}
```

### 3. Error Handling

```javascript
const handleAuthError = (error) => {
  if (error.status === 429) {
    console.log('Too many requests - please wait');
  } else if (error.status === 423) {
    console.log('Account locked - please try again later');
  } else if (error.status === 401) {
    console.log('Invalid OTP or expired session');
  } else {
    console.log('Authentication error:', error.message);
  }
};
```

## Security Best Practices

### For Frontend

1. **Store tokens securely** (use httpOnly cookies if possible)
2. **Implement auto-logout** on session expiration
3. **Validate device fingerprints** on each request
4. **Handle security warnings** appropriately
5. **Implement proper error handling**

### For Backend

1. **Monitor failed login attempts**
2. **Set up security alerts** for suspicious activity
3. **Regular security reviews** of admin accounts
4. **Implement proper logging** and monitoring
5. **Keep OTP delivery methods** secure

## Troubleshooting

### Common Issues

1. **OTP Not Received**
   - Check email/SMS configuration
   - Verify admin's contact details
   - Check rate limiting status

2. **Account Locked**
   - Wait for lockout period to expire
   - Contact system administrator
   - Check failed login attempts

3. **Session Expired**
   - Request new OTP
   - Check session timeout settings
   - Verify device fingerprint

4. **New Device Blocked**
   - Verify device fingerprint generation
   - Check device verification settings
   - Contact admin for device approval

### Debug Mode

Enable debug logging by setting:
```env
APP_DEBUG=true
LOG_CHANNEL=security
```

## Support

For technical support or security concerns:
- Check application logs
- Review security events
- Contact system administrator
- Monitor admin dashboard for alerts

---

**Security Note**: This system uses OTP-based authentication for enhanced security. Never share OTP codes with anyone and always verify the authenticity of login requests.
