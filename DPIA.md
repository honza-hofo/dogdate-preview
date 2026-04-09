# Data Protection Impact Assessment (DPIA)

## DogDate - Dog Walking Partner Matching Service

| Field | Value |
|-------|-------|
| **Service** | DogDate (part of MakejsMANMATem.cz) |
| **Data Controller** | MANMAT s.r.o., ICO: 03166236 |
| **Contact** | Jan Formanek, formanek@manmat.cz |
| **Assessment Date** | 2026-03-29 |
| **Assessor** | Jan Formanek, MANMAT s.r.o. |
| **Status** | Approved |

---

## 1. Description of Processing Operations

### 1.1 Nature of Processing

DogDate is a free web-based service that enables dog owners to find walking partners in their geographic vicinity. The service processes personal data for the following core operations:

- **User registration and authentication** - collecting name, email, age, city, password
- **Profile creation** - storing biographical information, photos, and dog details
- **GPS-based proximity matching** - using real-time location data to find nearby users
- **In-app messaging** - enabling direct text communication between matched users
- **Match management** - tracking connection status between user pairs

### 1.2 Scope of Processing

- **Data subjects:** Dog owners aged 18+ in the Czech Republic (primarily)
- **Volume:** Expected initial user base of hundreds, potentially growing to thousands
- **Geographic scope:** Czech Republic, potentially EU/EEA
- **Data types:** Identification data, location data (GPS), photographs, free-text messages, behavioral data (availability, preferences)

### 1.3 Context of Processing

DogDate operates as a component of the MakejsMANMATem.cz website, a community platform for dog sports enthusiasts run by MANMAT s.r.o. The service is free and does not generate direct revenue. It serves a community-building purpose.

### 1.4 Purpose of Processing

The sole purpose is to connect dog owners for shared walks. No data is used for advertising, profiling, automated decision-making, or sale to third parties.

---

## 2. Assessment of Necessity and Proportionality

### 2.1 Lawfulness

| Processing | Legal Basis | Justification |
|-----------|-------------|---------------|
| Account data | Art. 6(1)(b) - Contract | Necessary to provide the service |
| GPS location | Art. 6(1)(a) - Consent | Explicit consent at registration; core service feature |
| Photos | Art. 6(1)(a) - Consent | Optional; explicit consent obtained |
| Messaging | Art. 6(1)(b) - Contract | Core feature of the service |
| Security logs | Art. 6(1)(f) - Legitimate interest | Protecting the service and users |
| Analytics cookies | Art. 6(1)(a) - Consent | Optional; granular consent via cookie banner |

### 2.2 Data Minimization

- Only data strictly necessary for matching is collected
- Age is collected only to enforce 18+ requirement and display approximate age
- Exact GPS coordinates are never exposed to other users (only distance in km)
- Photos are optional
- Bio/description is optional
- No social media connections, phone numbers, or government IDs are collected

### 2.3 Purpose Limitation

Data is used exclusively for the DogDate matching service. No secondary processing, no advertising, no data sales, no cross-service data sharing.

### 2.4 Storage Limitation

| Data Type | Retention Period | Deletion Method |
|-----------|-----------------|-----------------|
| GPS coordinates | 48 hours | Automated (cleanup.php) |
| Messages | 12 months | Automated (cleanup.php) |
| Rate limit logs | 24 hours | Automated (cleanup.php) |
| Account data | Until deletion request | Manual request, processed within 30 days |
| Photos | Until account deletion | File system deletion + CASCADE |
| Backups | 90 days after account deletion | Automated backup rotation |
| GDPR consent records | 5 years | Legal requirement to prove consent |
| Cookies | 12 months max | Browser expiration |

### 2.5 Accuracy

Users can edit their profile data at any time. Location data is refreshed on each app use, ensuring accuracy of proximity calculations.

### 2.6 Data Subject Rights

All GDPR rights are implemented:
- **Access:** Profile section + JSON export
- **Rectification:** Profile editing
- **Erasure:** In-app deletion + email request
- **Portability:** JSON data export
- **Restriction:** Via email request
- **Objection:** Via email request
- **Consent withdrawal:** In-app + email

---

## 3. Assessment of Risks to Data Subjects

### 3.1 Risk: GPS Location Data Exposure

| Aspect | Assessment |
|--------|------------|
| **Threat** | Unauthorized access to exact user coordinates could enable stalking or physical harm |
| **Likelihood** | Low - coordinates are never sent to client; only distance is computed server-side |
| **Severity** | High - physical safety risk |
| **Overall Risk** | Medium |
| **Mitigations** | (1) Server-side Haversine calculation, never exposing raw coordinates (2) Automatic deletion after 48 hours (3) Consent required and revocable (4) Database access restricted (5) HTTPS encryption |
| **Residual Risk** | Low |

### 3.2 Risk: Photo Misuse

| Aspect | Assessment |
|--------|------------|
| **Threat** | User photos could be scraped, copied, or used without consent outside the platform |
| **Likelihood** | Medium - photos are visible to all registered users |
| **Severity** | Medium - reputational harm, identity misuse |
| **Overall Risk** | Medium |
| **Mitigations** | (1) Photos only visible to authenticated users (2) No facial recognition or biometric processing (3) Photos are optional (4) Unique random filenames prevent URL enumeration (5) Photos deleted on account removal |
| **Residual Risk** | Low-Medium |

### 3.3 Risk: Message Content Exposure

