const fs = require('fs');
const path = require('path');

const workspace = path.resolve(__dirname, '..');
const outDir = path.join(workspace, 'NCHIRE_Revised_DFD_Output_CLEAN');
const renderDir = path.join(__dirname, 'clean_render_sources');
fs.mkdirSync(outDir, { recursive: true });
fs.mkdirSync(renderDir, { recursive: true });

const E = value => String(value)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const titleStyle = 'text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;fontFamily=Arial;fontSize=24;fontStyle=1;fontColor=#000000;whiteSpace=wrap;';
const subtitleStyle = 'text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;fontFamily=Arial;fontSize=12;fontColor=#000000;whiteSpace=wrap;';
const headingStyle = 'text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontFamily=Arial;fontSize=14;fontStyle=1;fontColor=#000000;whiteSpace=wrap;';
const entityStyle = 'rounded=0;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=13;fontStyle=1;align=center;verticalAlign=middle;spacing=7;';
const processStyle = 'rounded=1;arcSize=18;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=14;align=center;verticalAlign=middle;spacing=7;';
const contextStyle = 'ellipse;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=3;fontFamily=Arial;fontSize=17;fontStyle=1;align=center;verticalAlign=middle;spacing=10;';
const storeStyle = 'shape=partialRectangle;whiteSpace=wrap;html=1;top=1;bottom=1;left=1;right=0;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=11;align=left;verticalAlign=middle;spacingLeft=10;spacingRight=6;';
const refStyle = 'rounded=1;arcSize=12;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=1.5;dashed=1;dashPattern=6 4;fontFamily=Arial;fontSize=11;align=center;verticalAlign=middle;spacing=5;';
const noteStyle = 'rounded=0;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=1;dashed=1;dashPattern=4 4;fontFamily=Arial;fontSize=11;align=left;verticalAlign=top;spacing=7;';
const separatorStyle = 'shape=line;strokeColor=#000000;strokeWidth=1;dashed=1;dashPattern=3 6;';

class Page {
  constructor(id, name, width, height, subtitle) {
    this.id = id; this.name = name; this.width = width; this.height = height; this.cells = [];
    this.text(`${id}_title`, `<b>${name}</b>`, 60, 22, width - 120, 42, titleStyle);
    if (subtitle) this.text(`${id}_subtitle`, subtitle, 80, 68, width - 160, 40, subtitleStyle);
  }
  text(id, value, x, y, w, h, style = subtitleStyle) {
    this.cells.push(`<mxCell id="${E(id)}" value="${E(value)}" style="${E(style)}" vertex="1" parent="1"><mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/></mxCell>`);
    return id;
  }
  entity(id, value, x, y, w = 270, h = 78) { return this.text(id, value, x, y, w, h, entityStyle); }
  process(id, number, value, x, y, w = 340, h = 115) { return this.text(id, `<b>${number}</b><br>${value}`, x, y, w, h, processStyle); }
  context(id, value, x, y, w, h) { return this.text(id, value, x, y, w, h, contextStyle); }
  store(id, number, table, x, y, w = 340, h = 56) { return this.text(id, `<b>${number}</b>&nbsp;&nbsp;${table}`, x, y, w, h, storeStyle); }
  ref(id, value, x, y, w, h) { return this.text(id, value, x, y, w, h, refStyle); }
  note(id, value, x, y, w, h) { return this.text(id, value, x, y, w, h, noteStyle); }
  separator(id, x, y, w) { return this.text(id, '', x, y, w, 1, separatorStyle); }
  edge(id, source, target, label, options = {}) {
    const style = [
      'edgeStyle=orthogonalEdgeStyle','rounded=0','orthogonalLoop=1','jettySize=auto','html=1',
      'strokeColor=#000000','strokeWidth=1.4','endArrow=classic','endFill=1',
      'fontFamily=Arial','fontSize=10','fontColor=#000000','labelBackgroundColor=#ffffff'
    ];
    if (options.both) style.push('startArrow=classic','startFill=1');
    if (options.dashed) style.push('dashed=1','dashPattern=6 4');
    if (options.exitX !== undefined) style.push(`exitX=${options.exitX}`,`exitY=${options.exitY ?? 0.5}`,'exitDx=0','exitDy=0');
    if (options.entryX !== undefined) style.push(`entryX=${options.entryX}`,`entryY=${options.entryY ?? 0.5}`,'entryDx=0','entryDy=0');
    let geometry = '<mxGeometry relative="1" as="geometry"/>';
    if (options.points?.length) geometry = `<mxGeometry relative="1" as="geometry"><Array as="points">${options.points.map(([x,y]) => `<mxPoint x="${x}" y="${y}"/>`).join('')}</Array></mxGeometry>`;
    this.cells.push(`<mxCell id="${E(id)}" value="${E(label)}" style="${E(style.join(';') + ';')}" edge="1" parent="1" source="${E(source)}" target="${E(target)}">${geometry}</mxCell>`);
    return id;
  }
  xml() {
    return `<diagram id="${E(this.id)}" name="${E(this.name)}"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="${this.width}" pageHeight="${this.height}" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>${this.cells.join('')}</root></mxGraphModel></diagram>`;
  }
}

