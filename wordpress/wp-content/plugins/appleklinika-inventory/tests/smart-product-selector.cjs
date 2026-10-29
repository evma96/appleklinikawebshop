const assert=require('node:assert/strict');
const {createIndex}=require('../assets/smart-product-selector.js');
let count=0;const check=(condition,message)=>{assert(condition,message);count++;};
const device=(id,changes={})=>({id,modelKey:'ipad_10',available:true,salePrice:100000,values:{storage:'64_gb',color:'blue',condition:'a'},technical:{connectivity:{value:'wifi'}},...changes});
const current=device(1);
for(const group of ['color','storage','condition']){
 const requested={color:'yellow',storage:'256_gb',condition:'a_plus'}[group];
 const exact=device(2,{values:{...current.values,[group]:requested}});
 let index=createIndex([current,exact]);let result=index.classify(current,group,requested);
 check(result.state==='exact'&&result.products.length===1&&result.products[0].id===2,'Single exact physical device is direct: '+group);
 for(const other of ['color','storage','condition'].filter(k=>k!==group)){
  const alternative=device(3,{values:{...exact.values,[other]:'different'}});
  result=createIndex([current,alternative]).classify(current,group,requested);
  check(result.state==='alternative'&&result.products.length===1,'Other '+other+' requires confirmation, including one candidate');
 }
 const cellular=device(4,{...exact, id:4, technical:{connectivity:{value:'wifi_cellular'}}});
 result=createIndex([current,cellular]).classify(current,group,requested);
 check(result.state==='alternative','Technical differences never silently switch');
 result=createIndex([current,cellular,exact]).classify(current,group,requested);
 check(result.state==='exact'&&result.products.map(p=>p.id).join()==='2','Exact candidates take precedence over other combinations');
 const duplicate=device(5,{...exact,id:5,salePrice:95000});
 result=createIndex([current,duplicate,exact]).classify(current,group,requested);
 check(result.products.length===2&&result.products.map(p=>p.id).join()==='2,5','Every matching physical device remains selectable');
 result=createIndex([current,{...exact,available:false},{...exact,id:6,modelKey:'ipad_9'}]).classify(current,group,requested);
 check(result.state==='unavailable'&&result.products.length===0,'No out-of-stock or cross-generation leak');
 check(JSON.stringify(current)===JSON.stringify(device(1)),'Projection does not mutate current device');
}
for(const field of ['ram','chip','size','connectivity','keyboard']){
 const a=device(1,{technical:{[field]:{value:'first'}}});
 const b=device(2,{values:{...a.values,condition:'b'},technical:{[field]:{value:'second'}}});
 check(createIndex([a,b]).classify(a,'condition','b').state==='alternative',field+' change is explicit');
 const missing={...b,technical:{}};
 check(createIndex([a,missing]).classify(a,'condition','b').state==='alternative','Missing '+field+' never implies equivalence');
}
const options=[device(2,{values:{...current.values,storage:'256_gb',color:'yellow'}}),device(3,{values:{...current.values,storage:'256_gb',condition:'b'}}),device(4,{values:{...current.values,storage:'256_gb'},technical:{connectivity:{value:'wifi_cellular'}}})];
check(createIndex([current,...options]).classify(current,'storage','256_gb').products.map(p=>p.id).join()==='3,2,4','Rank preserves technical config before color, then condition');
check(createIndex([current]).classify(current,'storage','8_tb').state==='unavailable','No invented storage');
check(createIndex([current]).classify({...current,modelKey:''},'color','blue').state==='unavailable','Unknown model fails closed');
check(createIndex([current]).classify(current,'chip','m3').state==='unavailable','Only approved selector groups accepted');
check(createIndex([device(1,{selectorEligible:false})]).classify(current,'color','blue').state==='unavailable','Current unavailable physical device is retained as context, never a candidate');
// All real LOCAL model projections: independent safety properties, not a copied matcher.
if(process.argv[2]){
 const fs=require('node:fs');const pools=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
 const all=Object.values(pools).flat();const real=createIndex(all.filter((p,i)=>all.findIndex(q=>q.id===p.id)===i));
 const actual=(id,group,value)=>real.classify(real.byId.get(id),group,value);
 check(actual(2513,'color','silver').products[0].id===306&&actual(2513,'color','silver').state==='exact','A: real exact device');
 check(actual(382,'storage','256_gb').state==='alternative'&&actual(382,'storage','256_gb').products.some(p=>p.id===458),'B: real other color');
 check(actual(328,'color','sierra_blue').state==='alternative'&&actual(328,'color','sierra_blue').products.some(p=>p.id===345),'C: real other condition');
 check(actual(382,'color','pink').state==='alternative'&&actual(382,'color','pink').products.some(p=>p.id===458),'D: real other storage');
 check(actual(453,'storage','256_gb').state==='alternative'&&actual(453,'storage','256_gb').products.map(p=>p.id).join()==='454','E: iPad needs explicit choice even one alternative');
 check(actual(2513,'color','sierra_blue').products.map(p=>p.id).join()==='338,2513','F: real separate physical devices');
 check(actual(453,'color','pink').state==='unavailable','G: valid unavailable option');
 check(real.byId.get(453).technical.connectivity.value==='wifi'&&real.byId.get(454).technical.connectivity.value==='wifi_cellular','H: actual connectivity difference');
 check(actual(453,'storage','256_gb').products.every(p=>p.modelKey==='ipad_10'),'I: other iPad models excluded');
 check(real.byId.get(454).purchaseHtml.includes('value="454"')&&real.byId.get(454).salePrice===249990,'J: real cart identity and price');
 for(const products of Object.values(pools)){
  const index=createIndex(products);
  for(const current of products){for(const group of ['color','storage','condition']){
   for(const value of new Set(products.map(p=>p.values[group]))){
    const r=index.classify(current,group,value);
    check(r.products.every(p=>p.available&&p.modelKey===current.modelKey&&p.values[group]===value),'Real pool never crosses model, stock or requested value');
    if(r.state==='exact')check(r.products.every(p=>Object.keys(current.technical).every(k=>p.technical[k]?.value===current.technical[k].value)&&['color','storage','condition'].every(k=>k===group||p.values[k]===current.values[k])),'Real exact alternatives preserve other configuration');
    check(new Set(r.products.map(p=>p.id)).size===r.products.length,'No duplicate physical identity');
   }
  }}
 }
}
console.log('Smart selector matching passed: '+count+' assertions.');
