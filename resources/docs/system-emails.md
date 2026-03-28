---
title: System Emails
description: How to customise the subject, body, branding, footer, and preview of transactional emails sent by the system.
version: "0.80"
updated: 2026-03-28
tags: [email, settings, compliance]
routes:
  - filament.admin.resources.email-templates.index
  - filament.admin.resources.email-templates.edit
---

# System Emails

System Emails lets you customise every transactional email the application sends. Six emails are available:

| Email | When it sends |
|-------|--------------|
| **Registration Confirmation** | A contact submits an event registration form |
| **Event Cancellation** | An event is marked cancelled in the admin |
| **Event Reminder** | Triggered manually or by a scheduled command |
| **Portal Password Reset** | A portal member requests a password reset |
| **Admin Invitation** | A new admin user is invited via the Users page |
| **Donation Receipt** | Year-end tax receipts are sent from the Donors page |

Each email ships with a default subject and body so it works immediately after installation. You can edit any of them at any time.

---

## Editing an email

Click on an email in the list to open the editor. Changes take effect immediately — the next time that email is triggered, it will use your updated content.

### Content section

**Subject line** — The email subject. Supports tokens (see below).

**Body** — The main message body, edited with the rich text editor. Supports tokens. HTML is preserved so formatting choices carry through to the sent email.

---

## Previewing and test-sending

Each email edit page includes a **Preview & Test** button in the page header. Clicking it opens a three-step wizard:

1. **Preview** — renders the email as it will appear in an inbox, using the current template settings.
2. **Test send** — enter any email address and click **Send Test** to deliver a test copy immediately. The test uses placeholder values where live data would normally appear.
3. **Confirm** — review and close the wizard.

Use the preview wizard to verify layout and branding before making an email live.

---

## Tokens

Tokens are placeholders that are replaced with real values when the email is sent. Write them in your subject, body, or "Why you received this" line.

| Token | Description | Available in |
|-------|-------------|-------------|
| `{{first_name}}` | Recipient's first name | All emails |
| `{{last_name}}` | Recipient's last name | All emails |
| `{{event_title}}` | Name of the event | Registration Confirmation, Event Cancellation, Event Reminder |
| `{{site_name}}` | Your organisation's name (from General Settings) | All emails |
| `{{event_date}}` | Date of the specific event occurrence | Event Reminder |
| `{{event_location}}` | Event venue or location | Event Reminder, Registration Confirmation |
| `{{reset_url}}` | Password reset link | Portal Password Reset |
| `{{invitation_url}}` | Set-password link for new admin | Admin Invitation |
| `{{org_name}}` | Organisation name | Admin Invitation, Donation Receipt |
| `{{contact_name}}` | Donor's full name | Donation Receipt |
| `{{tax_year}}` | Calendar year covered by the receipt | Donation Receipt |
| `{{donations}}` | Fund-by-fund breakdown table | Donation Receipt |
| `{{total}}` | Total donated amount | Donation Receipt |

Available tokens for each email are listed directly below the body editor on the edit page.

---

## Branding section

When no custom HTML template is uploaded, the system uses a default email wrapper with a header and footer. The Branding section controls its appearance.

- **Header colour** — Background colour for the email header bar. Defaults to `#1a56db`.
- **Header image** — Optional logo or banner image displayed in the header.
- **Header headline** — Optional text displayed below the header image.
- **Sender name** — Overrides the site-wide From name for this specific email.
- **Reply-to address** — Sets a reply-to address different from the From address.
- **Mailing address** — Your organisation's physical mailing address, shown in the footer. Required by CAN-SPAM (see compliance section below).
- **"Why you received this" line** — A short sentence explaining why the recipient received this email, shown at the bottom of the footer. Supports tokens.

---

## Custom HTML template

If you need full control over the email's HTML, you can upload a custom template in the **Custom Template** section (collapsed by default).

- Upload an `.html` file.
- Place `{{content}}` in your HTML where you want the rendered body to appear — the system will inject the body text at that location.
- When a custom template is uploaded, **the Branding section is ignored** for that email. All header and footer markup must be in your uploaded file.

To revert to the default wrapper, delete the uploaded file and save.

---

## CAN-SPAM compliance

Even purely transactional emails (confirmations, reminders) are subject to the [CAN-SPAM Act](https://www.ftc.gov/business-guidance/resources/can-spam-act-compliance-guide-business). Key requirements:

- **Physical mailing address** — A valid postal address must appear in every email. Fill in the **Mailing address** field in the Branding section, or include it in your custom template.
- **Accurate From / Reply-To** — The sender name and address must accurately identify your organisation. Configure these in Mail Settings and optionally override per email in the Branding section.
- **Non-deceptive subject line** — The subject must honestly reflect the content of the email.
- **"Why you received this"** — A clear statement explaining why the recipient received the email. Use the footer reason field with a token like: *You received this email because you registered for {{event_title}}.*
- **No unsubscribe link required** — Purely transactional emails (triggered by the recipient's own action) are exempt from the unsubscribe requirement under CAN-SPAM.

For full guidance see the FTC's [CAN-SPAM Act: A Compliance Guide for Business](https://www.ftc.gov/business-guidance/resources/can-spam-act-compliance-guide-business).
