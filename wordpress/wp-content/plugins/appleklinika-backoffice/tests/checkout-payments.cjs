const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let callbacks; const context={window:{wc:{wcBlocksRegistry:{registerPaymentMethodExtensionCallbacks:(name,value)=>{callbacks=value;}}}}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/checkout-payments.js','utf8'),context);
let n=0;
for(const methods of [{},{0:'local_pickup:6'},{0:'gls_shipping_method:1'},{0:'ak_mpl_home:4'},{0:'vp_pont:5'},{0:'local_pickup:6',1:'gls_shipping_method:1'},{0:'local_pickup_fake:1'}]){
 for(const id of ['cod','bacs']){assert.equal(callbacks[id]({selectedShippingMethods:methods}),false);n++;}
}
assert.equal(callbacks.barion,undefined);n++; // Never make Barion available against a native/production veto.
console.log(`Checkout Blocks payment eligibility: ${n} assertions passed.`);