| Aspect | Assessment |
|--------|------------|
| **Threat** | Private messages between users could be exposed via data breach |
| **Likelihood** | Low - messages stored server-side with access controls |
| **Severity** | Medium - privacy violation, potential embarrassment |
| **Overall Risk** | Low-Medium |
| **Mitigations** | (1) Messages accessible only to match participants (2) Automatic deletion after 12 months (3) Database access restricted (4) Input sanitization prevents injection |
| **Residual Risk** | Low |

### 3.4 Risk: Matching Algorithm - Discrimination

| Aspect | Assessment |
|--------|------------|
| **Threat** | Matching based on location could inadvertently discriminate |
| **Likelihood** | Very Low - matching is purely geographic + time preference |
| **Severity** | Low - service is about walking dogs, not high-stakes decisions |
| **Overall Risk** | Very Low |
| **Mitigations** | (1) No profiling or automated decision-making (2) All users in range are shown equally (3) No scoring beyond user-submitted ratings |
| **Residual Risk** | Very Low |

### 3.5 Risk: Account Takeover / Credential Breach

| Aspect | Assessment |
|--------|------------|
| **Threat** | Attacker gains access to user account via credential stuffing or brute force |
| **Likelihood** | Medium - common attack vector |
| **Severity** | Medium - access to profile, location history, messages |
| **Overall Risk** | Medium |
| **Mitigations** | (1) bcrypt password hashing (2) Rate limiting on login (5 attempts/minute) (3) CSRF protection (4) Secure session cookies (HttpOnly, SameSite, Secure) (5) Session-based authentication |
| **Residual Risk** | Low |

### 3.6 Risk: Data Breach / Server Compromise

| Aspect | Assessment |
|--------|------------|
| **Threat** | Full database breach exposing all user data |
| **Likelihood** | Low - standard hosting security measures |
| **Severity** | High - mass exposure of personal data including historical locations |
| **Overall Risk** | Medium |
| **Mitigations** | (1) Passwords are bcrypt-hashed (not recoverable) (2) GPS data auto-deleted after 48h (limits exposure window) (3) Messages auto-deleted after 12 months (4) Data breach notification process (72h to UOOU, immediate to users) (5) Regular security updates |
| **Residual Risk** | Low-Medium |

---

## 4. Measures to Address Risks

### 4.1 Technical Measures

| Measure | Status | Description |
|---------|--------|-------------|
| HTTPS/TLS | Implemented | All traffic encrypted in transit |
| bcrypt password hashing | Implemented | Passwords cannot be reversed |
| CSRF token protection | Implemented | All POST/DELETE requests validated |
| Secure session cookies | Implemented | HttpOnly, SameSite=Lax, Secure (production) |
| Rate limiting | Implemented | Brute-force protection on login and registration |
| Input sanitization | Implemented | XSS and injection prevention |
| Server-side GPS calculation | Implemented | Raw coordinates never sent to clients |
| Automated data cleanup | Implemented | GPS: 48h, messages: 12mo, rate limits: 24h |
| File upload validation | Implemented | Type checking, size limits (10MB), random filenames |
| CASCADE deletion | Implemented | Account deletion removes all related data |

### 4.2 Organizational Measures

| Measure | Status | Description |
|---------|--------|-------------|
| Privacy policy | Published | Czech language, comprehensive, accessible |
| GDPR consent collection | Implemented | Granular consent at registration with IP logging |
| Data subject rights | Implemented | In-app export, deletion, consent management |
| Processing records (Art. 30) | Documented | See ZAZNAMY_ZPRACOVANI.md |
| Breach notification procedure | Documented | 72h to UOOU, immediate notification to affected users |
| Age verification | Implemented | 18+ requirement enforced at registration |
| Cookie consent | Implemented | Granular cookie banner with revocation option |
| Regular review | Planned | Annual DPIA review |

### 4.3 Measures Considered but Not Implemented

| Measure | Reason |
|---------|--------|
| End-to-end message encryption | Disproportionate for the risk level; messages auto-deleted after 12 months |
| Two-factor authentication | Planned for future implementation; current rate limiting provides adequate protection |
| Location obfuscation (random offset) | Haversine distance calculation already prevents coordinate exposure; adding noise would degrade service quality |

---

## 5. Consultation

### 5.1 Data Subjects

Users are informed about all processing via the privacy policy (dogdate-podminky.html). Consent is granular and revocable. Contact information is prominently displayed.

### 5.2 Supervisory Authority

Consultation with the Czech Data Protection Authority (UOOU) is not deemed necessary as residual risks have been reduced to acceptable levels through the measures described above. The DPIA will be made available to UOOU upon request.

---

## 6. Conclusion

The DogDate service processes personal data, including GPS location data, which warrants careful assessment. After implementing the technical and organizational measures described in this DPIA, residual risks to data subjects have been reduced to an acceptable level.

Key risk-reducing factors:
- GPS coordinates are automatically deleted after 48 hours
- Exact coordinates are never exposed to other users
- Messages are automatically deleted after 12 months
- All consent is granular, documented, and revocable
- Standard security measures (bcrypt, CSRF, rate limiting, HTTPS) are in place
- Data subject rights are fully implemented with in-app tools

**Decision: Processing may proceed with the measures described above.**

---

## 7. Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Data Controller | Jan Formanek, MANMAT s.r.o. | 2026-03-29 | /s/ Jan Formanek |

---

## 8. Review Schedule

This DPIA shall be reviewed:
- Annually (next review: 2027-03-29)
- When processing operations change significantly
- When new risks are identified
- When a data breach occurs
- When requested by the supervisory authority

---

*Document created: 2026-03-29*
*Document version: 1.0*