const mxfile = pages => `<?xml version="1.0" encoding="UTF-8"?>\n<mxfile host="Electron" modified="2026-10-22T00:00:00.000Z" agent="Codex NCHIRE Clean DFD Generator" version="24.7.17" type="device" compressed="false">${pages.map(p => p.xml()).join('')}</mxfile>\n`;

function footer(page, value) { page.text(`${page.id}_footer`, value, 80, page.height - 48, page.width - 160, 26, subtitleStyle); }

function level0() {
  const p = new Page('clean_l0', 'NCHIRE Data Flow Diagram — Level 0 (Context Diagram)', 1920, 1200,
    'Current system boundary, arranged to retain the clean four-corner structure of the original context diagram.');
  p.entity('l0_app', 'Applicant / Job Seeker', 110, 135, 300, 95);
  p.entity('l0_staff', 'Recruitment Staff / Administrator<br><span style="font-size:11px">Secretary, Dean, Admin</span>', 1510, 135, 300, 95);
  p.entity('l0_groq', 'Groq Language Model API<br><span style="font-size:11px">Recruitment chatbot only</span>', 110, 900, 300, 95);
  p.entity('l0_email', 'SMTP Email Service<br><span style="font-size:11px">PHPMailer / Gmail SMTP</span>', 1510, 900, 300, 95);
  p.context('l0_system', '<b>0</b><br>NCHIRE<br><span style="font-size:14px">Recruitment and Applicant<br>Management System</span>', 720, 430, 480, 300);

  p.edge('l0_a_in','l0_app','l0_system','Registration, profile, job application and documents',{exitX:1,exitY:.35,entryX:0,entryY:.24,points:[[500,168],[500,502],[700,502]]});
  p.edge('l0_a_out','l0_system','l0_app','Jobs, application status, schedules and alerts',{exitX:0,exitY:.44,entryX:1,entryY:.78,points:[[460,562],[460,209]]});
  p.edge('l0_s_in','l0_staff','l0_system','Login, load data, review decisions and schedules',{exitX:0,exitY:.35,entryX:1,entryY:.24,points:[[1420,168],[1420,502],[1220,502]]});
  p.edge('l0_s_out','l0_system','l0_staff','Queues, documents, statistics and chatbot answers',{exitX:1,exitY:.44,entryX:0,entryY:.78,points:[[1460,562],[1460,209]]});
  p.edge('l0_g_in','l0_system','l0_groq','Authorized question and role-scoped context',{exitX:0,exitY:.74,entryX:1,entryY:.30,points:[[500,652],[500,928]]});
  p.edge('l0_g_out','l0_groq','l0_system','Generated chatbot answer',{exitX:1,exitY:.72,entryX:0,entryY:.88,points:[[460,968],[460,694],[700,694]]});
  p.edge('l0_e_in','l0_system','l0_email','Verification, password, schedule and decision email',{exitX:1,exitY:.74,entryX:0,entryY:.30,points:[[1420,652],[1420,928]]});
  p.edge('l0_e_out','l0_email','l0_system','Delivery result',{exitX:0,exitY:.72,entryX:1,entryY:.88,points:[[1460,968],[1460,694],[1220,694]]});
  footer(p, 'No internal database stores appear in the context diagram.');
  return p;
}

