---
title: Two-Factor Authentication
description: How admins enroll in and sign in with two-factor authentication (2FA), including the QR-code setup, recovery codes, and what to do if you lose your device.
version: "0.359"
updated: 2026-06-12
tags: [settings, security, login, 2fa, authentication]
standalone: true
category: settings
---

# Two-Factor Authentication

Two-factor authentication (2FA) adds a second step to signing in to the admin panel. After your password, you also enter a short code from an authenticator app on your phone. Even if someone learns your password, they can't sign in without your device.

2FA is **required** for every admin account. The first time you sign in after it's switched on, you'll be guided through a one-time setup before you can reach the dashboard.

## What you need

An authenticator app on your phone or computer, such as:

- Google Authenticator
- Microsoft Authenticator
- 1Password
- Authy

Any app that supports time-based one-time codes (TOTP) works.

## Setting up 2FA (one time)

1. Sign in with your email and password as usual.
2. You'll land on the **Set up two-factor authentication** screen.
3. Open your authenticator app and scan the QR code shown on screen. If you can't scan it, tap "enter a key manually" in your app and type the key printed below the QR code.
4. **Save your recovery codes.** They're shown only once on this screen. Store them somewhere safe (a password manager is ideal) — each one lets you sign in once if you lose your phone.
5. Your app now shows a rotating 6-digit code. Enter the current code in the **Authentication code** field and choose **Confirm & enable**.

That's it — setup is complete and you're taken to the dashboard.

## Signing in after setup

Each time you sign in:

1. Enter your email and password.
2. On the **Two-factor authentication** screen, enter the current 6-digit code from your authenticator app and choose **Verify**.

## If you lose your device

On the verification screen, enter one of the **recovery codes** you saved during setup instead of an app code. Each recovery code works only once. After using one, sign in and re-run setup if you've permanently lost your authenticator, so you have a fresh device and a fresh set of recovery codes.

If you've lost both your authenticator app and your recovery codes, another administrator can help: ask them to delete and recreate your user account, which clears your 2FA so you can enroll again from scratch.

## Notes

- 2FA protects the admin panel only. The public website and the member portal are separate.
- The public demo site skips 2FA so visitors can explore with one click — this never applies to a real install.
