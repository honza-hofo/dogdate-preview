# Records of Processing Activities (GDPR Art. 30)

## DogDate - Makej s MANMATem

**Document version:** 1.0
**Date:** 2026-03-29
**Last reviewed:** 2026-03-29

---

## Data Controller

| Field | Value |
|-------|-------|
| **Organization** | MANMAT s.r.o. |
| **Registration No. (ICO)** | 03166236 |
| **Address** | K Drubezarne 220, 549 54 Police nad Metuji, Czech Republic |
| **Contact Person** | Jan Formanek |
| **Email** | formanek@manmat.cz |
| **Phone** | +420 730 858 435 |
| **Data Protection Contact** | formanek@manmat.cz |

---

## Processing Activity 1: User Registration & Authentication

| Field | Description |
|-------|-------------|
| **Purpose** | Create user accounts, authenticate users, manage login sessions |
| **Legal Basis** | Art. 6(1)(b) GDPR - Performance of a contract (provision of service) |
| **Categories of Data Subjects** | Registered users (persons aged 18+) |
| **Categories of Personal Data** | Name, email address, password (bcrypt hash), age, city, IP address, session identifiers |
| **Recipients** | Hosting provider (data processor) |
| **Transfers to Third Countries** | None (EU/EEA only) |
| **Retention Period** | Until account deletion by user. Password hashes deleted immediately upon account deletion. Rate limit logs: 24 hours. |
| **Security Measures** | Passwords stored as bcrypt hashes; HTTPS encryption; rate limiting on login attempts; CSRF protection; secure session cookies (HttpOnly, SameSite=Lax) |

---

## Processing Activity 2: User Profile Management

| Field | Description |
|-------|-------------|
| **Purpose** | Display user profiles to other registered users for the purpose of finding walking partners |
| **Legal Basis** | Art. 6(1)(b) GDPR - Performance of a contract |
| **Categories of Data Subjects** | Registered users |
| **Categories of Personal Data** | Name, age, city, bio (free text), profile photo, availability times, rating |
| **Recipients** | Other registered users of DogDate (limited view - no email or exact GPS); Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | Until account deletion. Profile data deleted within 30 days of deletion request. |
| **Security Measures** | Authentication required to view profiles; email never exposed to other users; input sanitization against XSS |

---

## Processing Activity 3: Dog Profile Management

| Field | Description |
|-------|-------------|
| **Purpose** | Store and display dog information to facilitate matching of compatible walking partners |
| **Legal Basis** | Art. 6(1)(b) GDPR - Performance of a contract |
| **Categories of Data Subjects** | Registered users (as dog owners) |
| **Categories of Personal Data** | Dog name, breed, size, personality type, walk distance preference, dog photo |
| **Recipients** | Other registered users; Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | Until account deletion. Deleted via CASCADE when user account is removed. |
| **Security Measures** | File upload validation (type, size); unique filenames to prevent enumeration |

---

## Processing Activity 4: Location/GPS Processing for Matching

| Field | Description |
|-------|-------------|
| **Purpose** | Calculate distances between users to enable proximity-based matching for dog walks |
| **Legal Basis** | Art. 6(1)(a) GDPR - Explicit consent (obtained during registration) |
| **Categories of Data Subjects** | Registered users who have given location consent |
| **Categories of Personal Data** | Latitude, longitude, timestamp of last location update |
| **Recipients** | Other users see ONLY approximate distance (km), never exact coordinates; Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | **Maximum 48 hours.** GPS coordinates are automatically nullified by the cleanup script after 48 hours of inactivity. |
| **Security Measures** | Exact coordinates never exposed to other users; automatic deletion after 48h; consent can be revoked; server-side distance calculation (Haversine formula) |

---

## Processing Activity 5: Photo Storage and Display

| Field | Description |
|-------|-------------|
| **Purpose** | Allow users to upload and display profile photos and dog photos |
| **Legal Basis** | Art. 6(1)(a) GDPR - Consent (photos are optional; explicit consent for photos obtained at registration) |
| **Categories of Data Subjects** | Registered users who upload photos |
| **Categories of Personal Data** | Photographs (profile avatar, dog photos) |
| **Recipients** | Other registered users; Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | Until account deletion. Photo files physically deleted from server within 30 days. Backups purged within 90 days. |
| **Security Measures** | File type validation; max upload size (10MB); unique random filenames; no facial recognition or biometric processing; photos deleted from filesystem on account removal |

