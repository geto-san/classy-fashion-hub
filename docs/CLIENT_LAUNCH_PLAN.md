# Client testing and launch plan

Prepared 3 November 2026. This plan separates a client test handover from public
commercial operation. It uses the recorded application acceptance from 2 November
and the repository checks performed during cleanup; it is not a new full live-site audit.

**Recommendation:** use the existing free services for controlled client testing.
Finish the gates below before accepting public orders. You do not need to finance
the client's business yourself. The client should own the service accounts and
approve any hosting, payment, delivery or other operating costs.

## 1. What is already available

The deployed app includes catalogue/variants, customer accounts, cart/checkout,
Cash on Delivery, mobile-money integration, staff permissions, stock management,
order tracking, reports, an audit trail, and the assistant. Homepage campaign/category photos were restored on 3 November, with a distinct
closing banner and no repeated photos. The catalogue is still empty; the shop
should supply its own product listings and product images.

The last verified business counts were zero products, one customer and one order.
Do not delete that customer or order as part of testing without the owner's decision.
Cloudinary retains the branding assets and five verified homepage campaign/shop photos. Previous database/media
restore checks succeeded, but recurring off-machine backups are not yet proven.

Current unresolved items include email delivery, missing policy pages, a complete
owner-product/checkout rehearsal, live-payment readiness, recurring backups,
provider ownership/billing, and production availability. Earlier browser checks
of the Create Product dialog do not prove that saving a real product and its
photos works end to end; that is a required acceptance task.

## 2. Before giving the client a test version

| Objective | Affordable way to complete it | Evidence needed |
|---|---|---|
| Client-owned access | Create the client under Settings → Users with the full-access admin role. Have them change their password in My Account. Rotate the existing admin password previously shared in chat. Give workers separate restricted accounts. | The client signs in independently and adds a product. A worker cannot manage users, settings or profit reports. |
| Agree the test scope | Write a one-page agreement: this is testing, not a finished public shop; state supported workflows, known gaps, who reports problems and when you will review them. | Client confirms the test scope and limitations in writing. |
| Enter a small catalogue | Ask the owner to add 5–10 actual products, including one with size/colour variants and one with stock quantity 1. Use their phone photos and actual UGX prices, supplier costs and stock. | Product save/edit, image upload/display, search, variant selection and stock all work. |
| Complete an order rehearsal | Use Cash on Delivery and test contact details. Confirm shipping cost, address, phone and instructions; process the order through delivery. Cancel a separate order and check stock. | Totals match; the order appears once; stock and reports reconcile. |
| Separate test and real activity | Prefer a disposable local/test database for destructive, payment or security checks. On the current site, use clearly identified test accounts/orders and preserve existing records. Agree how test records will be reconciled before launch. | Nobody mistakes a test order for a customer sale or deletes real business data. |
| Make recovery possible | Take a fresh complete database/media backup and an encrypted off-machine copy before testing begins. | You can locate the backup, verify checksums and restore it into an empty disposable database. |

### A simple test session you can organize without buying tools

Borrow access to two phones and one laptop. Recruit the owner, a staff member and
one or two classmates. Use an ordinary spreadsheet with these columns:
`test / steps / expected result / actual result / screenshot / severity / owner / status`.
Do not put passwords, full customer records or payment credentials in screenshots.

Give each person a small task rather than explaining every click first:

1. Find an item, choose its size and add it to the cart.
2. Register/sign in, change quantity and complete a COD checkout.
3. View their order and check its delivery details and status.
4. As staff, confirm, process, dispatch and deliver the order.
5. As the owner, edit a price/stock value and inspect the report and audit trail.
6. Try an out-of-stock item, an invalid phone/address and an interrupted checkout.
7. Reopen the site on mobile data; check narrow-screen layout, readable controls,
   keyboard navigation and a slow first visit after inactivity.

On an isolated copy, also test two shoppers competing for the last unit, another
customer's order URL, restricted worker URLs and duplicate payment notifications.
Passing the normal flow alone is insufficient. Treat exposed customer data,
unauthorized access, wrong totals, duplicate orders and incorrect payment/stock
states as blockers. Keep cosmetic issues in a separate lower-priority list.

## 3. Before public operation

### A. Make email dependable

