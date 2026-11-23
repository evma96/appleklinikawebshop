#!/usr/bin/env python3
"""Independent closed-main/STAFF and TEST HTTPS monitoring; no credentials or private content."""
import argparse, datetime, json, os, ssl, time, urllib.error, urllib.request
from html.parser import HTMLParser
from concurrent.futures import ThreadPoolExecutor
STOREFRONT_MODE = os.environ.get('STOREFRONT_MODE', 'closed')
if STOREFRONT_MODE not in ('closed','open'): raise RuntimeError('Explicit closed/open monitor mode required')
TARGETS = {
    'test_storefront': ('https://teszt.appleklinika.com/', 200),
    'test_backoffice_login': ('https://backoffice-teszt.appleklinika.com/wp-login.php', 200),
    'main_storefront': ('https://appleklinika.hu/', 200 if STOREFRONT_MODE == 'open' else 401),
    'main_login': ('https://appleklinika.hu/wp-login.php', 200),
    'closed_staff': ('https://staff.appleklinika.hu/', 401),
    'staff_login': ('https://staff.appleklinika.hu/wp-login.php', 200),
    'main_www': ('https://www.appleklinika.hu/', 302),
}
MARKER = '<!-- appleklinika-test-availability-v1 -->'
TITLE = 'Apple Klinika closed main / STAFF / TEST availability'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None

def response_error(url, expected, status, headers):
    if status != expected: return 'unexpected_http_status_'+str(status)
    if expected == 401:
        # REVIEW basic-auth is not a successful closed STAFF route.
        if headers.get('WWW-Authenticate'): return 'unexpected_basic_auth_route'
        if 'no-store' not in headers.get('Cache-Control','').lower(): return 'private_cache_policy_missing'
        if 'cookie' not in headers.get('Vary','').lower(): return 'cookie_variance_missing'
    if expected == 302 and headers.get('Location') != 'https://appleklinika.hu/': return 'unexpected_redirect'
    return None

class PageIdentity(HTMLParser):
    def __init__(self):
        super().__init__(); self.title=''; self.in_title=False; self.ids=set(); self.login_action=None
    def handle_starttag(self, tag, attrs):
        attrs=dict(attrs)
        if tag=='title': self.in_title=True
        if attrs.get('id'): self.ids.add(attrs['id'])
        if tag=='form' and attrs.get('id')=='loginform': self.login_action=attrs.get('action')
    def handle_endtag(self, tag):
        if tag=='title': self.in_title=False
    def handle_data(self, text):
        if self.in_title: self.title+=text

def identity_error(url, body):
    page=PageIdentity(); page.feed(body)
    if url.endswith('/wp-login.php'):
        brand='appleklinika.com' if 'backoffice-teszt.' in url else 'Apple Klinika'
        return None if (brand in page.title and 'WordPress' in page.title and
            {'loginform','user_login','user_pass'} <= page.ids and page.login_action==url) else 'application_identity_mismatch'
    if url in ('https://teszt.appleklinika.com/','https://appleklinika.hu/'):
        # Public titles are editorial/localized text. Identify the rendered shop
        # by its theme, product search and homepage component instead.
        return None if {'appleklinika-theme-css','ak-product-search-field','ak-home-slide-0'} <= page.ids else 'application_identity_mismatch'
    return 'unknown_identity_target'

def check(target, opener=None):
    url,expected=target
    try:
        req=urllib.request.Request(url,headers={'User-Agent':'AppleKlinikaAvailability/1.1'})
        client=opener or urllib.request.build_opener(NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context()))
        try: response=client.open(req,timeout=20)
        except urllib.error.HTTPError as error: response=error
        with response as r:
            error=response_error(url,expected,r.status,r.headers)
            if error or expected!=200: return error
            return identity_error(url,r.read(131072).decode('utf-8','replace'))
    except (OSError,urllib.error.URLError,ssl.SSLError): return 'https_unavailable'

