# TEST monitoring and human alerts

## Storefront monitoring mode — 2026-11-23

The existing external workflow accepts `STOREFRONT_MODE=closed|open` (default `closed`). Closed mode requires the protected main-origin response; open mode requires HTTP 200 and the actual Apple Klinika homepage identity. STAFF remains private in either mode. A monitor mode change does not open the webshop. Set the repository variable to `open` only as part of a separately approved public opening, and restore `closed` if the storefront is closed again. Keep the existing schedule, one incident and notification deduplication; no application deployment or provider operation is part of this change. Offline checks cover both profiles. Workflow execution, notification submission and owner-confirmed Inbox delivery remain distinct evidence.


## Independent public check

`.github/workflows/test-availability.yml` checks the existing public TEST storefront/login and the closed main/STAFF origins and login pages, plus the exact www redirect, with normal TLS validation every 15 minutes. Closed roots must return 401 with private/no-store and Cookie variance, without REVIEW basic-auth headers. Login 200 responses must contain the expected store title, native login fields and exact form destination; the TEST storefront must contain the installed theme and homepage markers. Arbitrary 200 responses and redirects to unrelated applications fail. It retries short maintenance interruptions, fails visibly in GitHub Actions and maintains one reusable GitHub issue. Only public endpoint names and generic failure codes enter the public issue. It never contains SSH access, response bodies, credentials or customer data. Enable GitHub notifications for that issue/repository to receive independent alerts.

GitHub scheduled workflows run from the repository default branch, currently `feature/project-bootstrap`. Publish only the two executable monitoring files and their directly necessary documentation there through a normal fast-forward feature-branch update; never modify `main` or app source there. GitHub can delay scheduled jobs; public-repository schedules may be disabled after prolonged repository inactivity. Verify recent workflow runs as part of operational checks. This is a minimal availability monitor, not a guaranteed real-time paging service.

`--exercise` proves failure, duplicate suppression and recovery without stopping the server. Publication and scheduled runs check actual availability only. The controlled drill is opt-in through workflow_dispatch and must not be used to claim email delivery without verifying the notification recipient.

## Internal material failures

The existing systemd monitor retains disk/container/HTTPS/PHP/SMTP/backup/TLS checks. `monitor-alert.py` sends material changes via the existing authenticated Hetzner SMTP service, independently of the WordPress container. First healthy startup is silent. An unchanged failure is silent; recovery produces one message. Transport errors retry after at least 15 minutes, remain visible in systemd status/logs, and never fall back to unauthenticated PHP mail.

Root-only `/etc/appleklinika-ops/alert-smtp.json` holds `host`, `port`, `username`, `password`, `from`, `recipient`, `qa_recipient`. The approved TEST sender is `info@appleklinika.hu`, SMTP `mail.your-server.de:587` STARTTLS. The human recipient is configurable; the QA drill goes only to the authorized owner QA inbox. No credentials belong in Git. Copy the existing supported FluentSMTP decrypted setting directly into root-private configuration on TEST, without displaying or transferring it through chat/local temporary files. Mailbox password rotation requires updating both transport configurations privately.

Delivery state is root-private under `/var/lib/appleklinika-ops/alert-delivery.json`. `--exercise` uses separate QA state and does not mask real incidents. SMTP acceptance is recorded separately from the owner's confirmation of actual Inbox delivery. The independent GitHub surface remains available if both the server and its SMTP path fail.

Offline checks: `python3 scripts/operations/external-monitor.py --self-test` and `python3 scripts/operations/monitor-alert.py --self-test`.

The monitor carries returned issue state through each run so delayed GitHub lists cannot duplicate the rapid failure/recovery drill. Existing exact-marker duplicate incidents are closed. Offline stale-list regression checks cover this behavior.


## November 22 bounded extension evidence

Seven targets run concurrently per attempt so an all-host outage cannot exhaust the five-minute job before incident reporting. 23 offline assertions cover identity, closed-route/header restrictions, redirect destination, incident deduplication/recovery and target aggregation. No secrets or response bodies enter issue content. Standard ubuntu-latest runs externally on GitHub; this repository is public, so standard hosted runner execution is free. No paid runner, artifact storage increase or spending change is enabled.

The existing incident marker is retained to avoid duplicate incidents. There is no SMTP integration or imported webshop credential in this workflow. Verify the owner's GitHub email notification destination before the one authorized synthetic alert; distinguish issue creation, notification submission and confirmed Inbox delivery. A successful job alone is not receipt evidence. Recent runs show multi-hour scheduling gaps despite the 15-minute cron, and GitHub may delay/drop schedules under load or disable inactive public repository schedules.

Sources: [GitHub billing](https://docs.github.com/en/actions/concepts/billing-and-usage), [scheduled event limitations](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule).