function level1Card(page, cfg, x, y) {
  page.text(`${cfg.id}_heading`, `<b>${cfg.number}</b>&nbsp;&nbsp;${cfg.plain}`, x + 18, y + 12, 310, 28, headingStyle);
  const procX=x+350, procY=y+300, procW=330, procH=120;
  page.process(cfg.id,cfg.number,cfg.label,procX,procY,procW,procH);
  (cfg.actors||[]).forEach((actor,i)=>{
    const aid=`${cfg.id}_actor_${i}`, ay=y+150+i*115;
    page.entity(aid,actor.label,x+20,ay,260,76);
    const targetY=.22+i*(.56/Math.max(1,(cfg.actors.length-1)));
    const lane=x+305+i*12;
    page.edge(`${aid}_flow`,aid,cfg.id,actor.flow,{both:true,exitX:1,exitY:.5,entryX:0,entryY:targetY,points:[[lane,ay+38],[lane,procY+procH*targetY]]});
  });
  const n=cfg.stores.length;
  cfg.stores.forEach((store,i)=>{
    const sid=`${cfg.id}_store_${i}`, sy=y+90+i*78;
    page.store(sid,store.number,store.table,x+820,sy,360,52);
    const anchor=.10+(i+1)*(.80/(n+1));
    const startY=procY+procH*anchor, targetY=sy+26, lane=x+710+i*12;
    const common={exitX:1,exitY:anchor,entryX:0,entryY:.5,points:[[lane,startY],[lane,targetY]]};
    if(store.direction==='in') page.edge(`${sid}_edge`,sid,cfg.id,store.flow,{exitX:0,exitY:.5,entryX:1,entryY:anchor,points:[[lane,targetY],[lane,startY]]});
    else page.edge(`${sid}_edge`,cfg.id,sid,store.flow,{...common,both:store.direction==='both'});
  });
  page.separator(`${cfg.id}_sep`,x+10,y+780,1190);
}

