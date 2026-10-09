#!/usr/bin/env python3
"""Standalone TEST SMTP alerts; independent of WordPress/container availability."""
import argparse, email.message, email.utils, datetime, json, os, pathlib, smtplib, socket, ssl, time

ROOT = pathlib.Path('/var/lib/appleklinika-ops')
CONFIG = pathlib.Path('/etc/appleklinika-ops/alert-smtp.json')


def notify(config, issues):
    if config['host'] != 'mail.your-server.de' or config['port'] != 587:
        raise ValueError('Unexpected TEST SMTP endpoint')
    if config['from'] != 'info@appleklinika.hu' or config['username'] != config['from']:
        raise ValueError('Unexpected approved sender')
    message = email.message.EmailMessage()
    message['Date'] = email.utils.formatdate(localtime=False, usegmt=True)
    message['Message-ID'] = email.utils.make_msgid(domain='appleklinika.hu')
    event_time = datetime.datetime.now(datetime.timezone.utc).isoformat()
    message['From'] = 'Apple Klinika <'+config['from']+'>'
    message['Reply-To'] = config['from']
    message['To'] = config['recipient']
    message['Subject'] = '[Apple Klinika TEST] '+('Üzemeltetési riasztás' if issues else 'Helyreállt a működés')
    message.set_content('Esemény ideje (UTC): '+event_time+'\nTEST környezet. '+('Ellenőrzést igényel: '+', '.join(issues) if issues else 'A korábban jelzett eltérés megszűnt.')+'\n\nAzonos állapotról nem küldünk ismétlődő levelet. A qa_monitor_failure ellenőrzött értesítési próba.\n')
    # No SMTP debug mode or unauthenticated sendmail fallback.
    with smtplib.SMTP(config['host'], config['port'], timeout=20) as smtp:
        smtp.ehlo(); smtp.starttls(context=ssl.create_default_context()); smtp.ehlo()
        smtp.login(config['username'], config['password'])
        refused = smtp.send_message(message)
        if refused: raise RuntimeError('Recipient refused')


def transition(state, issues, now, send):
    issues = sorted(set(issues))
    if 'delivered' not in state and not issues:
        return {'delivered': [], 'last_attempt': 0}, 'initialized'
    if state.get('delivered') == issues:
        return state, 'unchanged'
    if now - state.get('last_attempt', 0) < 900:
        return state, 'retry_deferred'
    updated = dict(state, last_attempt=now)
    try: send(issues)
    except Exception:
        # Intentionally never log SMTP exceptions, responses or credentials.
        return updated, 'transport_failed'
    return {'delivered': issues, 'last_attempt': 0, 'delivered_at': now}, 'smtp_accepted'


def self_test():
    sent=[];state={}
    state,outcome=transition(state,[],1000,sent.append);assert outcome=='initialized'
    state,outcome=transition(state,['qa_monitor_failure'],1001,sent.append);assert outcome=='smtp_accepted'
    state,outcome=transition(state,['qa_monitor_failure'],1002,sent.append);assert outcome=='unchanged'
    state,outcome=transition(state,[],1003,sent.append);assert outcome=='smtp_accepted'
    state,outcome=transition(state,[],1004,sent.append);assert outcome=='unchanged'
    assert sent==[['qa_monitor_failure'],[]]
    def fail(_): raise RuntimeError('Private SMTP response must not escape')
    state,outcome=transition(state,['http'],2000,fail);assert outcome=='transport_failed' and state['delivered']==[]
    state,outcome=transition(state,['http'],2100,sent.append);assert outcome=='retry_deferred'
    state,outcome=transition(state,['http'],3000,sent.append);assert outcome=='smtp_accepted'
    print('9 internal alert failure/dedup/recovery/retry checks passed; no email sent.')


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--self-test',action='store_true');parser.add_argument('--exercise',action='store_true');args=parser.parse_args()
    if args.self_test: return self_test()
    if socket.gethostname() != 'appleklinika-staging-01': raise SystemExit('TEST host only')
    os.umask(0o077)
    if not CONFIG.exists() or CONFIG.stat().st_mode & 0o077: raise SystemExit('Private SMTP configuration missing or unsafe permissions')
    config=json.loads(CONFIG.read_text());ROOT.mkdir(exist_ok=True)
    path=ROOT/('alert-exercise.json' if args.exercise else 'alert-delivery.json')
    state=json.loads(path.read_text()) if path.exists() else {}
    cases=[['qa_monitor_failure'],['qa_monitor_failure'],[]] if args.exercise else [json.loads((ROOT/'monitor.json').read_text())['issues']]
    if args.exercise: config['recipient']=config['qa_recipient']
    for issues in cases:
        state,outcome=transition(state,issues,int(time.time()),lambda current: notify(config,current))
        temporary=path.with_suffix('.tmp');temporary.write_text(json.dumps(state)+'\n');temporary.replace(path)
        print(json.dumps({'alert_result':outcome,'issue_count':len(issues)}))
        if outcome=='transport_failed': raise SystemExit(1)

if __name__=='__main__': main()
