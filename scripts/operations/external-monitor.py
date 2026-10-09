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

def synchronize(cases, call=api):
    # Read once: GitHub list endpoints can lag immediately after an issue mutation.
    matches=[]
    for page in range(1,11):
        entries=call('GET',f'/issues?state=all&per_page=100&page={page}')
        matches += [e for e in entries if not e.get('pull_request') and MARKER in (e.get('body') or '')]
        if len(entries)<100: break
    else: raise RuntimeError('Issue search limit; refusing duplicate incident')
    matches.sort(key=lambda e:e['number'])
    old=matches[0] if matches else None
    # Retire only duplicate incidents carrying our exact machine marker.
    for duplicate in matches[1:]:
        if duplicate['state']=='open':
            call('PATCH','/issues/'+str(duplicate['number']),{'state':'closed','body':'Superseded by #'+str(old['number'])+'. '+MARKER})
    results=[]
    for issues in cases:
        action=transition(old,issues)
        if action=='none': results.append('unchanged');continue
        body=MARKER+'\n'+('TEST availability incident.' if issues else 'Recovered: both TEST HTTPS endpoints respond successfully.')+'\n\n'+'\n'.join('- '+i for i in issues)+'\n\nUpdated: '+datetime.datetime.now(datetime.timezone.utc).isoformat()+'\nNo server credentials or customer data are included. `qa_monitor_failure` is a controlled alert/recovery drill.'
        if action=='create': old=call('POST','/issues',{'title':TITLE,'body':body})
        else: old=call('PATCH','/issues/'+str(old['number']),{'body':body,'state':'open' if issues else 'closed'})
        results.append(action)
    return results

def self_test():
    state=[];mutations=[];reads=[]
    def fake(method,path,data=None):
        if method=='GET': reads.append(path);return []  # Deliberately stale list.
        mutations.append((method,data))
        if method=='POST': state.append(dict(data,number=1,state='open'))
        else: state[0].update(data)
        return dict(state[0])
    cases=[[],['qa_monitor_failure'],['qa_monitor_failure'],[],[],['https_unavailable']]
    assert synchronize(cases,fake)==['unchanged','create','unchanged','update','unchanged','update']
    assert len(state)==1 and len(mutations)==3 and len(reads)==1
    assert state[0]['state']=='open'
    assert transition({'state':'closed','body':MARKER},[])=='none'
    assert transition({'state':'open','body':MARKER+'\n- http'},['http'])=='none'
    print('5 external-monitor stale-list/failure/dedup/recovery assertions passed; no network.')

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
        result=synchronize([['qa_monitor_failure'],['qa_monitor_failure'],[]])
        print('Controlled external failure/recovery drill completed.')
    else: result=synchronize([issues])
    print(json.dumps({'status':'alert' if issues else 'ok','issues':issues,'incident':result}))
    if issues: raise SystemExit(1)
if __name__=='__main__': main()