---

## Processing Activity 6: Messaging Between Users

| Field | Description |
|-------|-------------|
| **Purpose** | Enable direct communication between matched users to coordinate dog walks |
| **Legal Basis** | Art. 6(1)(b) GDPR - Performance of a contract |
| **Categories of Data Subjects** | Registered users who have an active match |
| **Categories of Personal Data** | Message content (free text), sender ID, timestamp |
| **Recipients** | Only the two participants in the conversation; Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | **Maximum 12 months.** Messages are automatically deleted by the cleanup script after 12 months. |
| **Security Measures** | Messages only accessible to match participants; authentication required; input sanitization; automatic deletion after retention period |

---

## Processing Activity 7: Match Management

| Field | Description |
|-------|-------------|
| **Purpose** | Track connections between users (pending, confirmed, walk planned) |
| **Legal Basis** | Art. 6(1)(b) GDPR - Performance of a contract |
| **Categories of Data Subjects** | Registered users |
| **Categories of Personal Data** | User pair IDs, match status, creation timestamp |
| **Recipients** | Only the two matched users; Hosting provider |
| **Transfers to Third Countries** | None |
| **Retention Period** | Until either user deletes their account (CASCADE deletion). |
| **Security Measures** | Match data only accessible to involved users; authentication required for all match operations |

---

## Processing Activity 8: Cookie/Analytics Processing

| Field | Description |
|-------|-------------|
| **Purpose** | Essential: maintain user sessions. Optional: analytics to improve the service. Optional: marketing. |
| **Legal Basis** | Essential cookies: Art. 6(1)(f) GDPR - Legitimate interest. Analytics/Marketing cookies: Art. 6(1)(a) GDPR - Consent. |
| **Categories of Data Subjects** | All website visitors (essential cookies); Consenting visitors (analytics/marketing) |
| **Categories of Personal Data** | Session ID, cookie preferences (localStorage), analytics data (if consented) |
| **Recipients** | Hosting provider; Analytics provider (if applicable, only with consent) |
| **Transfers to Third Countries** | None (no third-party analytics currently in use) |
| **Retention Period** | Session cookies: until browser close. Cookie preferences: max 12 months. Analytics cookies: max 12 months. |
| **Security Measures** | HttpOnly session cookies; SameSite=Lax; Secure flag in production; cookie consent banner with granular control |

---

## Processing Activity 9: Email Communications

| Field | Description |
|-------|-------------|
| **Purpose** | Send registration confirmation, account-related notifications, and optional marketing communications |
| **Legal Basis** | Transactional emails: Art. 6(1)(b) GDPR - Performance of a contract. Marketing emails: Art. 6(1)(a) GDPR - Consent. |
| **Categories of Data Subjects** | Registered users |
| **Categories of Personal Data** | Email address, name, registration details |
| **Recipients** | Email service provider (data processor) |
| **Transfers to Third Countries** | None |
| **Retention Period** | Email address retained until account deletion. Email logs: max 90 days. |
| **Security Measures** | Emails sent via server-side wp_mail (WordPress context); no sensitive data in email body; unsubscribe option for marketing emails |

---

## Data Processors

| Processor | Purpose | Location | DPA Signed |
|-----------|---------|----------|------------|
| Hosting provider | Server infrastructure, data storage | EU/EEA | Yes |
| Email service provider | Transactional and marketing emails | EU/EEA | Yes |

---

## Technical and Organizational Measures (Art. 32 GDPR)

1. **Encryption in transit:** All data transmitted via HTTPS/TLS
2. **Encryption at rest:** Password hashing (bcrypt)
3. **Access control:** Authentication required for all data access; session-based access control
4. **Input validation:** Server-side sanitization of all user inputs
5. **Rate limiting:** Protection against brute-force and automated attacks
6. **CSRF protection:** Token-based protection for state-changing operations
7. **Automatic data deletion:** Scheduled cleanup of GPS data (48h), messages (12mo), rate limits (24h)
8. **Data minimization:** Only data necessary for service functionality is collected
9. **Backup policy:** Backups retained max 90 days after account deletion
10. **Incident response:** Data breach notification within 72 hours (UOOU) and to affected users

---

*This document is reviewed annually or whenever processing activities change significantly.*
*Next review date: 2027-03-29*
