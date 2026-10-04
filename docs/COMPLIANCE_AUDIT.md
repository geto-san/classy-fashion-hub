# Compliance Audit — Implementation vs `Blair_Fashion_Hub_report.docx`

**How to read this.** Verdicts: **Compliant**, **Partial**, **Not done**,
**Unverified**, **N/A**. A verdict is only "Compliant" where code *and* an
automated test back it. This revision follows an independent review of the
repository against the report; where that review changed a verdict, the
reason is given.

**Test status — read first.** `store/tests/Feature` holds **83 test cases**: the original 31, 14 added by
the AI-assistant/brand/rider work, and **38 added with the review fixes. None
of the 38 has been run** (no PHP/MySQL/Bagisto vendor was available where they
were written), and the 14 were not run after the merge either. Run
`php vendor/bin/pest tests/Feature` and treat any red test as a defect to fix
before relying on the rows below that cite it.

## 7. SMART objectives

| # | Objective | Verdict | Evidence / what is left |
|---|---|---|---|
| 1 | Digital catalogue (name, category, size, colour, description, price, availability) | Compliant | 16 products, variants, UGX prices. Footwear now carries EU sizes 38–45 **on a freshly seeded database**; an already-seeded catalogue keeps colour-only shoes until re-created in admin |
| 2 | Inventory + records (dates, items, amounts, profits) + basic reports | Compliant | Per-order and per-period profit report with CSV. Cost is now stored on each sale (`order_items.cost_price`), so past profit no longer changes when a supplier price does. Seed costs are still illustrative (60% of price): enter real ones |
| 3 | Ordering workflow (select, submit, view, status updates) | Compliant | Cart → order → statuses Pending→Confirmed→Paid→Processing→Dispatched→Delivered; cash on delivery follows Confirmed→Processing and records the cash at delivery; customer order page shows a progress timeline |
| 4 | Payment + delivery info for remote purchase | Compliant (sandbox) | Mobile money marks an order Paid only after server-side verification; one order per payment is enforced; a verified payment with no order is flagged for staff. Live merchant approval pending |
| 5 | Role-based access, transactions tied to users | Compliant | Admin/Worker/customer; 401 tests; audit log of stock, status, payment and (new) user/role changes with an admin viewer. Workers no longer see cost or profit |
| 6 | Evaluate usability, reliability, security, performance with users | **Not done** | No representative-user testing, load test or real-phone test has been carried out. This needs people, not code (scripted in DEMO_SCRIPT) |

## 8. Scope
Customer, admin and limited worker sides plus payment/delivery are present.
AI recommendations, virtual fitting and marketplace are correctly absent
(future work, `KNOWN_LIMITATIONS.md`).

## 9. Functional requirements

**9.1 Accounts — Partial.** Registration, login, roles, worker management and
profiles work; user/role changes are now audit-logged. Password-reset e-mails
send only once real SMTP is configured (`MAIL_MAILER=smtp` + provider
credentials); until then they go to the log.

**9.2 Catalogue — Compliant** (see objective 1 for the shoe-size note).
Deactivation instead of delete; workers cannot delete. The storefront search
page's "in stock only" filter is **unverified** — confirm in the running shop.

**9.3 Inventory — Compliant.** Oversell blocked; staff stock edits logged
with user and time; threshold 5. Low-stock alert is now e-mailed to
`ADMIN_MAIL_ADDRESS` for any drop below the threshold, including one caused
by a customer's checkout (again: needs SMTP to leave the log).

**9.4 Records — Compliant.** Date/item/amount/cost/profit per sale recorded;
cost is a snapshot per sale; period reports and CSV export. Sales made before
the snapshot column existed were backfilled with the cost at migration time.

**9.5 Cart & ordering — Compliant.** Status flow enforced on every path:
core invoicing/shipping is blocked until an order is paid (or COD-confirmed);
mobile-money orders cannot be marked Paid by hand; "Paid" by hand is recorded
with who/when; customers can cancel only before payment, staff cancel paid
orders (which returns stock and flags the refund). Admin order list now
renders and filters all report statuses (core showed a blank status).

**9.6 Payments — Compliant (sandbox).** Verified-only Paid with signature,
reference, amount and currency checks; atomic finalisation (webhook and
status check cannot both create an order); attempts addressed by unguessable
id and limited to the shopper's session; unapproved prompts expire (a late
approval is still honoured); sandbox/live is enforced against the key prefix;
admin "Mobile Money Payments" page lists every attempt. **No automatic
refund**: cancelling a paid order flags it and staff refund in the Flutterwave
dashboard.

**9.7 Delivery — Compliant.** Location, phone, instructions captured and shown
to staff; a rider / delivery partner can be assigned to each order from the
order page and every assignment is audit-logged. It is a free-text name (no
rider directory or phone number); customers see live status and the order
progress timeline.

**9.8 Notifications — Partial.** Customers are e-mailed on Confirmed, Paid,
Processing, Dispatched, Delivered and Canceled; the owner on low stock. All
of it is **unproven end to end until SMTP is configured**.

**9.9 Reports — Compliant.** Dashboard, sales/customer/product reports, sales
and profit CSV exports.

## 10. Non-functional requirements

- **10.1 Usability — Compliant** in design; **unverified** with real users.
- **10.2 Security — Partial.** Hashed passwords, CSRF, server-side RBAC,
  secrets in env, audit log with viewer. Published default passwords are no
  longer usable on production (`classy:secure-accounts`). Not done: penetration
  test; HTTPS only in production.
- **10.3 Performance — Unverified.** No load test.
- **10.4 Availability — Not met on the free tier.** Render free sleeps.
- **10.5 Reliability — Partial.** `scripts/backup.sh` / `restore.sh` exist but
  are run by hand, are not scheduled, and **no restore drill has been recorded**
  (see `docs/DEPLOY.md` §4).
- **10.6 Scalability / 10.7 Maintainability — Compliant** for scope (package
  isolation, docs, tests — subject to the test status above).
- **10.8 Compatibility — Unverified.** No real-phone or weak-network test.
- **10.9 Accessibility / Luganda — Partial.** English only (report: "where
  resources permit").
- **10.10 Integrity / privacy — Compliant.**

## What still needs a person (cannot be closed by code)

1. Run the test suite and fix anything red (38 tests are new and unrun).
2. Configure SMTP and prove reset + order e-mails arrive (9.1, 9.8).
3. Real-user, phone/weak-network and load testing (objective 6, 10.3, 10.8).
4. Schedule backups and record one successful restore drill (10.5).
5. Enter real supplier costs (9.4) and decide whether workers should keep
   product-edit access, which still exposes the cost field.
6. Present Render free hosting honestly (sleeps) or move to always-on (10.4).
