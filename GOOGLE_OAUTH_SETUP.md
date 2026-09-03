# Google OAuth Setup Instructions

To enable Google account creation for your MediStock RHU application, follow these steps:

## 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable Google+ API and Google OAuth2 API

## 2. Create OAuth 2.0 Credentials

1. Navigate to APIs & Services → Credentials
2. Click "Create Credentials" → "OAuth client ID"
3. Configure the consent screen if prompted
4. Choose "Web application" as application type
5. Add these authorized redirect URIs:
   - `http://localhost:8000/auth/google/callback` (for local development)
   - `https://yourdomain.com/auth/google/callback` (for production)

## 3. Get Your Credentials

After creating the OAuth client, you'll receive:
- **Client ID**: Copy this to your `.env` file as `GOOGLE_CLIENT_ID`
- **Client Secret**: Copy this to your `.env` file as `GOOGLE_CLIENT_SECRET`

## 4. Update Environment Variables

Add these to your `.env` file:

```env
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

## 5. Test the Integration

1. Clear your config cache: `php artisan config:clear`
2. Visit the login page: `http://localhost:8000/login`
3. Click "Sign in with Google"
4. Complete the Google authentication flow
5. Enter the OTP (check your Laravel logs for the OTP code)

## Notes

- For production, use HTTPS URLs
- The OTP system currently logs to `storage/logs/laravel.log`
- To implement real email OTP sending, configure your mail settings in `.env`
- New Google users will be assigned the default "staff" role

## OTP System

The current OTP implementation:
- Generates a 6-digit code
- Stores it in cache for 10 minutes
- Logs the OTP to Laravel logs for testing
- For production, implement email notification to send the actual OTP