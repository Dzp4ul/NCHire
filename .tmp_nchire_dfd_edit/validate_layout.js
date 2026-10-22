const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const out = path.join(root, 'NCHIRE_DFD_Updated');
const expected = {
  D1:'applicants', D2:'admin_users', D3:'admin_user', D4:'job', D5:'job_applicants',
  D6:'application_bans', D7:'workflow_history', D8:'user_draft_documents',
  D9:'user_education', D10:'user_experience', D11:'user_skills', D12:'notifications',
  D13:'admin_notifications', D14:'admin_activity', D15:'rooms'
};
const failures=[];
const results=[];
const attr = (s,n) => (s.match(new RegExp('(?:^|\\s)'+n+'="([^"]*)"'))||[])[1];
const decode = s => String(s||'').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').replace(/&amp;/g,'&').replace(/&#39;/g,"'");
const styleMap = s => Object.fromEntries(String(s||'').split(';').filter(Boolean).map(x=>{const i=x.indexOf('=');return i<0?[x,'1']:[x.slice(0,i),x.slice(i+1)];}));

function pages(xml){
  const all=[]; let m;
  const re=/<diagram\b([^>]*)>([\s\S]*?)<\/diagram>/g;
  while((m=re.exec(xml))) all.push({attrs:m[1],body:m[2],name:decode(attr(m[1],'name'))});
  return all;
}
function cells(page){
  const all=[]; let m;
  const re=/<mxCell\b([^>]*)>([\s\S]*?)<\/mxCell>/g;
  while((m=re.exec(page.body))){
    const a=m[1], body=m[2], ga=(body.match(/<mxGeometry\b([^>]*)/)||[])[1]||'';
    const points=[]; let pm; const pr=/<mxPoint\b[^>]*x="([^"]+)"[^>]*y="([^"]+)"[^>]*\/>/g;
    while((pm=pr.exec(body))) points.push({x:+pm[1],y:+pm[2]});
    all.push({id:attr(a,'id'),value:decode(attr(a,'value')),style:attr(a,'style')||'',vertex:attr(a,'vertex')==='1',edge:attr(a,'edge')==='1',source:attr(a,'source'),target:attr(a,'target'),x:+(attr(ga,'x')||0),y:+(attr(ga,'y')||0),w:+(attr(ga,'width')||0),h:+(attr(ga,'height')||0),points});
  }
  return all;
}
function endpoint(cell,edgeStyle,prefix){
  const st=styleMap(edgeStyle), x=+(st[prefix+'X']??.5), y=+(st[prefix+'Y']??.5);
  return {x:cell.x+cell.w*x,y:cell.y+cell.h*y};
}
function edgePath(edge,byId){
  const s=byId.get(edge.source),t=byId.get(edge.target); if(!s||!t) return [];
  const a=endpoint(s,edge.style,'exit'),b=endpoint(t,edge.style,'entry');
  if(edge.points.length) return [a,...edge.points,b];
  if(Math.abs(b.x-a.x)>=Math.abs(b.y-a.y)){const x=(a.x+b.x)/2;return[a,{x,y:a.y},{x,y:b.y},b];}
  const y=(a.y+b.y)/2;return[a,{x:a.x,y},{x:b.x,y},b];
}
function segments(points){return points.slice(0,-1).map((a,i)=>({a,b:points[i+1]})).filter(s=>s.a.x!==s.b.x||s.a.y!==s.b.y);}
function solid(c){const s=styleMap(c.style);return c.vertex&&s.strokeColor!=='none'&&s.shape!=='line';}
function hitsRect(seg,r){
  const e=.01, minx=Math.min(seg.a.x,seg.b.x),maxx=Math.max(seg.a.x,seg.b.x),miny=Math.min(seg.a.y,seg.b.y),maxy=Math.max(seg.a.y,seg.b.y);
  if(Math.abs(seg.a.x-seg.b.x)<e) return seg.a.x>r.x+e&&seg.a.x<r.x+r.w-e&&maxy>r.y+e&&miny<r.y+r.h-e;
  if(Math.abs(seg.a.y-seg.b.y)<e) return seg.a.y>r.y+e&&seg.a.y<r.y+r.h-e&&maxx>r.x+e&&minx<r.x+r.w-e;
  return false;
}
function strictCross(a,b){
  const e=.01, av=Math.abs(a.a.x-a.b.x)<e, bv=Math.abs(b.a.x-b.b.x)<e;
  if(av===bv) return false;
  const v=av?a:b,h=av?b:a,x=v.a.x,y=h.a.y;
  const vmin=Math.min(v.a.y,v.b.y),vmax=Math.max(v.a.y,v.b.y),hmin=Math.min(h.a.x,h.b.x),hmax=Math.max(h.a.x,h.b.x);
  return x>hmin+e&&x<hmax-e&&y>vmin+e&&y<vmax-e;
}

const files=['NCHIRE DFD LVL 0(2).drawio','DFD-LVL-1-AND-2 (1)(2).drawio'];
for(const file of files){
  const xml=fs.readFileSync(path.join(out,file),'utf8'), ps=pages(xml);
  const wanted=file.includes('AND-2')?2:1;
  if(ps.length!==wanted) failures.push(`${file}: expected ${wanted} page(s), found ${ps.length}`);
  const banned=['resume analyzer','resume parsing','resume scoring','candidate ranking','compatibility score','recruitment insights'];
  for(const term of banned) if(xml.toLowerCase().includes(term)) failures.push(`${file}: obsolete term "${term}"`);
  ps.forEach((page,pi)=>{
    const cs=cells(page),byId=new Map(cs.map(c=>[c.id,c])),verts=cs.filter(c=>c.vertex),edges=cs.filter(c=>c.edge);
    edges.forEach(e=>{if(!byId.has(e.source)||!byId.has(e.target)) failures.push(`${file}/${page.name}: broken edge ${e.id}`);});
    const stores=verts.filter(c=>styleMap(c.style).shape==='partialRectangle');
    if(page.name.includes('Level 0')&&stores.length) failures.push(`${file}/${page.name}: Level 0 contains stores`);
    for(const st of stores){
      const m=st.value.replace(/<[^>]+>/g,' ').replace(/&nbsp;/g,' ').match(/\b(D\d+)\s+([a-z_]+)/i);
      if(!m||expected[m[1]]!==m[2]) failures.push(`${file}/${page.name}: invalid store ${st.id} = ${st.value}`);
    }
    for(const e of edges){
      const path=edgePath(e,byId),segs=segments(path);
      for(const v of verts){if(v.id===e.source||v.id===e.target||!solid(v))continue; if(segs.some(s=>hitsRect(s,v))) failures.push(`${file}/${page.name}: edge ${e.id} crosses shape ${v.id}`);}
    }
    for(let i=0;i<edges.length;i++) for(let j=i+1;j<edges.length;j++){
      const a=edges[i],b=edges[j]; if(a.source===b.source||a.source===b.target||a.target===b.source||a.target===b.target) continue;
      if(segments(edgePath(a,byId)).some(x=>segments(edgePath(b,byId)).some(y=>strictCross(x,y)))) failures.push(`${file}/${page.name}: edge crossing ${a.id} with ${b.id}`);
    }
    const procs=verts.filter(c=>styleMap(c.style).ellipse==='1');
    results.push({file,page:page.name,vertices:verts.length,edges:edges.length,stores:stores.length,processes:procs.length});
  });
}

console.log(JSON.stringify({ok:failures.length===0,results,failures},null,2));
process.exitCode=failures.length?1:0;