function level1() {
  const p = new Page('clean_l1','NCHIRE Data Flow Diagram — Level 1',5100,2730,
    'One-page view. Repeated entity and store symbols prevent long connectors; identical D-numbers refer to the same ERD table.');
  const cards=[
    {x:650,y:130,c:{id:'p1',number:'1.0',plain:'Manage Accounts & Access',label:'Manage Accounts<br>&amp; Access',actors:[{label:'Applicant / Job Seeker',flow:'Registration, credentials / access result'},{label:'Secretary / Dean / Admin',flow:'Staff credentials / authorized session'},{label:'SMTP Email Service',flow:'Verification or password email / delivery result'}],stores:[{number:'D1',table:'applicants',direction:'both',flow:'Applicant account data'},{number:'D2',table:'admin_users',direction:'both',flow:'Staff account data'},{number:'D14',table:'admin_activity',direction:'out',flow:'Password-reset audit'}]}},
    {x:1925,y:130,c:{id:'p2',number:'2.0',plain:'Maintain Applicant Profiles',label:'Maintain Applicant<br>Profiles',actors:[{label:'Applicant / Job Seeker',flow:'Profile changes / saved profile'}],stores:[{number:'D1',table:'applicants',direction:'both',flow:'Personal profile data'},{number:'D9',table:'user_education',direction:'both',flow:'Education records'},{number:'D10',table:'user_experience',direction:'both',flow:'Experience records'},{number:'D11',table:'user_skills',direction:'both',flow:'Skill records'}]}},
    {x:3200,y:130,c:{id:'p3',number:'3.0',plain:'Manage Teaching Loads & Requirements',label:'Manage Teaching Loads<br>&amp; Requirements',actors:[{label:'Secretary',flow:'Load changes / publication result'},{label:'Dean',flow:'Department load changes / result'},{label:'Applicant / Job Seeker',flow:'Search criteria / available loads'}],stores:[{number:'D4',table:'job',direction:'both',flow:'Teaching-load data'},{number:'D14',table:'admin_activity',direction:'out',flow:'Load-management audit'}]}},
    {x:25,y:990,c:{id:'p4',number:'4.0',plain:'Submit & Maintain Applications',label:'Submit &amp; Maintain<br>Applications',actors:[{label:'Applicant / Job Seeker',flow:'Application, documents / status'}],stores:[{number:'D1',table:'applicants',direction:'both',flow:'Profile and ban state'},{number:'D4',table:'job',direction:'in',flow:'Open load and vacancy'},{number:'D5',table:'job_applicants',direction:'both',flow:'Application and status'},{number:'D8',table:'user_draft_documents',direction:'both',flow:'Draft documents'},{number:'D9',table:'user_education',direction:'in',flow:'Education eligibility'},{number:'D10',table:'user_experience',direction:'in',flow:'Experience eligibility'}]}},
    {x:1300,y:990,c:{id:'p5',number:'5.0',plain:'Review & Route Applications',label:'Review &amp; Route<br>Applications',actors:[{label:'Secretary',flow:'Review decision / review queue'}],stores:[{number:'D1',table:'applicants',direction:'both',flow:'Applicant and ban state'},{number:'D2',table:'admin_users',direction:'in',flow:'Dean routing data'},{number:'D4',table:'job',direction:'in',flow:'Load requirements'},{number:'D5',table:'job_applicants',direction:'both',flow:'Review and route status'},{number:'D6',table:'application_bans',direction:'out',flow:'Rejection ban record'},{number:'D7',table:'workflow_history',direction:'out',flow:'Secretary workflow event'},{number:'D14',table:'admin_activity',direction:'out',flow:'Review audit'}]}},
    {x:2575,y:990,c:{id:'p6',number:'6.0',plain:'Schedule & Evaluate Recruitment',label:'Schedule &amp; Evaluate<br>Recruitment',actors:[{label:'Dean',flow:'Schedules and decisions / queue'},{label:'Applicant / Job Seeker',flow:'Psych receipt / stage status'}],stores:[{number:'D1',table:'applicants',direction:'both',flow:'Applicant and ban state'},{number:'D4',table:'job',direction:'both',flow:'Teaching-load capacity'},{number:'D5',table:'job_applicants',direction:'both',flow:'Schedules and results'},{number:'D6',table:'application_bans',direction:'out',flow:'Rejection ban record'},{number:'D14',table:'admin_activity',direction:'out',flow:'Decision audit'},{number:'D15',table:'rooms',direction:'in',flow:'Interview rooms'}]}},
    {x:3850,y:990,c:{id:'p7',number:'7.0',plain:'Deliver & Track Notifications',label:'Deliver &amp; Track<br>Notifications',actors:[{label:'Applicant / Job Seeker',flow:'Feed request / alerts'},{label:'Secretary / Dean / Admin',flow:'Feed request / staff alerts'},{label:'SMTP Email Service',flow:'Email payload / delivery result'}],stores:[{number:'D2',table:'admin_users',direction:'in',flow:'Active staff recipients'},{number:'D12',table:'notifications',direction:'both',flow:'Applicant notifications'},{number:'D13',table:'admin_notifications',direction:'both',flow:'Staff notifications'}]}},
    {x:1300,y:1850,c:{id:'p8',number:'8.0',plain:'Administer Staff & Monitor Operations',label:'Administer Staff &amp;<br>Monitor Operations',actors:[{label:'Administrator',flow:'Account/report request / results'},{label:'SMTP Email Service',flow:'Temporary password / delivery result'}],stores:[{number:'D2',table:'admin_users',direction:'both',flow:'Staff accounts'},{number:'D4',table:'job',direction:'in',flow:'Teaching-load totals'},{number:'D5',table:'job_applicants',direction:'in',flow:'Recruitment statistics'},{number:'D14',table:'admin_activity',direction:'both',flow:'Activity and audit data'}]}},
    {x:2575,y:1850,c:{id:'p9',number:'9.0',plain:'Provide AI Recruitment Assistant',label:'Provide AI Recruitment<br>Assistant',actors:[{label:'Secretary',flow:'Question / chatbot answer'},{label:'Dean',flow:'Question / scoped answer'},{label:'Groq Language Model API',flow:'Prompt context / generated answer'}],stores:[{number:'D2',table:'admin_users',direction:'in',flow:'Active staff role'},{number:'D1',table:'applicants',direction:'in',flow:'Applicant context'},{number:'D4',table:'job',direction:'in',flow:'Teaching-load context'},{number:'D5',table:'job_applicants',direction:'in',flow:'Application context'},{number:'D9',table:'user_education',direction:'in',flow:'Education context'},{number:'D10',table:'user_experience',direction:'in',flow:'Experience context'},{number:'D11',table:'user_skills',direction:'in',flow:'Skill context'},{number:'D14',table:'admin_activity',direction:'out',flow:'Chatbot usage audit'}]}}
  ];
  cards.forEach(({c,x,y})=>level1Card(p,c,x,y));
  const procTop=1290, laneY=955;
  p.edge('seq45','p4','p5','Submitted application',{exitX:.58,exitY:0,entryX:.35,entryY:0,points:[[566,950],[1766,950]]});
  p.edge('seq56','p5','p6','Transferred application',{exitX:.65,exitY:0,entryX:.35,entryY:0,points:[[1865,965],[3046,965]]});
  p.edge('seq67','p6','p7','Workflow notification event',{exitX:.65,exitY:0,entryX:.35,entryY:0,points:[[3145,980],[4326,980]]});
  footer(p,'Main recruitment sequence: 4.0 submission → 5.0 Secretary review → 6.0 Dean workflow → 7.0 notifications. D3 admin_user is reserved but unused by current routes.');
  return p;
}

