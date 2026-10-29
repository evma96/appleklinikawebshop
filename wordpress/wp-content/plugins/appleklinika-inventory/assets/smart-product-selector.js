/* Read-only navigation over a single batched Woo product projection. */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.AppleklinikaSmartSelector = api;
})(typeof window === 'undefined' ? {} : window, function () {
    'use strict';
    const groups = ['color', 'storage', 'condition'];
    const technicalDifferences = (a, b) => [...new Set([...Object.keys(a.technical || {}), ...Object.keys(b.technical || {})])]
        .filter(key => (a.technical?.[key]?.value || '') !== (b.technical?.[key]?.value || ''));
    const primaryDifferences = (a, b) => groups.filter(key => (a.values?.[key] || '') !== (b.values?.[key] || ''));
    const formatPrice = amount => Math.round(Number(amount) || 0).toLocaleString('hu-HU') + ' Ft';
    function createIndex(products) {
        const byId = new Map(products.map(p => [Number(p.id), p]));
        const options = new Map();
        const key = (model, group, value) => JSON.stringify([model, group, value]);
        products.forEach(p => {
            if (!p.available || p.selectorEligible === false || !p.modelKey || !p.values) return;
            groups.forEach(group => {
                const k = key(p.modelKey, group, p.values[group] || '');
                if (!options.has(k)) options.set(k, []);
                options.get(k).push(p);
            });
        });
        function classify(current, group, value) {
            if (!current?.modelKey || !groups.includes(group)) return {state:'unavailable', products:[]};
            const candidates = options.get(key(current.modelKey, group, value)) || [];
            const exact = candidates.filter(p => groups.every(other => other === group || (p.values[other] || '') === (current.values[other] || '')) && technicalDifferences(current,p).length === 0);
            if (exact.length) return {state:'exact', products:exact.slice().sort((a,b)=>a.id-b.id)};
            const rank = p => [technicalDifferences(current,p).length, ...['storage','color','condition'].map(g => g === group || p.values[g] === current.values[g] ? 0 : 1), Number(p.id)];
            const alternatives = candidates.slice().sort((a,b)=>{const aa=rank(a),bb=rank(b);for(let i=0;i<aa.length;i++){if(aa[i]!==bb[i])return aa[i]-bb[i];}return 0;});
            return {state:alternatives.length?'alternative':'unavailable', products:alternatives};
        }
        return {byId, classify};
    }
    function mount({products, initialId, onSelect}) {
        const index = createIndex(products);
        let current = index.byId.get(Number(initialId));
        if (!current) return;
        let opener = null;
        const panel = document.createElement('section');
        panel.className='ak-selector-alternatives';panel.id='ak-selector-alternatives';panel.hidden=true;
        panel.setAttribute('role','region');panel.setAttribute('aria-labelledby','ak-selector-alternatives-title');
        const node=(tag,className,text)=>{const el=document.createElement(tag);el.className=className;if(text!==undefined)el.textContent=text;return el;};
        function close(restoreFocus=true) {
            panel.hidden=true;
            if(opener){opener.setAttribute('aria-expanded','false');if(restoreFocus)opener.focus();}
            opener=null;
        }
        function select(product) {
            if(!product.available || product.selectorEligible === false || product.modelKey!==current.modelKey)return;
            close(false);
            if(product.id!==current.id){current=product;onSelect(product);document.title=product.title+' – Apple Klinika';}
            refresh();
        }
        function refresh() {
            document.querySelectorAll('[data-selector-group]').forEach(group=>{
                const name=group.dataset.selectorGroup;if(!groups.includes(name))return;
                group.querySelectorAll('[data-selector-option]').forEach(option=>{
                    const result=index.classify(current,name,option.dataset.optionValue);
                    const selected=option.dataset.optionValue===current.values[name];
                    option.disabled=result.state==='unavailable';
                    option.classList.toggle('is-unavailable',option.disabled);
                    option.classList.toggle('is-alternative',result.state==='alternative');
                    option.classList.toggle('is-selected',selected);option.classList.toggle('is-active',selected);
                    option.dataset.availability=result.state;
                    option.setAttribute('aria-disabled',String(option.disabled));option.setAttribute('aria-pressed',String(selected));
                    if(option.disabled)option.setAttribute('tabindex','-1');else option.removeAttribute('tabindex');
                    const chooser=result.state==='alternative'||result.products.length>1;
                    if(chooser){option.setAttribute('aria-controls',panel.id);option.setAttribute('aria-expanded','false');}
                    else{option.removeAttribute('aria-controls');option.removeAttribute('aria-expanded');}
                    let text='';
                    if(result.state==='alternative')text='Más kivitelben';
                    else if(result.products.length>1)text='Több készülék';
                    else if(result.products.length===1){const delta=Number(result.products[0].salePrice)-Number(current.salePrice);if(Math.abs(delta)>=1)text=(delta>0?'+':'−')+formatPrice(Math.abs(delta));}
                    let meta=option.querySelector('.appleklinika-config-card__meta');
                    if(!meta && text){meta=node('span','appleklinika-config-card__meta');option.querySelector('.appleklinika-config-card__content').append(meta);}
                    if(meta){meta.textContent=text;meta.hidden=!text;}
                    if(selected){const label=group.querySelector('[data-selected-label]');if(label)label.textContent=option.dataset.optionLabel;}
                });
            });
        }
        function open(group, option, result) {
            close(false);opener=option;panel.replaceChildren();
            const heading=node('div','ak-selector-alternatives__heading');
            const title=node('h4','',result.state==='alternative'?'Elérhető más kivitelek':'Válassz konkrét készüléket');title.id='ak-selector-alternatives-title';
            const dismiss=node('button','ak-selector-alternatives__close','Bezárás');dismiss.type='button';dismiss.addEventListener('click',()=>close());heading.append(title,dismiss);panel.append(heading);
            panel.append(node('p','ak-selector-alternatives__intro',result.state==='alternative'?'A választás más tulajdonságokat is érint. Nézd meg a különbségeket.':'Több külön készülék érhető el ezzel a beállítással.'));
            const list=node('div','ak-selector-alternatives__list');
            result.products.forEach(product=>{
                const row=node('a','ak-selector-alternatives__device');row.href=product.url;row.dataset.alternativeProduct=String(product.id);
                const image=product.images?.[0];if(image){const img=node('img','');img.src=image.thumb||image.url;img.alt='';img.loading='lazy';row.append(img);}
                const content=node('span','ak-selector-alternatives__content');content.append(node('strong','',product.title));
                const fields=node('span','ak-selector-alternatives__facts');
                groups.filter(g=>g!=='storage'||document.querySelector('[data-selector-group="storage"]')).forEach(g=>{
                    const labels={color:'Szín',storage:'Tárhely',condition:'Állapot'};
                    fields.append(node('span',product.values[g]!==current.values[g]?'is-difference':'',labels[g]+': '+product.labels[g]));
                });
                const techKeys=[...new Set([...Object.keys(current.technical||{}),...Object.keys(product.technical||{})])];
                techKeys.forEach(key=>{
                    const field=product.technical?.[key]||current.technical[key];
                    fields.append(node('span',technicalDifferences(current,product).includes(key)?'is-difference':'',field.label+': '+(product.technical?.[key]?.display||'Nincs megadva')));
                });
                content.append(fields);content.append(node('strong','ak-selector-alternatives__price',formatPrice(product.salePrice)));
                content.append(node('span','ak-selector-alternatives__action',product.id===current.id?'Jelenleg ezt nézed':'Ezt a készüléket nézem meg →'));row.append(content);
                row.addEventListener('click',event=>{if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;event.preventDefault();select(product);option.focus();});list.append(row);
            });
            panel.append(list);group.append(panel);panel.hidden=false;option.setAttribute('aria-expanded','true');dismiss.focus();
        }
        document.querySelectorAll('[data-selector-group]').forEach(group=>{
            if(!groups.includes(group.dataset.selectorGroup))return;
            group.addEventListener('click',event=>{
                const option=event.target.closest('[data-selector-option]');if(!option||!group.contains(option)||option.disabled)return;
                const result=index.classify(current,group.dataset.selectorGroup,option.dataset.optionValue);
                if(result.state==='unavailable')return;
                if(result.state==='exact'&&result.products.length===1)select(result.products[0]);else if(opener===option&&!panel.hidden)close();else open(group,option,result);
            });
        });
        document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden){event.preventDefault();close();}});
        document.addEventListener('click',event=>{if(!panel.hidden&&!panel.contains(event.target)&&!opener?.contains(event.target))close(false);});
        refresh();
        return {current:()=>current,close};
    }
    return {createIndex, technicalDifferences, primaryDifferences, mount};
});