def collect(checker=check):
    # Seven sequential 20-second failures would exceed the existing five-minute
    # workflow deadline before an alert. Bound each round to one request timeout.
    with ThreadPoolExecutor(max_workers=len(TARGETS)) as pool:
        results=list(pool.map(checker,TARGETS.values()))
    return [name+':'+error for name,error in zip(TARGETS,results) if error]

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
        body=MARKER+'\n'+('Closed main / STAFF / TEST availability incident.' if issues else 'Recovered: all configured closed main / STAFF / TEST HTTPS checks match their expected responses.')+'\n\n'+'\n'.join('- '+i for i in issues)+'\n\nUpdated: '+datetime.datetime.now(datetime.timezone.utc).isoformat()+'\nNo server credentials or customer data are included. `qa_monitor_failure` is a controlled alert/recovery drill.'
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
    headers={'Cache-Control':'private, no-store','Vary':'Cookie'}
    assert response_error('https://appleklinika.hu/',401,401,headers) is None
    assert response_error('https://appleklinika.hu/',401,200,headers)=='unexpected_http_status_200'
    assert response_error('https://appleklinika.hu/',401,401,{**headers,'WWW-Authenticate':'Basic'})=='unexpected_basic_auth_route'
    assert response_error('https://appleklinika.hu/',401,401,{'Vary':'Cookie'})=='private_cache_policy_missing'
    assert response_error('https://appleklinika.hu/',401,401,{'Cache-Control':'private, no-store'})=='cookie_variance_missing'
    assert response_error('https://www.appleklinika.hu/',302,302,{'Location':'https://appleklinika.hu/'}) is None
    assert response_error('https://www.appleklinika.hu/',302,302,{'Location':'https://teszt.appleklinika.com/'})=='unexpected_redirect'
    assert response_error('https://appleklinika.hu/wp-login.php',200,503,{})=='unexpected_http_status_503'
    class Response:
        status=401
        headers={'Cache-Control':'private, no-store','Vary':'Cookie'}
        def __enter__(self): return self
        def __exit__(self,*args): pass
    class Opener:
        def open(self,request,timeout): return Response()
    assert check(('https://appleklinika.hu/',401),Opener()) is None
    login='<title>Bejelentkezés ‹ Apple Klinika — WordPress</title><form id="loginform" action="https://appleklinika.hu/wp-login.php"><input id="user_login"><input id="user_pass"></form>'
    assert identity_error('https://appleklinika.hu/wp-login.php',login) is None
    assert identity_error('https://staff.appleklinika.hu/wp-login.php',login)=='application_identity_mismatch'
    assert identity_error('https://appleklinika.hu/wp-login.php','<title>OK</title>')=='application_identity_mismatch'
    assert identity_error('https://appleklinika.hu/wp-login.php',login.replace('Apple Klinika','Other store'))=='application_identity_mismatch'
    assert identity_error('https://appleklinika.hu/wp-login.php',login.replace('user_pass','unrelated'))=='application_identity_mismatch'
    store='<title>appleklinika.com</title><link id="appleklinika-theme-css"><input id="ak-product-search-field"><div id="ak-home-slide-0"></div>'
    assert identity_error('https://teszt.appleklinika.com/',store) is None
    assert identity_error('https://teszt.appleklinika.com/','<title>appleklinika.com</title>')=='application_identity_mismatch'
    assert collect(lambda target: None)==[]
    assert collect(lambda target: 'https_unavailable' if target==TARGETS['main_storefront'] else None)==['main_storefront:https_unavailable']
    assert identity_error('https://appleklinika.hu/',store) is None
    assert identity_error('https://appleklinika.hu/','<title>Apple Klinika</title>')=='application_identity_mismatch'
    assert identity_error('https://appleklinika.hu/',login)=='application_identity_mismatch'
    # The same actual storefront must pass independently of title spelling or SEO text.
    for url in ('https://appleklinika.hu/','https://teszt.appleklinika.com/'):
        for title in ('Appleklinika','Apple Klinika','APPLE KLINIKA – Ellenőrzött készülékek',''):
            assert identity_error(url,store.replace('appleklinika.com',title)) is None
        for marker in ('appleklinika-theme-css','ak-product-search-field','ak-home-slide-0'):
            assert identity_error(url,store.replace(marker,'missing-marker'))=='application_identity_mismatch'
        assert identity_error(url,'<!-- '+store+' -->')=='application_identity_mismatch'
    class PageResponse(Response):
        status=200
        headers={}
        def read(self,limit): return store.replace('appleklinika.com','Apple Klinika').encode()[:limit]
    class PageOpener:
        def open(self,request,timeout): return PageResponse()
    assert check(('https://appleklinika.hu/',200),PageOpener()) is None
    class FailingOpener:
        def __init__(self,error): self.error=error
        def open(self,request,timeout): raise self.error
    from io import BytesIO
    faults=[
        (urllib.error.HTTPError('https://appleklinika.hu/',503,'Unavailable',{},BytesIO()),'unexpected_http_status_503'),
        (urllib.error.HTTPError('https://appleklinika.hu/',404,'Missing',{},BytesIO()),'unexpected_http_status_404'),
        (ssl.SSLCertVerificationError('fixture certificate failure'),'https_unavailable'),
        (urllib.error.URLError('fixture DNS failure'),'https_unavailable'),
        (TimeoutError('fixture timeout'),'https_unavailable'),
    ]
    for fault,expected in faults:
        error=check(('https://appleklinika.hu/',200),FailingOpener(fault))
        assert error==expected
        # Execute the unchanged incident/alert path with a fake GitHub API only.
        state.clear();mutations.clear();reads.clear()
        assert synchronize([['main_storefront:'+error],['main_storefront:'+error],[]],fake)==['create','unchanged','update']
        assert mutations[0][0]=='POST' and mutations[-1][1]['state']=='closed' and len(mutations)==2
    print('58 external-monitor identity/routing/privacy/HTTP/TLS/timeout/alert/recovery assertions passed; no network.')

def main():
    p=argparse.ArgumentParser();p.add_argument('--self-test',action='store_true');p.add_argument('--exercise',action='store_true');a=p.parse_args()
    if a.self_test: return self_test()
    issues=[]
    # Retry avoids raising an incident for a normal short TEST backup pause.
    for attempt in range(3):
        issues=collect()
        if not issues: break
        if attempt<2: time.sleep(30)
    if a.exercise and not issues:
        result=synchronize([['qa_monitor_failure'],['qa_monitor_failure'],[]])
        print('Controlled external failure/recovery drill completed.')
    else: result=synchronize([issues])
    print(json.dumps({'status':'alert' if issues else 'ok','issues':issues,'incident':result}))
    if issues: raise SystemExit(1)
if __name__=='__main__': main()
