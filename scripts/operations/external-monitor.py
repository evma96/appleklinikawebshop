#!/usr/bin/env python3
"""Public TEST HTTPS monitoring; no SSH access, response bodies or private data."""
import argparse, datetime, json, os, ssl, time, urllib.error, urllib.request
TARGETS = {'storefront': 'https://teszt.appleklinika.com/', 'backoffice_login': 'https://backoffice-teszt.appleklinika.com/wp-login.php'}
MARKER = '<!-- appleklinika-test-availability-v1 -->'
TITLE = 'Apple Klinika TEST availability'

def check(url):
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'AppleKlinikaAvailability/1.0'})
        with urllib.request.urlopen(req, timeout=20, context=ssl.create_default_context()) as r:
            return None if r.status == 200 and r.url.startswith('https://') else 'http_status'
    except (OSError, urllib.error.URLError, ssl.SSLError):
        return 'https_unavailable'

def api(method, path, payload=None):
    repo = os.environ['GITHUB_REPOSITORY']
    if repo != 'evma96/appleklinikawebshop': raise RuntimeError('Unexpected repository')
    req = urllib.request.Request('https://api.github.com/repos/'+repo+path, method=method,
        data=json.dumps(payload).encode() if payload is not None else None,
        headers={'Authorization':'Bearer '+os.environ['GITHUB_TOKEN'], 'Accept':'application/vnd.github+json', 'X-GitHub-Api-Version':'2022-11-28', 'User-Agent':'AppleKlinikaAvailability/1.0', 'Content-Type':'application/json'})
    with urllib.request.urlopen(req, timeout=20) as response: return json.load(response)

def transition(old, issues):
    if not old: return 'create' if issues else 'none'
    previous = [line[2:] for line in old.get('body','').splitlines() if line.startswith('- ')]
    if previous == issues and old['state'] == ('open' if issues else 'closed'): return 'none'
    return 'update'

def sync(issues, call=api):
    matches=[]
    for page in range(1,11):
        entries=call('GET',f'/issues?state=all&per_page=100&page={page}')
        matches += [e for e in entries if not e.get('pull_request') and MARKER in (e.get('body') or '')]
        if len(entries)<100: break
    else: raise RuntimeError('Issue search limit; refusing duplicate incident')
    if len(matches)>1: raise RuntimeError('Duplicate incident markers; manual review required')
    old=matches[0] if matches else None
    action=transition(old,issues)
    if action=='none': return 'unchanged'
    body=MARKER+'\n'+('TEST availability incident.' if issues else 'Recovered: both TEST HTTPS endpoints respond successfully.')+'\n\n'+'\n'.join('- '+i for i in issues)+'\n\nUpdated: '+datetime.datetime.now(datetime.timezone.utc).isoformat()+'\nNo server credentials or customer data are included. `qa_monitor_failure` is a controlled alert/recovery drill.'
    if action=='create': call('POST','/issues',{'title':TITLE,'body':body})
    else: call('PATCH','/issues/'+str(old['number']),{'body':body,'state':'open' if issues else 'closed'})
    return action

def self_test():
    state=[];mutations=[]
    def fake(method,path,data=None):
        if method=='GET': return state
        mutations.append((method,data))
        if method=='POST': state.append(dict(data,number=1,state='open'))
        else: state[0].update(data)
        return state[0]
    assert sync([],fake)=='unchanged'
    assert sync(['qa_monitor_failure'],fake)=='create'
    assert sync(['qa_monitor_failure'],fake)=='unchanged'
    assert sync([],fake)=='update'
    assert sync([],fake)=='unchanged'
    assert sync(['https_unavailable'],fake)=='update'
    assert len(state)==1 and len(mutations)==3
    print('7 external-monitor failure/dedup/recovery checks passed; no network.')

def main():
    p=argparse.ArgumentParser();p.add_argument('--self-test',action='store_true');p.add_argument('--exercise',action='store_true');a=p.parse_args()
    if a.self_test: return self_test()
    issues=[]
    # Retry avoids raising an incident for a normal short TEST backup pause.
    for attempt in range(3):
        issues=[name+':'+error for name,url in TARGETS.items() if (error:=check(url))]
        if not issues: break
        if attempt<2: time.sleep(30)
    if a.exercise and not issues:
        sync(['qa_monitor_failure']);sync(['qa_monitor_failure']);sync([])
        print('Controlled external failure/recovery drill completed.')
    print(json.dumps({'status':'alert' if issues else 'ok','issues':issues,'incident':sync(issues)}))
    if issues: raise SystemExit(1)
if __name__=='__main__': main()
