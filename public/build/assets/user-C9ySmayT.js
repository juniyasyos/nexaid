import{r as s}from"./app-mb6EOihl.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=(...t)=>t.filter((e,o,r)=>!!e&&e.trim()!==""&&r.indexOf(e)===o).join(" ").trim();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const y=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const A=t=>t.replace(/^([A-Z])|[\s-_]+(\w)/g,(e,o,r)=>r?r.toUpperCase():o.toLowerCase());/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const d=t=>{const e=A(t);return e.charAt(0).toUpperCase()+e.slice(1)};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var i={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const b=t=>{for(const e in t)if(e.startsWith("aria-")||e==="role"||e==="title")return!0;return!1},L=s.createContext({}),W=()=>s.useContext(L),v=s.forwardRef(({color:t,size:e,strokeWidth:o,absoluteStrokeWidth:r,className:c="",children:a,iconNode:C,...l},p)=>{const{size:n=24,strokeWidth:u=2,absoluteStrokeWidth:m=!1,color:x="currentColor",className:f=""}=W()??{},k=r??m?Number(o??u)*24/Number(e??n):o??u;return s.createElement("svg",{ref:p,...i,width:e??n??i.width,height:e??n??i.height,stroke:t??x,strokeWidth:k,className:h("lucide",f,c),...!a&&!b(l)&&{"aria-hidden":"true"},...l},[...C.map(([w,g])=>s.createElement(w,g)),...Array.isArray(a)?a:[a]])});/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=(t,e)=>{const o=s.forwardRef(({className:r,...c},a)=>s.createElement(v,{ref:a,iconNode:e,className:h(`lucide-${y(d(t))}`,`lucide-${t}`,r),...c}));return o.displayName=d(t),o};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const E=[["path",{d:"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2",key:"975kel"}],["circle",{cx:"12",cy:"7",r:"4",key:"17ys0d"}]],$=S("user",E);export{$ as U,S as c};
