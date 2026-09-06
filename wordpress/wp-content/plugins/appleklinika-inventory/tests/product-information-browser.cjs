// LOCAL-only real product presentation gate. One isolated QA cart; no order.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const out=process.env.EVIDENCE_DIR;
assert(out,'EVIDENCE_DIR must be an ignored local artifact directory');fs.mkdirSync(out,{recursive:true});
const base='http://localhost:8080';
let count=0;
const check=(ok,message)=>{count++;assert(ok,message);};
const norm=text=>text.replace(/\s+/g,' ').trim();
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_EXECUTABLE});
 const report=[];
 try{for(const width of [1440,390]){
  const ctx=await browser.newContext({viewport:{width,height:1000},hasTouch:width===390});
  const page=await ctx.newPage(),errors=[],warnings=[];
  page.on('pageerror',e=>errors.push(e.message));
  page.on('console',msg=>{if(msg.type()!=='error')return;const detail=msg.text()+' '+msg.location().url;
   if(msg.location().url===base+'/favicon.ico'&&msg.text().includes('404'))warnings.push(detail);else errors.push(detail);
  });
  for(const id of [288,314,366]){
   await page.goto(base+'/?post_type=product&p='+id);
   const stage=page.locator('[data-appleklinika-stage-image]');await stage.evaluate(img=>img.decode());
   check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'No overflow '+id+'/'+width);
   // Inspect customer-facing prose, not the preserved canonical SKU or Woo's hidden quantity label.
   const prose=await page.locator('[data-appleklinika-product-title], [data-appleklinika-product-lead], .appleklinika-product-below-hero, .ak-single-product__description, .appleklinika-product-facts > section, .ak-single-product__related-title').allInnerTexts();
   check(!/selector|teszttermék|WooCommerce|helyi fejleszt/i.test(prose.join(' ')),'No visible developer prose');
   check(await page.locator('.ak-header-actions .ak-header-pill').count()===3,'Approved header preserved');
   const hint=page.locator('.appleklinika-product-gallery__hint');
   check(await hint.locator('svg').count()===1,'Neutral magnifier icon');
   const imageBox=await stage.boundingBox(),hintBox=await hint.boundingBox();
   check(imageBox.y+imageBox.height<=hintBox.y+1,'Zoom hint does not cover photograph');
   check(await page.locator('.appleklinika-product-gallery__stage').evaluate(n=>getComputedStyle(n).borderTopWidth==='0px' && getComputedStyle(n).backgroundColor==='rgba(0, 0, 0, 0)'),'No grey gallery container');
   check(await page.locator('[data-selector-option].is-selected').evaluateAll(nodes=>nodes.every(n=>getComputedStyle(n).borderTopColor==='rgb(214, 0, 28)')),'Intentional red option states preserved');
   for(let n=0;n<3;n++){
    await page.locator('[data-gallery-index="'+n+'"]').click();await stage.evaluate(img=>img.decode());
    check(await page.locator('[data-gallery-index="'+n+'"]').getAttribute('aria-pressed')==='true','Thumbnail switches');
   }
   await page.locator('[data-gallery-open]').click();const modal=page.locator('dialog.ak-image-viewer');
   await modal.waitFor({state:'visible'});await modal.locator('img').evaluate(img=>img.decode());
   await page.waitForFunction(()=>!document.querySelector('dialog.ak-image-viewer img').hidden);
   check(await modal.evaluate(node=>node.matches(':modal')),'Native gallery opens');
   check(await modal.evaluate(n=>getComputedStyle(n).backgroundColor==='rgb(255, 255, 255)' && getComputedStyle(n).borderRadius==='0px' && getComputedStyle(n).boxShadow==='none'),'Viewport viewer has no framed modal surface');
   check(await page.evaluate(()=>document.documentElement.classList.contains('ak-image-viewer-lock') && document.body.style.position==='fixed'),'Background scroll stays locked');
   check(await modal.locator('button:visible').evaluateAll(nodes=>nodes.every(n=>n.getBoundingClientRect().width>=44 && n.getBoundingClientRect().height>=44)),'Viewer touch controls at least 44px');
   await modal.locator('[data-direction="1"]').click();await modal.locator('img').evaluate(img=>img.decode());
   await modal.locator('[data-zoom]').click();
   check(await modal.locator('.ak-image-viewer__canvas').getAttribute('data-zoomed')==='true','Detail zoom works');
   if(id===288) await page.screenshot({path:path.join(out,'after-viewer-'+width+'.png')});
   await modal.locator('[data-close]').click();check(!await modal.isVisible(),'Close works');
   check(await page.evaluate(()=>!document.documentElement.classList.contains('ak-image-viewer-lock') && document.body.style.position!=='fixed'),'Background scroll restored');
   await page.locator('[data-gallery-open]').click();await modal.waitFor({state:'visible'});await page.keyboard.press('ArrowRight');
   check((await modal.locator('.ak-image-viewer__count').innerText()).trim()==='2 / 3','Keyboard image navigation');
   await page.keyboard.press('Escape');check(!await modal.isVisible(),'ESC closes viewer');
   check(await page.locator('[data-gallery-open]').evaluate(n=>n===document.activeElement),'Focus returns to opener');
   const reference=page.locator('.appleklinika-product-facts details');
   await reference.locator('summary').click();
   check(await reference.locator('.appleklinika-sku-row dd').isVisible(),'Exact SKU remains accessible');
   check(await reference.locator('dd').evaluateAll(nodes=>nodes.every(n=>n.scrollWidth<=n.clientWidth+1)),'Long identifiers wrap');
   if(id===288) await page.locator('.ak-single-product__data').screenshot({path:path.join(out,'after-expanded-reference-'+width+'.png')});
   await reference.locator('summary').click();
   const official=page.locator('.appleklinika-spec-disclosure--all');
   if(await official.count()) {
    check(await page.locator('.appleklinika-spec-highlights .appleklinika-spec-row').count()===4,'Four useful technical highlights');
    await official.locator('summary').click();check(await official.locator('.appleklinika-spec-table').isVisible(),'Native technical disclosure');
    check(!/Gyártói forrás|Hivatalos műszaki adatok|Adatok dátuma|2026-06-23/.test(await official.innerText()),'Internal source/date metadata removed from presentation');
    check(await official.locator('dd').evaluateAll(nodes=>nodes.every(n=>n.scrollWidth<=n.clientWidth+1)),'Long specifications wrap within their columns');
    await official.locator('summary').click();
   }
   const related=page.locator('.ak-single-product__related-card').first();const relatedUrl=await related.getAttribute('href');
   await Promise.all([page.waitForURL(relatedUrl),related.click()]);check(await page.locator('[data-appleklinika-product-title]').isVisible(),'Similar product navigation');
  }
  // All enabled choices on a stocked representative; use the unchanged payload as pricing oracle.
  await page.goto(base+'/?post_type=product&p=288');
  for(const group of ['color','storage','condition','battery']){
   const options=await page.locator('[data-selector-group="'+group+'"] [data-selector-option]').evaluateAll(nodes=>nodes.map(n=>n.dataset.optionValue));
   for(const key of options){
    const option=page.locator('[data-selector-group="'+group+'"] [data-option-value="'+key+'"]');
    if(await option.isDisabled()) {check(await option.getAttribute('aria-disabled')==='true','Unavailable choice remains disabled');continue;}
    await option.click();
    const state=await page.evaluate(()=>{
     const values=Object.fromEntries([...document.querySelectorAll('[data-selector-group]')].map(g=>[g.dataset.selectorGroup,g.querySelector('.is-selected')?.dataset.optionValue]));
     const product=JSON.parse(document.querySelector('#appleklinika-product-selector-data').textContent).find(p=>['color','storage','condition'].every(k=>p.values[k]===values[k]));
     const extra=Number(document.querySelector('[data-selector-group=battery] .is-selected').dataset.extraPrice||0);
     return {product,extra};
    });
    check(!!state.product,'Native selector chooses a matching real product');
    check(await page.locator('[data-selector-group="'+group+'"] .is-selected').count()===1,'Single selected state');
    check(norm(await page.locator('[data-appleklinika-product-title]').innerText())===norm(state.product.title),'Current product title');
    const actualPrice=Number((await page.locator('.appleklinika-price-stack__current').innerText()).replace(/\D/g,''));
    check(actualPrice===state.product.salePrice+state.extra,'Price and existing battery surcharge unchanged');
    check(norm(await page.locator('[data-appleklinika-stock-badge]').innerText())===norm(state.product.stockLabel),'Dynamic stock label');
    for(const [selector,field] of [['.ak-single-product__description','descriptionHtml'],['[data-appleklinika-product-facts]','factsHtml'],['[data-appleklinika-quick-facts]','quickFactsHtml'],['[data-appleklinika-product-lead]','leadHtml'],['[data-appleklinika-trust]','trustHtml']]){
     const expected=await page.evaluate(html=>new DOMParser().parseFromString(html,'text/html').body.textContent,state.product[field]);
     check(norm(await page.locator(selector).textContent())===norm(expected),'Latest selected facts: '+field);
    }
    check(await page.locator('[data-selector-group=battery] [data-option-value=standard] .appleklinika-config-card__meta').textContent()===state.product.batteryHealthLabel,'Latest physical battery health');
   }
  }
  if(width===390){
   // Fresh anonymous context only. One ordinary add, then native removal; never visit checkout.
   await page.goto(base+'/?post_type=product&p=288');
   check((await page.locator('.ak-cart-count').innerText()).trim()==='0','QA cart starts empty');
   await page.locator('.single_add_to_cart_button').click();
   await page.waitForFunction(()=>document.querySelector('.ak-cart-count').textContent.trim()==='1');
   check((await page.locator('.ak-cart-count').innerText()).trim()==='1','Cart action and live badge');
   await ctx.storageState({path:path.join(out,'qa-cart-storage-state.json')});
   await Promise.all([page.waitForURL(url=>url.searchParams.get('page_id')==='8'),page.locator('.ak-header-shell .ak-cart-link').click()]);
   const remove=page.locator('a.ak-cart-item__remove');await remove.click();
   await page.waitForFunction(()=>document.querySelector('.ak-cart-count')?.textContent.trim()==='0');
   check((await page.locator('.ak-cart-count').innerText()).trim()==='0','Exact QA cart item removed');
   await ctx.storageState({path:path.join(out,'qa-cart-storage-state.json')});
  }
  check(errors.length===0,'No new console errors: '+errors.join('; '));
  report.push({width,result:'PASS',errors,warnings});await ctx.close();console.log('Product information browser PASS '+width);
 }fs.writeFileSync(path.join(out,'functional-results.json'),JSON.stringify({assertions:count,report},null,2));console.log('Product information browser passed: '+count+' assertions; QA cart empty, no order.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
