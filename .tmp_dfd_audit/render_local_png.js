const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const workspace = path.resolve(__dirname, '..');
const sourceDir = path.join(__dirname, 'clean_render_sources');
const pngDir = path.join(workspace, 'NCHIRE_Revised_DFD_Output_CLEAN', 'PNG');
fs.mkdirSync(pngDir, { recursive: true });

const outputs = {
  clean_l0: 'NCHIRE_DFD_LEVEL_0_REVISED.png',
  clean_l1: 'NCHIRE_DFD_LEVEL_1_REVISED.png',
  clean_l2: 'NCHIRE_DFD_LEVEL_2_REVISED.png'
};

const esc = value => String(value)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

function htmlDocument(xml) {
  return `<!doctype html><html><head><meta charset="utf-8"><style>
    *{box-sizing:border-box}html,body{margin:0;background:#fff;overflow:hidden}
    #canvas{position:relative;background:#fff;font-family:Arial,sans-serif;color:#000}
    svg{position:absolute;inset:0}.label{position:absolute;display:flex;padding:0 5px;line-height:1.2;overflow:hidden}
    .edge-label{position:absolute;display:flex;align-items:center;justify-content:center;text-align:center;padding:2px 5px;background:transparent;font:11px/1.18 Arial,sans-serif;color:#000;overflow:hidden}
  </style></head><body><div id="canvas"></div><script>
  const xmlText = ${JSON.stringify(xml)};
  const doc = new DOMParser().parseFromString(xmlText, 'application/xml');
  const model = doc.querySelector('mxGraphModel');
  const width = Number(model.getAttribute('pageWidth')) || 1600;
  const height = Number(model.getAttribute('pageHeight')) || 1200;
  const canvas = document.getElementById('canvas');
  canvas.style.width = width + 'px'; canvas.style.height = height + 'px';
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('width', width); svg.setAttribute('height', height); svg.setAttribute('viewBox', '0 0 '+width+' '+height);
  const defs = document.createElementNS(ns, 'defs');
  defs.innerHTML = '<marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#000"/></marker>';
  svg.appendChild(defs); canvas.appendChild(svg);
  const cells = [...doc.querySelectorAll('mxCell')];
  const byId = new Map(cells.map(c => [c.getAttribute('id'), c]));
  const styles = s => Object.fromEntries((s || '').split(';').filter(Boolean).map(part => { const i=part.indexOf('='); return i<0 ? [part,'1'] : [part.slice(0,i),part.slice(i+1)]; }));
  const box = cell => { const g=cell.querySelector(':scope > mxGeometry'); return {x:Number(g?.getAttribute('x'))||0,y:Number(g?.getAttribute('y'))||0,w:Number(g?.getAttribute('width'))||0,h:Number(g?.getAttribute('height'))||0}; };
  const point = (b, x, y) => ({x:b.x+b.w*Number(x),y:b.y+b.h*Number(y)});
  const node = (name, attrs={}) => { const n=document.createElementNS(ns,name); for(const [k,v] of Object.entries(attrs)) n.setAttribute(k,v); return n; };
  const addLabel = (value,b,s,edge=false) => {
    if (!value) return;
    const d=document.createElement('div'); d.className=edge?'edge-label':'label';
    const inner=document.createElement('div'); inner.innerHTML=value; inner.style.maxWidth='100%'; inner.style.maxHeight='100%';
    if(edge){inner.style.background='#fff';inner.style.padding='2px 4px'}
    d.appendChild(inner);
    d.style.left=b.x+'px'; d.style.top=b.y+'px'; d.style.width=b.w+'px'; d.style.height=b.h+'px';
    if (!edge) {
      d.style.fontSize=(Number(s.fontSize)||12)+'px'; d.style.fontWeight=s.fontStyle==='1'?'700':'400';
      d.style.textAlign=s.align||'center'; inner.style.width='100%'; d.style.justifyContent=s.align==='left'?'flex-start':s.align==='right'?'flex-end':'center';
      d.style.alignItems=s.verticalAlign==='top'?'flex-start':s.verticalAlign==='bottom'?'flex-end':'center';
      const pad=Number(s.spacing)||0; d.style.padding=(pad||0)+'px '+(Number(s.spacingRight)||pad||5)+'px '+(pad||0)+'px '+(Number(s.spacingLeft)||pad||5)+'px';
    }
    canvas.appendChild(d);
  };
  const vertices=cells.filter(c=>c.getAttribute('vertex')==='1');
  const boxes=new Map(vertices.map(c=>[c.getAttribute('id'),box(c)]));
  const edges=cells.filter(c=>c.getAttribute('edge')==='1');
  for (const e of edges) {
    const sb=boxes.get(e.getAttribute('source')), tb=boxes.get(e.getAttribute('target')); if(!sb||!tb) continue;
    const s=styles(e.getAttribute('style'));
    const start=point(sb,s.exitX??0.5,s.exitY??0.5), end=point(tb,s.entryX??0.5,s.entryY??0.5);
    const listed=[...e.querySelectorAll(':scope > mxGeometry > Array[as="points"] > mxPoint')].map(p=>({x:Number(p.getAttribute('x')),y:Number(p.getAttribute('y'))}));
    let pts=[start,...listed,end];
    if(!listed.length){
      if(Math.abs(end.x-start.x)>=Math.abs(end.y-start.y)){ const mx=(start.x+end.x)/2; pts=[start,{x:mx,y:start.y},{x:mx,y:end.y},end]; }
      else { const my=(start.y+end.y)/2; pts=[start,{x:start.x,y:my},{x:end.x,y:my},end]; }
    }
    const poly=node('polyline',{points:pts.map(p=>p.x+','+p.y).join(' '),fill:'none',stroke:'#000','stroke-width':s.strokeWidth||1.5,'stroke-linejoin':'miter','stroke-linecap':'square'});
    if(s.dashed==='1') poly.setAttribute('stroke-dasharray','7 5');
    if(s.endArrow!=='none') poly.setAttribute('marker-end','url(#arrow)');
    svg.appendChild(poly);
    const segs=pts.slice(0,-1).map((p,i)=>({a:p,b:pts[i+1],len:Math.hypot(pts[i+1].x-p.x,pts[i+1].y-p.y)}));
    const longest=segs.sort((a,b)=>b.len-a.len)[0];
    if(longest && e.getAttribute('value')) { const m={x:(longest.a.x+longest.b.x)/2,y:(longest.a.y+longest.b.y)/2}; addLabel(e.getAttribute('value'),{x:m.x-120,y:m.y-30,w:240,h:60},s,true); }
  }
  for (const c of vertices) {
    const b=boxes.get(c.getAttribute('id')), s=styles(c.getAttribute('style')); let shape;
    const common={fill:s.fillColor==='none'?'none':'#fff',stroke:s.strokeColor==='none'?'none':'#000','stroke-width':s.strokeWidth||1};
    if(s.shape==='line') shape=node('line',{x1:b.x,y1:b.y,x2:b.x+b.w,y2:b.y,...common});
    else if(s.ellipse==='1') shape=node('ellipse',{cx:b.x+b.w/2,cy:b.y+b.h/2,rx:b.w/2,ry:b.h/2,...common});
    else if(s.shape==='partialRectangle') {
      shape=node('path',{d:'M '+(b.x+b.w)+' '+b.y+' H '+b.x+' V '+(b.y+b.h)+' H '+(b.x+b.w),...common});
    } else shape=node('rect',{x:b.x,y:b.y,width:b.w,height:b.h,rx:s.rounded==='1'?Math.min(18,b.h*.18):0,...common});
    if(s.dashed==='1') shape.setAttribute('stroke-dasharray','7 5');
    svg.appendChild(shape);
    addLabel(c.getAttribute('value'),b,s,false);
  }
  document.body.dataset.ready='1';
  </script></body></html>`;
}

(async () => {
  const browser = await chromium.launch({ headless:true, executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    for (const [id,name] of Object.entries(outputs)) {
      const xml=fs.readFileSync(path.join(sourceDir,id+'.drawio'),'utf8');
      const match=xml.match(/pageWidth="(\d+)" pageHeight="(\d+)"/); if(!match) throw new Error('Missing page dimensions: '+id);
      const width=Number(match[1]), height=Number(match[2]);
      const page=await browser.newPage({viewport:{width,height},deviceScaleFactor:1});
      await page.setContent(htmlDocument(xml),{waitUntil:'load'}); await page.waitForFunction(()=>document.body.dataset.ready==='1');
      const out=path.join(pngDir,name); await page.screenshot({path:out,fullPage:true,type:'png'}); await page.close();
      console.log(name+'\t'+fs.statSync(out).size+' bytes\t'+width+'x'+height);
    }
  } finally { await browser.close(); }
})().catch(error=>{console.error(error.stack||error);process.exit(1)});
