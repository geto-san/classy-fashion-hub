# ROLE
You are the lead developer on a 3-day university project: an Online Fashion Management and E-Commerce System for "Classy Fashion Hub" (a small Ugandan fashion shop). We are adapting the open-source Bagisto platform (Laravel), not building from scratch. Speed and a working, demo-able result matter more than elegance.

# SOURCE FILES (in the current working directory)
1. `Blair_Fashion_Hub_report.docx`: the requirements report. This is the source of truth for WHAT to build. Key sections: 8 (scope), 9 (functional requirements 9.1-9.9), 10 (non-functional), 7 (the six SMART objectives).
2. `Classy_Fashion_Hub_Bagisto_Plan.xlsx`: the plan. Tabs: Summary, Gap List (17 requirements with fit level and planned day), 3-Day Plan (17 tasks), If Time Slips (cut order).

Read both fully before doing anything. Use `python-docx`/`pandoc` for the report and `openpyxl` for the spreadsheet (load with default settings when you intend to write back so formulas are preserved; never save a workbook loaded with `data_only=True`).

# FIRST STEPS (do these before writing code)
1. Detect the environment: OS, PHP version, Composer, Node/npm, MySQL/MariaDB availability, git status. Check Bagisto's current installation requirements against what is installed and tell me about any mismatch.
2. Summarise back to me in under 25 lines: the project, the 17 gap list items by fit level, and today's plan. Then ask for any missing info in ONE batched question, and continue without waiting if nothing blocks you.
3. Install Bagisto into this project (or a subfolder if the directory is not empty; do not overwrite the report or spreadsheet). Use the official installation method from Bagisto's documentation for the version you install, and record that version.

# HOW TO WORK
- Follow the plan day by day. Within a day, do tasks in order. Do not start Day 2 work until Day 1 is verified working, unless I say otherwise.
- Do NOT guess Bagisto internals. Bagisto's structure, ACL, order status handling, payment method registration and theming vary by version. Before each customization, read the installed code and official docs for that version, then follow its extension mechanism.
- Prefer extension over modification: put custom code in a separate Laravel package/module (Bagisto's package convention) or in app-level service providers, listeners and migrations. Avoid editing vendor/core files, so upgrades and review stay clean.
- Every database change goes in a migration. Every custom feature gets at least one automated test (feature test) or, where that is unrealistic in the time, a documented manual test script.
- Verify by running things: after each task, run the app, execute the relevant test or request, and show the evidence (command output, a passing test, or a described click-path). Never mark something done on the basis of "the code looks right."
- Commit after each completed task with a clear message referencing the Gap List number, e.g. `feat(gap-12): audit log for stock changes`.
- Seed data: provide a seeder for 15-20 realistic fashion products (shirts, jackets, shoes) with sizes, colours, fixed UGX prices and placeholder images, plus one admin, one worker and one customer test account. Print the test credentials in a README, not in code.

# REQUIREMENTS TO IMPLEMENT (map to Gap List numbers in the spreadsheet)
- Catalogue with size/colour variants, fixed prices, search and filters, deactivate instead of delete (report 9.2).
- Roles: Admin and Worker with limited permissions; customers separate (9.1).
- Stock tracking, low-stock threshold and admin alert (9.3, 9.8).
- Order statuses: Pending, Confirmed, Paid, Processing, Dispatched, Delivered, with sensible transitions and customer-visible status (9.5, 9.7).
- Delivery info at checkout: location, contact phone, delivery instructions; visible in admin order view (9.7).
- Mobile money (MTN / Airtel) via Flutterwave or Pesapal in SANDBOX mode (9.6). Rules:
  - An order must only become Paid after a verified server-side confirmation (webhook signature check and/or transaction verification call). A customer returning from the payment page is never proof of payment.
  - Pending, successful and failed payments must be distinguishable; store the transaction reference.
  - All keys and secrets live in `.env` and are documented in `.env.example`. Never hard-code or commit secrets.
- Audit trail: record who changed stock levels and order statuses, and when, using an established Laravel activity-log package (10.2, 9.3).
- Cost price per product and profit per sale in reports (9.4).
- Dashboard and sales reports for a date range (9.9); export if the built-in supports it.
- Branding for "Classy Fashion Hub"; mobile-responsive checks (10.8).
- Luganda is optional and is the first thing to cut.

# KEEP THE SPREADSHEET IN SYNC
After completing, partially completing or cutting a task, update `Classy_Fashion_Hub_Bagisto_Plan.xlsx`:
- In `3-Day Plan`, set column E to one of: Not started, In progress, Done, Cut. Put brief evidence or caveats in column F.
- In `Gap List`, set column F (Verified in Bagisto?) to Yes, No or Partly once you have checked the real behaviour in the installed version. If the fit level in column D turns out wrong, correct it and note why in column E.
- Do not touch or overwrite the formulas in the `Summary` tab. After editing, recalculate if a recalculation script is available, and make sure no formula errors appear.
- Keep a copy of the original file before your first edit (`Classy_Fashion_Hub_Bagisto_Plan.original.xlsx`).

# TIME PRESSURE RULES
- We have 3 days total. If a task will clearly take more than about twice its planned time, stop, tell me, and propose the cheapest acceptable alternative.
- Cut in this order if needed: (1) Luganda, (2) live payments, so keep sandbox only, (3) email notifications, so use dashboard alerts only. Never cut: roles, the audit log, or order statuses.
- Live Flutterwave/Pesapal merchant approval may not happen in time. Sandbox plus a clearly documented switch to production credentials is acceptable.

# SECURITY AND QUALITY BASELINE
- No plain-text passwords, no secrets in git, HTTPS assumed in production, CSRF protection left enabled, validate all new inputs server-side, and enforce role permissions on the server and not only by hiding menu items.
- Check that a Worker cannot reach admin-only pages by typing the URL.
- Do not delete, rewrite or reformat the report. Do not install unrelated packages.

# COMMUNICATION
- Be concise. At the end of each task: what changed, how you verified it, and anything I must do manually (e.g. creating the sandbox account).
- If you are blocked or an assumption could be wrong in a way that costs me time, ask one specific question and keep working on something unblocked meanwhile.
- If the report and the plan conflict, the report wins; flag the conflict to me.

# DEFINITION OF DONE (end of Day 3)
- Fresh clone + README steps gets the app running with seeded data.
- A customer can browse, order, pay in sandbox, and watch the order status change; an admin and a worker see appropriately different functions; the audit log shows who changed what.
- The spreadsheet reflects reality (statuses and verification column filled in).
- A short `docs/USER_GUIDE.md` (browse, order, pay, check status; for customers, workers and admin) and a `docs/DEMO_SCRIPT.md` walking through the six SMART objectives with where to take a screenshot for each.
- A short `docs/KNOWN_LIMITATIONS.md` honestly listing what was cut or only sandboxed, and the future work from the report (AI recommendations, virtual fitting, multi-seller marketplace).

Start with the FIRST STEPS now.
