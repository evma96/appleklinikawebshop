# TEST monitoring and human alerts

## Independent public check

`.github/workflows/test-availability.yml` checks the public TEST storefront and Back Office login with normal certificate validation every 15 minutes. It retries short maintenance interruptions, fails visibly in GitHub Actions and maintains one reusable GitHub issue. Only public endpoint names and generic failure codes enter the public issue. It never contains SSH access, response bodies, credentials or customer data. Enable GitHub notifications for that issue/repository to receive independent alerts.

GitHub scheduled workflows run from the repository default branch, currently `feature/project-bootstrap`. Publish only the two monitoring files there through a normal fast-forward feature-branch update; never modify `main` or app source there. GitHub can delay scheduled jobs; public-repository schedules may be disabled after prolonged repository inactivity. Verify recent workflow runs as part of operational checks. This is a minimal availability monitor, not a guaranteed real-time paging service.

`--exercise` proves failure, duplicate suppression and recovery without stopping the server. The first workflow push runs this controlled drill; later scheduled runs check actual availability.

## Internal material failures

The existing systemd monitor retains disk/container/HTTPS/PHP/SMTP/backup/TLS checks. `monitor-alert.py` sends material changes via the existing authenticated Hetzner SMTP service, independently of the WordPress container. First healthy startup is silent. An unchanged failure is silent; recovery produces one message. Transport errors retry after at least 15 minutes, remain visible in systemd status/logs, and never fall back to unauthenticated PHP mail.

Root-only `/etc/appleklinika-ops/alert-smtp.json` holds `host`, `port`, `username`, `password`, `from`, `recipient`, `qa_recipient`. The approved TEST sender is `info@appleklinika.hu`, SMTP `mail.your-server.de:587` STARTTLS. The human recipient is configurable; the QA drill goes only to the authorized owner QA inbox. No credentials belong in Git. Copy the existing supported FluentSMTP decrypted setting directly into root-private configuration on TEST, without displaying or transferring it through chat/local temporary files. Mailbox password rotation requires updating both transport configurations privately.

Delivery state is root-private under `/var/lib/appleklinika-ops/alert-delivery.json`. `--exercise` uses separate QA state and does not mask real incidents. SMTP acceptance is recorded separately from the owner's confirmation of actual Inbox delivery. The independent GitHub surface remains available if both the server and its SMTP path fail.

Offline checks: `python3 scripts/operations/external-monitor.py --self-test` and `python3 scripts/operations/monitor-alert.py --self-test`.

The monitor carries returned issue state through each run so delayed GitHub lists cannot duplicate the rapid failure/recovery drill. Existing exact-marker duplicate incidents are closed. Offline stale-list regression checks cover this behavior.