function stageStores(page, proc, stageX, procY, stores, side) {
  const widths={1:220,2:220,3:200,4:160};
  const offsets={1:[60],2:[-10,230],3:[-90,150,390],4:[-165,5,175,345]};
  const anchors={1:[.50],2:[.24,.76],3:[.10,.50,.90],4:[.08,.33,.67,.92]};
  const w=widths[stores.length], sy=side==='below'?procY+280:procY-300;
  stores.forEach((store,i)=>{
    const sid=`${proc}_store_${i}`, sx=stageX+offsets[stores.length][i];
    page.store(sid,store.number,store.table,sx,sy,w,54);
    const anchor=anchors[stores.length][i];
    let options;
    if(side==='below') {
      if(store.direction==='in') options={exitX:.5,exitY:0,entryX:anchor,entryY:1};
      else options={exitX:anchor,exitY:1,entryX:.5,entryY:0,both:store.direction==='both'};
    } else {
      if(store.direction==='in') options={exitX:.5,exitY:1,entryX:anchor,entryY:0};
      else options={exitX:anchor,exitY:0,entryX:.5,entryY:1,both:store.direction==='both'};
    }
    if(store.direction==='in') page.edge(`${sid}_edge`,sid,proc,store.flow,options);
    else page.edge(`${sid}_edge`,proc,sid,store.flow,options);
  });
}

function stageActor(page, proc, stageX, procY, label, flow, side='above', direction='both') {
  const id=`${proc}_actor`, actorY=side==='above'?procY-250:procY+300;
  page.entity(id,label,stageX+40,actorY,260,78);
  if(side==='above') {
    if(direction==='in') page.edge(`${id}_edge`,id,proc,flow,{exitX:.5,exitY:1,entryX:.5,entryY:0});
    else if(direction==='out') page.edge(`${id}_edge`,proc,id,flow,{exitX:.5,exitY:0,entryX:.5,entryY:1});
    else page.edge(`${id}_edge`,id,proc,flow,{both:true,exitX:.5,exitY:1,entryX:.5,entryY:0});
  } else {
    if(direction==='in') page.edge(`${id}_edge`,id,proc,flow,{exitX:.5,exitY:0,entryX:.5,entryY:1});
    else if(direction==='out') page.edge(`${id}_edge`,proc,id,flow,{exitX:.5,exitY:1,entryX:.5,entryY:0});
    else page.edge(`${id}_edge`,id,proc,flow,{both:true,exitX:.5,exitY:0,entryX:.5,entryY:1});
  }
}