The project already uses Brevo over HTTPS, so do not add an SMTP subscription.
Brevo's current Free plan permits up to 300 sends per day; order-status updates
consume sends too. [Brevo daily limits](https://help.brevo.com/hc/en-us/articles/8292912279954-Add-or-remove-emails-from-your-plan).

1. In Brevo, add a sender using an inbox the shop controls and complete the sender
   verification. Set the matching `MAIL_FROM_ADDRESS` in Render, not Git.
2. Check Render's current outbound IP ranges and authorize them in Brevo if its
   IP restrictions are enabled. The migration record lists `74.220.48.0/24` and
   `74.220.56.0/24`; verify the dashboard before relying on those values.
3. If the shop already owns a domain, authenticate it using Brevo's DNS steps.
   Without a domain, Brevo documents temporary sender-address replacement for
   free/unauthenticated senders. That is not domain authentication: inspect the
   delivered sender, explain it to the client and prove delivery before using it.
   [Sender requirements](https://help.brevo.com/hc/en-us/articles/14925263522578-Comply-with-Gmail-Yahoo-and-Microsoft-s-requirements-for-email-senders).
4. Test customer password reset, order confirmation/status updates and staff
   low-stock email using inboxes you control. Check inbox, spam, provider logs,
   links and expiry. A valid API key is not proof that mail arrives.

If this cannot be completed at zero cost, continue supervised testing with email
explicitly marked unaccepted. Do not present password recovery as working or open
public registrations that depend on it.

### B. Publish the shop's actual policies and contact details

Use the existing CMS. Ask the owner to write or approve clear answers about:
what customer information is collected and why, the external storage/email
providers, who handles requests, payment methods, delivery areas/costs/timing,
cancellation, returns and refunds. Record only promises the shop can fulfil.
The owner must also check their applicable business and data-protection obligations;
publishing a policy page alone does not establish legal compliance.

Create the missing Privacy and Terms pages with the slugs used by the footer
(`privacy-policy` and `terms-conditions`). Review Payment, Shipping, Returns,
Refunds, Customer Service and every other footer destination. Publish missing
approved content or remove unsupported links. Check each visible link from a phone.
Replace placeholder support/contact details with the shop's own details.

### C. Choose and verify a payment method

The cheapest initial test route is COD. Enable it only where the shop genuinely
accepts cash on delivery. Set delivery charges and confirm who collects the money.

If the shop wants mobile money before an automatic merchant integration is ready,
use the implemented manual Till flow only with its own approved collection line:
set `MOMO_PROVIDER=manual` and `MOMO_TILL_NUMBER` privately in Render, deploy, then
test the entire flow. Staff must check their merchant/provider record before
confirming a submitted transaction reference. An SMS screenshot or customer claim
is not sufficient verification. Transaction/provider charges still belong to the
shop; manual reconciliation is not a free substitute for provider requirements.

For automatic MTN payments, complete merchant onboarding and set the real keys,
environment, currency and callback host. Test success, rejection, timeout,
duplicate notification and the charged-but-no-order recovery case. Use a small
owner-funded transaction only after agreeing the test and refund procedure.
Do not process real money through sandbox credentials. Paid-order refunds remain
manual and need a named staff member and a reconciliation record.

### D. Establish a backup routine the owner can follow

Use the existing [deployment backup instructions](DEPLOY.md) and
`scripts/backup.sh` / `scripts/restore.sh`; do not buy a backup service first.

1. On your own trusted computer, prepare the matching application dependencies and
   PostgreSQL clients at least as new as the server. Keep database and Cloudinary
   credentials in an ignored private environment file, never in a public script.
2. Export **both public and private Cloudinary disks** using
   `classy:copy-media` into separate local `public/` and `private/` directories.
   Use its verification mode to confirm the exported original bytes match.
3. Use Neon's direct TLS endpoint for the database dump. Set the verified media
   directory and only then set `MEDIA_EXPORT_COMPLETE=true` for `scripts/backup.sh`.
   A database-only export does not protect uploaded photos.
4. Keep only backups with the completion marker and valid checksums. Encrypt a
   complete backup before copying it to an existing private cloud drive or another
   trusted device. Keep the decryption passphrase separately.
5. Start with a daily end-of-day reminder. A laptop schedule only runs while the
   laptop is on and connected. Record missed backups rather than assuming cron ran.
6. Restore weekly during testing into an **empty disposable** database and media
   directory. Check order counts/totals, a customer sign-in and uploaded images.
   Never test restoration over the operating shop.

Agree that daily backups can lose changes made after the latest successful run.
If the owner cannot accept that recovery window, a stronger schedule is required.

### E. Confirm availability and account/billing ownership

Keep the existing `onrender.com` URL during testing; a custom domain is optional.
Render Free sleeps after 15 minutes without traffic and can take about a minute
to wake. Render explicitly advises against using Free instances for production
applications. Use it for testing; do not promise always-on commercial availability.
For dependable daily operation, obtain an owner-funded production hosting decision.
If funding is unavailable, deliver the test version and postpone that commitment.
[Render Free limitations](https://render.com/docs/free).

Use provider invitations where supported and ensure the client controls the
recovery email and billing decisions. Review usage regularly; do not attach your
personal payment card to cover unknown future client usage. The GitHub source,
Neon database, Cloudinary originals, Brevo sender and backup copies all need a
clear owner. Keep `APP_KEY` stable and store recovery secrets privately.

The last Railway inventory still had two empty project records and five volumes.
Before deleting the volumes, decide whether their unverified contents need recovery.
Then obtain explicit deletion approval, remove the approved resources and check
subscription/billing status in Railway. Do not assume deleting services cancels
an account plan or erases existing charges.

## 4. Keep the budget at zero while testing

| Resource | Practical choice |
|---|---|
| Website | Keep the existing Render Free service and default URL. Accept testing delays; avoid repeated unnecessary builds and artificial keep-alive traffic. |
| Database | Keep the existing Neon Free project. Its current published allocation includes 1 GB storage and 100 CU-hours per project/month. Watch its usage dashboard. [Neon pricing](https://neon.com/pricing). |
| Photos | Use the owner's phone and daylight. Resize/compress photos before upload. Cloudinary Free currently shares 25 monthly credits across storage, transformations and bandwidth; this is not 25 GB of each. [Cloudinary plans](https://cloudinary.com/documentation/billing_and_plans). |
| Email | Use Brevo Free within its daily allowance; test delivery instead of buying a different service immediately. |
| AI | Keep the rule-based assistant. A paid LLM is unnecessary for catalogue/order testing. |
| QA and training | Use classmates, borrowed devices, browser tools, a spreadsheet and a short screen recording. Use existing private storage for encrypted backup copies. |
| Real transactions and delivery | The shop funds these. A zero-cost software test does not make merchant fees, stock or delivery free. |

Free-plan allowances were checked on 3 November 2026 and may change. Review the
actual account dashboards before handover. Student/startup credits can be explored,
but eligibility, approval and expiry are uncertain; do not base operations on them.

## 5. Suggested seven-day sequence

| Day | Work and outcome |
|---|---|
| 1 | Agree scope, client account, password changes, account ownership and fresh backup. |
| 2 | Owner adds the small catalogue; verify photos, prices, stock, variants and delivery charges. |
| 3 | Rehearse COD orders, worker permissions, cancellation, reporting and audit records. |
| 4 | Verify email and publish approved policies/contact details. Record any blocked item. |
| 5 | Run the phone/classmate tests and a disposable restore drill; log defects. |
| 6 | Fix blockers, rerun affected checks, perform a clean build and final acceptance. |
| 7 | Train the client and obtain written acceptance for testing; authorize public operation only if its gates pass. |

This is a work sequence, not a guarantee that merchant approval or other external
requirements finish in seven days. Keep the client test phase open if blockers remain.

## 6. Acceptance and maintenance

Before client testing, require independent client login, usable product entry,
one complete COD rehearsal, a recoverable backup and acknowledged limitations.
Before public operation, additionally require approved policies, dependable
password/order email, verified enabled payment methods, agreed production
availability, a backup owner, and no unresolved access/data/payment/stock blockers.

Give the owner the [user guide](USER_GUIDE.md), current [deployment report](DEPLOYMENT_REPORT.md),
this checklist, a list of provider accounts, backup/recovery instructions and an
agreed support contact. Record the tested version, who accepted it, known minor
issues and the support period. Do not silently promise unlimited free maintenance.

For release verification, use a fresh checkout and a disposable seeded database;
run the custom application suite, both theme builds, translations, dependency
reviews and browser acceptance. The cleanup itself changed no tracked application
files. Its seven backup safeguards, links, shell syntax and project-document
checksums pass. Real scratch Docker exports under the original and simplified
ignore rules contain the same 480 files with matching SHA-256 checksums; secrets
and local dependencies are excluded. This is build-context verification, not a
full production-image build or an end-to-end commerce test.
