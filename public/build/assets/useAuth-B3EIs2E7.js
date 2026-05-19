import{r as m,R as I,b as F,u as M}from"./app-JAURnm-s.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=(...e)=>e.filter((t,r,s)=>!!t&&t.trim()!==""&&s.indexOf(t)===r).join(" ").trim();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=e=>e.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const O=e=>e.replace(/^([A-Z])|[\s-_]+(\w)/g,(t,r,s)=>s?s.toUpperCase():r.toLowerCase());/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const E=e=>{const t=O(e);return t.charAt(0).toUpperCase()+t.slice(1)};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var L={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const W=e=>{for(const t in e)if(t.startsWith("aria-")||t==="role"||t==="title")return!0;return!1},$=m.createContext({}),T=()=>m.useContext($),B=m.forwardRef(({color:e,size:t,strokeWidth:r,absoluteStrokeWidth:s,className:a="",children:o,iconNode:h,...c},u)=>{const{size:i=24,strokeWidth:l=2,absoluteStrokeWidth:g=!1,color:S="currentColor",className:p=""}=T()??{},k=s??g?Number(r??l)*24/Number(t??i):r??l;return m.createElement("svg",{ref:u,...L,width:t??i??L.width,height:t??i??L.height,stroke:e??S,strokeWidth:k,className:j("lucide",p,a),...!o&&!W(c)&&{"aria-hidden":"true"},...c},[...h.map(([b,n])=>m.createElement(b,n)),...Array.isArray(o)?o:[o]])});/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const P=(e,t)=>{const r=m.forwardRef(({className:s,...a},o)=>m.createElement(B,{ref:o,iconNode:t,className:j(`lucide-${N(E(e))}`,`lucide-${e}`,s),...a}));return r.displayName=E(e),r};/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const J=[["path",{d:"M12 7v4",key:"xawao1"}],["path",{d:"M14 21v-3a2 2 0 0 0-4 0v3",key:"1rgiei"}],["path",{d:"M14 9h-4",key:"1w2s2s"}],["path",{d:"M18 11h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2",key:"1tthqt"}],["path",{d:"M18 21V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16",key:"dw4p4i"}]],te=P("hospital",J);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const q=[["path",{d:"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2",key:"975kel"}],["circle",{cx:"12",cy:"7",r:"4",key:"17ys0d"}]],re=P("user",q),H=e=>{let t;const r=new Set,s=(i,l)=>{const g=typeof i=="function"?i(t):i;if(!Object.is(g,t)){const S=t;t=l??(typeof g!="object"||g===null)?g:Object.assign({},t,g),r.forEach(p=>p(t,S))}},a=()=>t,c={setState:s,getState:a,getInitialState:()=>u,subscribe:i=>(r.add(i),()=>r.delete(i))},u=t=e(s,a,c);return c},Z=(e=>e?H(e):H),z=e=>e;function D(e,t=z){const r=I.useSyncExternalStore(e.subscribe,I.useCallback(()=>t(e.getState()),[e,t]),I.useCallback(()=>t(e.getInitialState()),[e,t]));return I.useDebugValue(r),r}const R=e=>{const t=Z(e),r=s=>D(t,s);return Object.assign(r,t),r},K=(e=>e?R(e):R);function G(e,t){let r;try{r=e()}catch{return}return{getItem:a=>{var o;const h=u=>u===null?null:JSON.parse(u,void 0),c=(o=r.getItem(a))!=null?o:null;return c instanceof Promise?c.then(h):h(c)},setItem:(a,o)=>r.setItem(a,JSON.stringify(o,void 0)),removeItem:a=>r.removeItem(a)}}const x=e=>t=>{try{const r=e(t);return r instanceof Promise?r:{then(s){return x(s)(r)},catch(s){return this}}}catch(r){return{then(s){return this},catch(s){return x(s)(r)}}}},Q=(e,t)=>(r,s,a)=>{let o={storage:G(()=>window.localStorage),partialize:n=>n,version:0,merge:(n,w)=>({...w,...n}),...t},h=!1,c=0;const u=new Set,i=new Set;let l=o.storage;if(!l)return e((...n)=>{console.warn(`[zustand persist middleware] Unable to update item '${o.name}', the given storage is currently unavailable.`),r(...n)},s,a);const g=()=>{const n=o.partialize({...s()});return l.setItem(o.name,{state:n,version:o.version})},S=a.setState;a.setState=(n,w)=>(S(n,w),g());const p=e((...n)=>(r(...n),g()),s,a);a.getInitialState=()=>p;let k;const b=()=>{var n,w;if(!l)return;const A=++c;h=!1,u.forEach(d=>{var f;return d((f=s())!=null?f:p)});const C=((w=o.onRehydrateStorage)==null?void 0:w.call(o,(n=s())!=null?n:p))||void 0;return x(l.getItem.bind(l))(o.name).then(d=>{if(d)if(typeof d.version=="number"&&d.version!==o.version){if(o.migrate){const f=o.migrate(d.state,d.version);return f instanceof Promise?f.then(_=>[!0,_]):[!0,f]}console.error("State loaded from storage couldn't be migrated since no migrate function was provided")}else return[!1,d.state];return[!1,void 0]}).then(d=>{var f;if(A!==c)return;const[_,U]=d;if(k=o.merge(U,(f=s())!=null?f:p),r(k,!0),_)return g()}).then(()=>{A===c&&(C?.(s(),void 0),k=s(),h=!0,i.forEach(d=>d(k)))}).catch(d=>{A===c&&C?.(void 0,d)})};return a.persist={setOptions:n=>{o={...o,...n},n.storage&&(l=n.storage)},clearStorage:()=>{l?.removeItem(o.name)},getOptions:()=>o,rehydrate:()=>b(),hasHydrated:()=>h,onHydrate:n=>(u.add(n),()=>{u.delete(n)}),onFinishHydration:n=>(i.add(n),()=>{i.delete(n)})},o.skipHydration||b(),k||p},X=Q,Y=window.location.origin,y=F.create({baseURL:Y,headers:{"Content-Type":"application/json"},withCredentials:!0});y.interceptors.request.use(e=>{const t=localStorage.getItem("access_token");return t&&(e.headers.Authorization=`Bearer ${t}`),e},e=>Promise.reject(e));y.interceptors.response.use(e=>e,e=>(e.response?.status===401&&(localStorage.removeItem("access_token"),localStorage.removeItem("user"),window.location.href="/login"),Promise.reject(e)));const v={async login(e){return(await y.post("/api/auth/login",e)).data},async register(e){return(await y.post("/api/auth/register",e)).data},async getCurrentUser(){return(await y.get("/api/users/me")).data.user},async logout(){if(localStorage.getItem("access_token"))try{await y.post("/api/auth/logout")}catch(t){if(t.response?.status===401)return;throw t}},async refreshToken(){return(await y.post("/api/auth/refresh")).data},async createSessionFromToken(e){await y.post("/api/auth/session-from-token",{access_token:e})}},V=K()(X(e=>({user:null,isAuthenticated:!1,isLoading:!1,error:null,login:async t=>{e({isLoading:!0,error:null});try{const r=await v.login(t);e({user:r.user,isAuthenticated:!0,isLoading:!1}),localStorage.setItem("access_token",r.access_token);try{await v.createSessionFromToken(r.access_token)}catch(s){console.warn("Failed to create backend session:",s)}}catch(r){throw e({error:r.response?.data?.message||"Login failed",isLoading:!1}),r}},register:async t=>{e({isLoading:!0,error:null});try{const r=await v.register(t);e({user:r.user,isAuthenticated:!0,isLoading:!1}),localStorage.setItem("access_token",r.access_token);try{await v.createSessionFromToken(r.access_token)}catch(s){console.warn("Failed to create backend session:",s)}}catch(r){throw e({error:r.response?.data?.message||"Registration failed",isLoading:!1}),r}},logout:async()=>{e({user:null,isAuthenticated:!1}),localStorage.removeItem("access_token")},checkAuth:async()=>{if(!localStorage.getItem("access_token")){e({isAuthenticated:!1,user:null});return}e({isLoading:!0});try{const r=await v.getCurrentUser();e({user:r,isAuthenticated:!0,isLoading:!1})}catch{e({user:null,isAuthenticated:!1,isLoading:!1}),localStorage.removeItem("access_token")}},clearError:()=>e({error:null})}),{name:"auth-storage",partialize:e=>({user:e.user,isAuthenticated:e.isAuthenticated})})),se=()=>{const e=M(),t=V(),s=(e.props.auth??{})?.user||t.user,a=m.useMemo(()=>!!s,[s]),o=m.useCallback(async(u,i)=>{try{return await t.login({email:u,password:i})}catch(l){throw l}},[t]),h=m.useCallback(async()=>{try{await t.logout()}catch(u){console.error("Logout error:",u)}finally{window.location.href="/logout"}},[t]),c=m.useCallback(async u=>{try{return await t.register(u)}catch(i){throw i}},[t]);return{user:s,isAuthenticated:a,isLoading:t.isLoading,error:t.error,login:o,logout:h,register:c,checkAuth:t.checkAuth,clearError:t.clearError}};export{te as H,re as U,P as c,se as u};
