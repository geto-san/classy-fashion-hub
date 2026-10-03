# Compliance Audit — Implementation vs `Blair_Fashion_Hub report.docx`

Method: every requirement below was checked against the running code,
routes, DB state or automated tests (suite: 27 passing). Verdicts:
**Compliant**, **Partial**, **Missing**, **Unverified**, **N/A** (doc-level
or physical-world, no software duty).

## 7. SMART objectives

| # | Objective | Verdict | Evidence |
|---|---|---|---|
| 1 | Digital catalogue (name, category, size, colour, description, price, availability) | Compliant | 16 products, configurable variants, UGX prices, availability flags; category/search verified |
| 2 | Inventory + records (dates, items, amounts, profits) + basic reports | Partial | Records, profit-per-order and sales reports done; **profit aggregated per period missing** (only per order) |
| 3 | Ordering workflow (select, submit, view, status updates) | Compliant | E2E test: cart → COD → history with live status |
| 4 | Payment + delivery info for remote purchase | Compliant (sandbox) | Flutterwave sandbox; delivery fields end to end; live approval pending |
| 5 | Role-based access, transactions tied to users | Compliant | Admin/Worker/customer split; 401 enforcement tested; audit log records who/when |
| 6 | Evaluate usability, reliability, security, performance with users | Partial | 27 automated tests + guides done; **representative-user testing and phone test pending (owner-side)** |

## 8. Scope — Compliant
Customer side (register/login/browse/search/cart/orders/history/status),
admin side (products/images/prices/inventory/orders/users/reports),
limited worker side, and payment/delivery all present. AI recommendations,
virtual fitting and marketplace correctly absent (future work).

## 9. Functional requirements

**9.1 Accounts — Partial.**
Registration, login/logout, admin/worker/customer roles, worker
management by admin, and minimal profiles all work. Password recovery
routes exist for shop and admin, but **reset emails cannot send until
SMTP is configured** (`MAIL_MAILER=log`).

**9.2 Catalogue — Compliant.** CRUD with size/colour variants, fixed UGX
prices, images, search/filters; deactivation toggle instead of delete
(workers cannot delete at all).

**9.3 Inventory — Compliant.** Quantities per variant; oversell blocked
(back-orders off by default); worker edits allowed; every change logged
with user and timestamp; low-stock threshold (5) with dashboard widget
and crossing alerts.

**9.4 Records — Partial.** Date/item/amount/profit per sale recorded;
transaction search and period reports with CSV export work; historical
prices snapshot on order items. **Missing: profit totals for a selected
period** (dashboard shows sales totals, not profit totals).

**9.5 Cart & ordering — Compliant.** Cart editing, availability checks,
delivery info at checkout, full status chain
(Pending→Confirmed→Paid→Processing→Dispatched→Delivered), customer
history, customer cancel route. Extra legacy codes (completed/closed/
fraud) retained harmlessly.

**9.6 Payments — Compliant (sandbox).** Flutterwave MTN/Airtel method;
pending/success/failed distinguished with stored tx refs; Paid is set
only after webhook signature + server-side verify (tested, incl.
mismatch and bad-signature rejection). Live merchant approval pending.

**9.7 Delivery — Compliant.** Location, contact phone and instructions
collected and visible in admin; staff update delivery progress through
order statuses; customers see live status; rider/partner assignable per
order with audit entry.

**9.8 Notifications — Partial.** Admin new-order notices and low-stock
alerts work (dashboard-only, per the agreed cut). **Customer order-event
emails are built-in but unsent** (no SMTP; same cut).

**9.9 Reports — Compliant.** Dashboard stats, sales/customer/product
reports over date ranges, CSV export verified.

## 10. Non-functional requirements

- **10.1 Usability — Compliant.** Simple flows + USER_GUIDE; mobile layout built-in.
- **10.2 Security — Partial.** Hashed passwords, server-side RBAC (401-tested),
  CSRF on, secrets in `.env`, audit logging of stock/status/payment.
  Gaps: **user/role-management changes are not audit-logged**; no
  penetration test; HTTPS only in production.
- **10.3 Performance — Unverified.** Snappy locally with sync queue; **no
  load test** under concurrent users.
- **10.4 Availability — Non-compliant on free tier.** Render free sleeps
  (cold starts) and has no failure alerting beyond the `/up` health check.
  Needs paid/no-sleep hosting for genuine 24/7.
- **10.5 Reliability — Partial.** Accurate stock, consistent statuses,
  FK-safe deletes. **Missing: a backup/restore procedure** (neither
  Railway nor Render free backups are configured).
- **10.6 Scalability — Compliant (for scope).** Modular ClassyFashion
  package; single-seller architecture as required.
- **10.7 Maintainability — Compliant.** Package isolation, docs, error
  logging, 27 automated tests.
- **10.8 Compatibility — Unverified.** Responsive theme built-in, but
  **real-phone test and weak-network check are owner-side pending**.
- **10.9 Accessibility/Luganda — Partial (cut as agreed).** Clear labels
  and instructions; English only, no Luganda locale or bilingual guide.
- **10.10 Integrity/privacy — Compliant.** Minimal collection, guarded
  access, profile/address self-correction, history preserved.

## Non-compliances to fix before grading (prioritized)

1. **Backups (10.5)** — define and test a dump/restore routine.
2. **SMTP (9.1, 9.8)** — configure a sender so recovery + order emails work.
3. **Phone + user testing (SMART 6, 10.8)** — owner-side, scripted in DEMO_SCRIPT.
4. **Profit per period (obj. 2/9.4)** — small reporting addition if time allows.
5. **Delivery-partner field, user-admin audit rows** — nice-to-have extensions.
6. **Availability wording** — present Render free honestly (sleep + cold start).