function level2() {
  const p = new Page('clean_l2','NCHIRE Data Flow Diagram — Level 2 (Core Recruitment Workflow)',4000,2500,
    'One-page decomposition of Processes 4.0, 5.0, 6.0 and 7.0. The sequence folds into two rows; repeated symbols keep every data flow local.');
  const topXs=[180,880,1610,2340,3070], bottomXs=[3300,2570,1840,1110,350];
  const topY=430, bottomY=1730;
  const xs=[...topXs,...bottomXs];
  const specs=[
    ['l2_p41','4.1','Validate Job, Profile,<br>Eligibility &amp; Ban'],
    ['l2_p42','4.2','Submit, Resubmit or<br>Cancel Application'],
    ['l2_p51','5.1','Inspect Application<br>&amp; Documents'],
    ['l2_p52','5.2','Record Secretary<br>Decision'],
    ['l2_p61','6.1','Schedule or Reschedule<br>Interview'],
    ['l2_p62','6.2','Record Interview &amp;<br>Schedule Demonstration'],
    ['l2_p63','6.3','Record Demonstration &amp;<br>Schedule Psych Exam'],
    ['l2_p64','6.4','Receive Psychological-<br>Exam Receipt'],
    ['l2_p65','6.5','Record Final or<br>Rejection Decision'],
    ['l2_p71','7.1','Store &amp; Deliver<br>Notifications']
  ];
  specs.forEach((s,i)=>p.process(s[0],s[1],s[2],xs[i],i<5?topY:bottomY,340,110));
  const flows=['Eligible teaching load','Application for review','Selected application','Transferred application','Interview schedule','Interview result','Psych schedule','Receipt received','Decision event'];
  for(let i=0;i<4;i++) p.edge(`l2_seq_${i}`,specs[i][0],specs[i+1][0],flows[i],{exitX:1,entryX:0});
  p.edge('l2_seq_4','l2_p61','l2_p62',flows[4],{exitX:1,exitY:.5,entryX:1,entryY:.5,points:[[3880,485],[3880,1785]]});
  for(let i=5;i<9;i++) p.edge(`l2_seq_${i}`,specs[i][0],specs[i+1][0],flows[i],{exitX:0,entryX:1});

  stageActor(p,'l2_p41',xs[0],topY,'Applicant / Job Seeker','Selected load / validation result');
  stageActor(p,'l2_p42',xs[1],topY,'Applicant / Job Seeker<br><span style="font-size:10px">repeated</span>','Application data / submission result');
  stageActor(p,'l2_p51',xs[2],topY,'Secretary','Review selection / document view');
  stageActor(p,'l2_p52',xs[3],topY,'Secretary<br><span style="font-size:10px">repeated</span>','Review decision / recorded outcome');
  stageActor(p,'l2_p61',xs[4],topY,'Dean','Interview schedule / stage result');
  stageActor(p,'l2_p62',xs[5],bottomY,'Dean<br><span style="font-size:10px">repeated</span>','Interview decision / next schedule','below');
  stageActor(p,'l2_p63',xs[6],bottomY,'Dean<br><span style="font-size:10px">repeated</span>','Demo decision / psych schedule','below');
  stageActor(p,'l2_p64',xs[7],bottomY,'Applicant / Job Seeker<br><span style="font-size:10px">repeated</span>','Psych receipt / upload result','below');
  stageActor(p,'l2_p65',xs[8],bottomY,'Dean<br><span style="font-size:10px">repeated</span>','Final decision / recorded status','below');
  stageActor(p,'l2_p71',xs[9],bottomY,'Applicant / Staff Recipients','Feed request / notification','below');
  p.entity('l2_email','SMTP Email Service',35,1746,260,78);
  p.edge('l2_email_flow','l2_p71','l2_email','Email payload / delivery result',{both:true,exitX:0,entryX:1});

  stageStores(p,'l2_p41',xs[0],topY,[
    {number:'D1',table:'applicants',direction:'in',flow:'Profile and ban state'},
    {number:'D4',table:'job',direction:'in',flow:'Load, deadline and vacancy'},
    {number:'D9',table:'user_education',direction:'in',flow:'Education eligibility'},
    {number:'D10',table:'user_experience',direction:'in',flow:'Experience eligibility'}],'below');
  stageStores(p,'l2_p42',xs[1],topY,[
    {number:'D5',table:'job_applicants',direction:'both',flow:'Application and status'},
    {number:'D8',table:'user_draft_documents',direction:'both',flow:'Draft documents'},
    {number:'D1',table:'applicants',direction:'both',flow:'Cancellation ban state'}],'below');
  stageStores(p,'l2_p51',xs[2],topY,[{number:'D5',table:'job_applicants',direction:'in',flow:'Secretary-review application'}],'below');
  stageStores(p,'l2_p52',xs[3],topY,[
    {number:'D5',table:'job_applicants',direction:'both',flow:'Review and route status'},
    {number:'D6',table:'application_bans',direction:'out',flow:'Rejection ban record'},
    {number:'D7',table:'workflow_history',direction:'out',flow:'Secretary workflow event'},
    {number:'D14',table:'admin_activity',direction:'out',flow:'Review audit'}],'below');
  stageStores(p,'l2_p61',xs[4],topY,[
    {number:'D5',table:'job_applicants',direction:'both',flow:'Interview schedule'},
    {number:'D15',table:'rooms',direction:'in',flow:'Active rooms'}],'below');
  stageStores(p,'l2_p62',xs[5],bottomY,[
    {number:'D5',table:'job_applicants',direction:'both',flow:'Interview result / demo schedule'},
    {number:'D14',table:'admin_activity',direction:'out',flow:'Interview audit'}],'above');
  stageStores(p,'l2_p63',xs[6],bottomY,[
    {number:'D5',table:'job_applicants',direction:'both',flow:'Demo result / psych schedule'},
    {number:'D14',table:'admin_activity',direction:'out',flow:'Demo audit'}],'above');
  stageStores(p,'l2_p64',xs[7],bottomY,[{number:'D5',table:'job_applicants',direction:'out',flow:'Receipt and psych-completed stage'}],'above');
  stageStores(p,'l2_p65',xs[8],bottomY,[
    {number:'D4',table:'job',direction:'both',flow:'Teaching-load capacity'},
    {number:'D5',table:'job_applicants',direction:'both',flow:'Final application status'},
    {number:'D6',table:'application_bans',direction:'out',flow:'Rejection ban record'},
    {number:'D14',table:'admin_activity',direction:'out',flow:'Decision audit'}],'above');
  stageStores(p,'l2_p71',xs[9],bottomY,[
    {number:'D2',table:'admin_users',direction:'in',flow:'Active staff recipients'},
    {number:'D12',table:'notifications',direction:'both',flow:'Applicant notifications'},
    {number:'D13',table:'admin_notifications',direction:'both',flow:'Staff notifications'}],'above');

  p.note('l2_note','<b>Alternative outcomes:</b> A Secretary replacement request returns the applicant to 4.2. Secretary or Dean rejection records the status and ban, then invokes 7.1. A transferred application continues to 6.1.',420,1040,1450,105);
  p.note('l2_erd','<b>ERD constraint:</b> Dynamic-requirement submissions, document versions, and final teaching assignments are implemented in non-ERD tables. They remain disclosed in the report but are not drawn as data stores.',2120,1040,1450,105);
  footer(p,'Balanced with Level 1 Processes 4.0–7.0. All review and evaluation decisions are entered by the Applicant, Secretary or Dean.');
  return p;
}

const pages=[level0(),level1(),level2()];
const files=[
  ['NCHIRE_DFD_LEVEL_0_REVISED.drawio',[pages[0]]],
  ['NCHIRE_DFD_LEVEL_1_REVISED.drawio',[pages[1]]],
  ['NCHIRE_DFD_LEVEL_2_REVISED.drawio',[pages[2]]],
  ['NCHIRE_COMPLETE_REVISED_DFD.drawio',pages]
];
files.forEach(([name,content])=>fs.writeFileSync(path.join(outDir,name),mxfile(content),'utf8'));
pages.forEach(page=>fs.writeFileSync(path.join(renderDir,`${page.id}.drawio`),mxfile([page]),'utf8'));
console.log(JSON.stringify({outDir,files:files.map(f=>f[0]),pages:pages.map(p=>({id:p.id,name:p.name,width:p.width,height:p.height}))},null,2));
