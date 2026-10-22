const fs = require('fs');
const path = require('path');

const workspace = path.resolve(__dirname, '..');
const outDir = path.join(workspace, 'NCHIRE_Revised_DFD_Output');
fs.mkdirSync(outDir, { recursive: true });

const E = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;');

const titleStyle = 'text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;fontFamily=Arial;fontSize=24;fontStyle=1;fontColor=#000000;whiteSpace=wrap;';
const subtitleStyle = 'text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;fontFamily=Arial;fontSize=12;fontColor=#000000;whiteSpace=wrap;';
const sectionStyle = 'text;html=1;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;fontFamily=Arial;fontSize=14;fontStyle=1;fontColor=#000000;whiteSpace=wrap;';
const entityStyle = 'rounded=0;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=14;fontStyle=1;align=center;verticalAlign=middle;spacing=8;';
const processStyle = 'rounded=1;arcSize=22;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=14;align=center;verticalAlign=middle;spacing=8;';
const contextProcessStyle = 'ellipse;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=3;fontFamily=Arial;fontSize=18;fontStyle=1;align=center;verticalAlign=middle;spacing=10;';
const storeStyle = 'shape=partialRectangle;whiteSpace=wrap;html=1;top=1;bottom=1;left=1;right=0;strokeColor=#000000;fillColor=#ffffff;strokeWidth=2;fontFamily=Arial;fontSize=12;align=left;verticalAlign=middle;spacingLeft=12;spacingRight=8;';
const refStyle = 'rounded=1;arcSize=15;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=1.5;dashed=1;dashPattern=6 4;fontFamily=Arial;fontSize=12;align=center;verticalAlign=middle;spacing=6;';
const noteStyle = 'rounded=0;whiteSpace=wrap;html=1;strokeColor=#000000;fillColor=#ffffff;strokeWidth=1;dashed=1;dashPattern=4 4;fontFamily=Arial;fontSize=11;align=left;verticalAlign=top;spacing=8;';
const separatorStyle = 'shape=line;strokeColor=#000000;strokeWidth=1;dashed=1;dashPattern=3 5;';

class Page {
  constructor(id, name, width, height, subtitle) {
    this.id = id;
    this.name = name;
    this.width = width;
    this.height = height;
    this.cells = [];
    this.text(`${id}_title`, `<b>${name}</b>`, 80, 24, width - 160, 38, titleStyle);
    if (subtitle) this.text(`${id}_subtitle`, subtitle, 100, 66, width - 200, 44, subtitleStyle);
  }

  text(id, value, x, y, w, h, style = subtitleStyle) {
    this.cells.push(`<mxCell id="${E(id)}" value="${E(value)}" style="${E(style)}" vertex="1" parent="1"><mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/></mxCell>`);
    return id;
  }

  entity(id, label, x, y, w = 250, h = 90) {
    return this.text(id, label, x, y, w, h, entityStyle);
  }

  process(id, number, label, x, y, w = 300, h = 110) {
    return this.text(id, `<b>${number}</b><br>${label}`, x, y, w, h, processStyle);
  }

  context(id, label, x, y, w = 520, h = 280) {
    return this.text(id, label, x, y, w, h, contextProcessStyle);
  }

  store(id, number, table, x, y, w = 300, h = 62) {
    return this.text(id, `<b>${number}</b>&nbsp;&nbsp;${table}`, x, y, w, h, storeStyle);
  }

  ref(id, label, x, y, w = 260, h = 72) {
    return this.text(id, label, x, y, w, h, refStyle);
  }

  note(id, label, x, y, w, h) {
    return this.text(id, label, x, y, w, h, noteStyle);
  }

  separator(id, x, y, w) {
    return this.text(id, '', x, y, w, 1, separatorStyle);
  }

  edge(id, source, target, label, options = {}) {
    const styleParts = [
      'edgeStyle=orthogonalEdgeStyle', 'rounded=0', 'orthogonalLoop=1', 'jettySize=auto',
      'html=1', 'strokeColor=#000000', 'strokeWidth=1.5', 'endArrow=classic', 'endFill=1',
      'fontFamily=Arial', 'fontSize=11', 'fontColor=#000000', 'labelBackgroundColor=#ffffff'
    ];
    if (options.dashed) styleParts.push('dashed=1', 'dashPattern=6 4');
    if (options.exitX !== undefined) styleParts.push(`exitX=${options.exitX}`, `exitY=${options.exitY ?? 0.5}`, 'exitDx=0', 'exitDy=0');
    if (options.entryX !== undefined) styleParts.push(`entryX=${options.entryX}`, `entryY=${options.entryY ?? 0.5}`, 'entryDx=0', 'entryDy=0');
    if (options.noArrow) styleParts.splice(styleParts.indexOf('endArrow=classic'), 2, 'endArrow=none');
    let geometry = '<mxGeometry relative="1" as="geometry"/>';
    if (options.points && options.points.length) {
      geometry = `<mxGeometry relative="1" as="geometry"><Array as="points">${options.points.map(([x, y]) => `<mxPoint x="${x}" y="${y}"/>`).join('')}</Array></mxGeometry>`;
    }
    this.cells.push(`<mxCell id="${E(id)}" value="${E(label)}" style="${E(styleParts.join(';') + ';')}" edge="1" parent="1" source="${E(source)}" target="${E(target)}">${geometry}</mxCell>`);
    return id;
  }

  toDiagramXml() {
    return `<diagram id="${E(this.id)}" name="${E(this.name)}"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="${this.width}" pageHeight="${this.height}" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>${this.cells.join('')}</root></mxGraphModel></diagram>`;
  }
}

function mxfile(pages) {
  return `<?xml version="1.0" encoding="UTF-8"?>\n<mxfile host="Electron" modified="2026-10-22T00:00:00.000Z" agent="Codex NCHIRE DFD Generator" version="24.7.17" type="device" compressed="false">${pages.map(page => page.toDiagramXml()).join('')}</mxfile>\n`;
}

function addFooter(page, text) {
  page.text(`${page.id}_footer`, text, 80, page.height - 55, page.width - 160, 30, subtitleStyle);
}

function level0() {
  const p = new Page('dfd_l0', 'NCHIRE Data Flow Diagram — Level 0 (Context Diagram)', 2500, 1600,
    'Current implementation: applicant management, teaching loads, human-led recruitment workflow, notifications, dashboards, and the retained AI Recruitment Assistant chatbot.');

  p.entity('l0_app', 'Applicant / Job Seeker', 80, 250, 300, 105);
  p.entity('l0_sec', 'Secretary', 80, 665, 300, 105);
  p.entity('l0_dean', 'Dean<br><span style="font-size:11px">stored role: Department Head</span>', 80, 1080, 300, 115);
  p.entity('l0_admin', 'Administrator<br><span style="font-size:11px">stored role: Admin</span>', 2110, 245, 300, 115);
  p.entity('l0_email', 'SMTP Email Service<br><span style="font-size:11px">PHPMailer / Gmail SMTP</span>', 2110, 680, 300, 115);
  p.entity('l0_groq', 'Groq Language Model API<br><span style="font-size:11px">chatbot only</span>', 2110, 1100, 300, 115);
  p.context('l0_system', '<b>0</b><br>NCHIRE<br><span style="font-size:14px">Recruitment and Applicant<br>Management System</span>', 955, 565, 590, 340);

  p.edge('l0_e1', 'l0_app', 'l0_system', 'Registration / login; profile data;<br>job search; application and documents;<br>resubmission, cancellation, psych receipt', { exitX: 1, exitY: 0.42, entryX: 0, entryY: 0.30, points: [[610, 295], [610, 665]] });
  p.edge('l0_e2', 'l0_system', 'l0_app', 'Verification and access results;<br>teaching loads; application status;<br>schedules and notifications', { exitX: 0, exitY: 0.18, entryX: 1, entryY: 0.78, points: [[760, 625], [760, 335]] });

  p.edge('l0_e3', 'l0_sec', 'l0_system', 'Staff login; teaching-load data;<br>document-review decision;<br>chatbot or dashboard query', { exitX: 1, entryX: 0, entryY: 0.48, points: [[700, 718]] });
  p.edge('l0_e4', 'l0_system', 'l0_sec', 'Review queue and documents;<br>transfer result; statistics;<br>alerts and chatbot response', { exitX: 0, exitY: 0.62, entryX: 1, entryY: 0.78, points: [[720, 775]] });

  p.edge('l0_e5', 'l0_dean', 'l0_system', 'Staff login; schedules and results;<br>rejection / passing decision;<br>chatbot or dashboard query', { exitX: 1, exitY: 0.35, entryX: 0, entryY: 0.82, points: [[620, 1120], [620, 845]] });
  p.edge('l0_e6', 'l0_system', 'l0_dean', 'Department applications; room data;<br>stage status; statistics;<br>alerts and chatbot response', { exitX: 0, exitY: 0.92, entryX: 1, entryY: 0.72, points: [[770, 875], [770, 1160]] });

  p.edge('l0_e7', 'l0_admin', 'l0_system', 'Administrator login;<br>staff-account changes;<br>monitoring and report request', { exitX: 0, exitY: 0.45, entryX: 1, entryY: 0.18, points: [[1860, 300], [1860, 625]] });
  p.edge('l0_e8', 'l0_system', 'l0_admin', 'Authentication result;<br>staff accounts; dashboards;<br>activity and notification data', { exitX: 1, exitY: 0.30, entryX: 0, entryY: 0.78, points: [[1735, 665], [1735, 335]] });

  p.edge('l0_e9', 'l0_system', 'l0_email', 'Verification, password, schedule,<br>review, and decision email payload', { exitX: 1, exitY: 0.50, entryX: 0, entryY: 0.35, points: [[1830, 735]] });
  p.edge('l0_e10', 'l0_email', 'l0_system', 'Delivery success / failure', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.65, points: [[1790, 770]] });

  p.edge('l0_e11', 'l0_system', 'l0_groq', 'Authorized question and<br>role-scoped recruitment context', { exitX: 1, exitY: 0.82, entryX: 0, entryY: 0.30, points: [[1770, 845], [1770, 1135]] });
  p.edge('l0_e12', 'l0_groq', 'l0_system', 'Generated chatbot answer', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.95, points: [[1840, 1185], [1840, 890]] });

  p.note('l0_scope', '<b>Scope rule:</b> The verified staff chatbot is read-only and remains isolated from application decisions and workflow updates.', 765, 1285, 970, 105);
  addFooter(p, 'Balanced with Level 1: all external entities and boundary flows are expanded by Processes 1.0–9.0. No internal database stores appear in this context diagram.');
  return p;
}

function addLevel1Row(page, cfg) {
  const y = cfg.y;
  page.text(`${cfg.id}_rowtitle`, cfg.rowTitle || '', 90, y - 18, 470, 24, sectionStyle);
  const entities = cfg.entities || [];
  entities.forEach((entity, i) => {
    const eid = `${cfg.id}_ent_${i}`;
    page.entity(eid, entity.label, 100, y + 55 + i * 96, 300, 76);
    page.edge(`${cfg.id}_ent_in_${i}`, eid, cfg.id, entity.in, { exitX: 1, entryX: 0, entryY: 0.25 + i * 0.25 });
    if (entity.out) page.edge(`${cfg.id}_ent_out_${i}`, cfg.id, eid, entity.out, { exitX: 0, exitY: 0.70 + i * 0.12, entryX: 1, entryY: 0.75 });
  });
  page.process(cfg.id, cfg.number, cfg.label, 650, y + 45, 360, 130);

  const stores = cfg.stores || [];
  stores.forEach((store, i) => {
    const col = i % 4;
    const row = Math.floor(i / 4);
    const sid = `${cfg.id}_store_${i}`;
    const sx = 1170 + col * 430;
    const sy = y + 15 + row * 102;
    page.store(sid, store.number, store.table, sx, sy, 350, 60);
    if (store.direction === 'out') {
      page.edge(`${cfg.id}_store_edge_${i}`, cfg.id, sid, store.flow, { exitX: 1, exitY: 0.18 + (i % 5) * 0.16, entryX: 0, entryY: 0.5 });
    } else if (store.direction === 'both') {
      page.edge(`${cfg.id}_store_edge_in_${i}`, sid, cfg.id, store.read || 'Stored records', { exitX: 0, exitY: 0.35, entryX: 1, entryY: 0.18 + (i % 5) * 0.16 });
      page.edge(`${cfg.id}_store_edge_out_${i}`, cfg.id, sid, store.write || 'Record changes', { exitX: 1, exitY: 0.25 + (i % 5) * 0.14, entryX: 0, entryY: 0.72 });
    } else {
      page.edge(`${cfg.id}_store_edge_${i}`, sid, cfg.id, store.flow, { exitX: 0, exitY: 0.5, entryX: 1, entryY: 0.18 + (i % 5) * 0.16 });
    }
  });
  if (cfg.external) {
    const xid = `${cfg.id}_external`;
    page.entity(xid, cfg.external.label, 3210, y + 55, 300, 80);
    if (cfg.external.in) page.edge(`${cfg.id}_ext_in`, cfg.id, xid, cfg.external.in, { exitX: 1, entryX: 0, entryY: 0.35 });
    if (cfg.external.out) page.edge(`${cfg.id}_ext_out`, xid, cfg.id, cfg.external.out, { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.75 });
  }
  page.separator(`${cfg.id}_separator`, 90, y + 275, 3420);
}

function addLevel1Card(page, cfg, x, y) {
  page.text(`${cfg.id}_scope`, `<b>${cfg.number}</b> &nbsp; ${cfg.plainLabel}`, x + 20, y + 10, 1180, 28, sectionStyle);
  page.process(cfg.id, cfg.number, cfg.label, x + 350, y + 125, 350, 125);

  (cfg.entities || []).forEach((entity, i) => {
    const eid = `${cfg.id}_ent_${i}`;
    page.entity(eid, entity.label, x + 20, y + 100 + i * 100, 270, 72);
    page.edge(`${eid}_in`, eid, cfg.id, entity.in, { exitX: 1, exitY: 0.35, entryX: 0, entryY: 0.25 + i * 0.22 });
    if (entity.out) page.edge(`${eid}_out`, cfg.id, eid, entity.out, { exitX: 0, exitY: 0.68 + i * 0.12, entryX: 1, entryY: 0.76 });
  });

  if (cfg.external) {
    const xid = `${cfg.id}_external`;
    page.entity(xid, cfg.external.label, x + 930, y + 115, 290, 78);
    if (cfg.external.in) page.edge(`${xid}_in`, cfg.id, xid, cfg.external.in, { exitX: 1, exitY: 0.30, entryX: 0, entryY: 0.30 });
    if (cfg.external.out) page.edge(`${xid}_out`, xid, cfg.id, cfg.external.out, { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.75 });
  }

  (cfg.stores || []).forEach((store, i) => {
    const rightSide = i < 4;
    const sid = `${cfg.id}_store_${i}`;
    const sx = rightSide ? x + 875 : x + 45 + (i - 4) * 300;
    const sy = rightSide ? y + 310 + i * 94 : y + 800;
    const sw = rightSide ? 345 : 270;
    page.store(sid, store.number, store.table, sx, sy, sw, 58);
    if (rightSide) {
      const lane = 0.18 + i * 0.20;
      if (store.direction === 'in') page.edge(`${sid}_read`, sid, cfg.id, store.flow, { exitX: 0, entryX: 1, entryY: lane });
      if (store.direction === 'out') page.edge(`${sid}_write`, cfg.id, sid, store.flow, { exitX: 1, exitY: lane, entryX: 0 });
      if (store.direction === 'both') {
        page.edge(`${sid}_read`, sid, cfg.id, store.read, { exitX: 0, exitY: 0.30, entryX: 1, entryY: lane });
        page.edge(`${sid}_write`, cfg.id, sid, store.write, { exitX: 1, exitY: lane + 0.08, entryX: 0, entryY: 0.72 });
      }
    } else {
      const bottomIndex = i - 4;
      const anchor = 0.15 + bottomIndex * 0.23;
      if (store.direction === 'in') page.edge(`${sid}_read`, sid, cfg.id, store.flow, { exitX: 0.5, exitY: 0, entryX: anchor, entryY: 1 });
      if (store.direction === 'out') page.edge(`${sid}_write`, cfg.id, sid, store.flow, { exitX: anchor, exitY: 1, entryX: 0.5, entryY: 0 });
      if (store.direction === 'both') {
        page.edge(`${sid}_read`, sid, cfg.id, store.read, { exitX: 0.35, exitY: 0, entryX: Math.max(0.08, anchor - 0.04), entryY: 1 });
        page.edge(`${sid}_write`, cfg.id, sid, store.write, { exitX: Math.min(0.92, anchor + 0.04), exitY: 1, entryX: 0.70, entryY: 0 });
      }
    }
  });

  if (cfg.handoff) {
    const hid = `${cfg.id}_handoff`;
    page.ref(hid, cfg.handoff.label, x + 895, y + 705, 310, 68);
    page.edge(`${hid}_edge`, cfg.id, hid, cfg.handoff.flow, { exitX: 0.75, exitY: 1, entryX: 0, entryY: 0.5, points: [[x + 760, y + 690], [x + 840, y + 690], [x + 840, y + 739]] });
  }
  if (cfg.handoffIn) {
    const hid = `${cfg.id}_handoff_in`;
    page.ref(hid, cfg.handoffIn.label, x + 20, y + 405, 270, 68);
    page.edge(`${hid}_edge`, hid, cfg.id, cfg.handoffIn.flow, { exitX: 1, entryX: 0, entryY: 0.92, points: [[x + 315, y + 439], [x + 315, y + 240]] });
  }
  page.separator(`${cfg.id}_separator`, x + 10, y + 950, 1230);
}

function level1() {
  const p = new Page('dfd_l1', 'NCHIRE Data Flow Diagram — Level 1', 4050, 3380,
    'Nine current major processes. Repeated entity and store symbols are layout duplicates with the same authoritative identifier; only ERD-listed tables are drawn as stores.');
  const xs = [60, 1375, 2690];
  const ys = [120, 1160, 2200];

  addLevel1Card(p, {
    id: 'l1_p1', number: '1.0', plainLabel: 'Manage Accounts & Access', label: 'Manage Accounts<br>&amp; Access',
    entities: [
      { label: 'Applicant / Job Seeker', in: 'Signup, code, login or password request', out: 'Verification / access result' },
      { label: 'Secretary / Dean / Administrator', in: 'Staff credentials', out: 'Authorized role session' }
    ],
    stores: [
      { number: 'D1', table: 'applicants', direction: 'both', read: 'Applicant credentials / status', write: 'Verified account / password' },
      { number: 'D2', table: 'admin_users', direction: 'both', read: 'Staff credentials / role / status', write: 'Login time / password' },
      { number: 'D14', table: 'admin_activity', direction: 'out', flow: 'Staff password-reset audit' }
    ],
    external: { label: 'SMTP Email Service', in: 'Verification / password email', out: 'Delivery result' }
  }, xs[0], ys[0]);

  addLevel1Card(p, {
    id: 'l1_p2', number: '2.0', plainLabel: 'Maintain Applicant Profiles', label: 'Maintain Applicant<br>Profiles',
    entities: [{ label: 'Applicant / Job Seeker', in: 'Personal, education, experience and skill changes', out: 'Saved profile / eligibility view' }],
    stores: [
      { number: 'D1', table: 'applicants', direction: 'both', read: 'Current identity / profile', write: 'Personal / contact changes' },
      { number: 'D9', table: 'user_education', direction: 'both', read: 'Education history', write: 'Education records / documents' },
      { number: 'D10', table: 'user_experience', direction: 'both', read: 'Experience history', write: 'Experience records' },
      { number: 'D11', table: 'user_skills', direction: 'both', read: 'Skill records', write: 'Skill changes' }
    ]
  }, xs[1], ys[0]);

  addLevel1Card(p, {
    id: 'l1_p3', number: '3.0', plainLabel: 'Manage Teaching Loads & Requirements', label: 'Manage Teaching Loads<br>&amp; Requirements',
    entities: [
      { label: 'Secretary', in: 'Teaching-load / requirement data', out: 'Saved load / publication status' },
      { label: 'Dean<br><span style="font-size:11px">Department Head role</span>', in: 'Department load data', out: 'Department load status' },
      { label: 'Applicant / Job Seeker', in: 'Search / filter criteria', out: 'Available teaching loads' }
    ],
    stores: [
      { number: 'D4', table: 'job', direction: 'both', read: 'Load / vacancy data', write: 'Load details / status' },
      { number: 'D14', table: 'admin_activity', direction: 'out', flow: 'Job-management audit' }
    ]
  }, xs[2], ys[0]);

  addLevel1Card(p, {
    id: 'l1_p4', number: '4.0', plainLabel: 'Submit & Maintain Applications', label: 'Submit &amp; Maintain<br>Applications',
    entities: [{ label: 'Applicant / Job Seeker', in: 'Load, application, documents, resubmission or cancellation', out: 'Submission / application status' }],
    stores: [
      { number: 'D1', table: 'applicants', direction: 'both', read: 'Profile / ban state', write: 'Cancellation ban state' },
      { number: 'D4', table: 'job', direction: 'in', flow: 'Open load / capacity' },
      { number: 'D5', table: 'job_applicants', direction: 'both', read: 'Prior application / status', write: 'Application / documents / status' },
      { number: 'D8', table: 'user_draft_documents', direction: 'both', read: 'Saved draft documents', write: 'Draft document set' },
      { number: 'D9', table: 'user_education', direction: 'in', flow: 'Education eligibility' },
      { number: 'D10', table: 'user_experience', direction: 'in', flow: 'Experience eligibility' }
    ]
  }, xs[0], ys[1]);

  addLevel1Card(p, {
    id: 'l1_p5', number: '5.0', plainLabel: 'Review & Route Applications', label: 'Review &amp; Route<br>Applications',
    entities: [{ label: 'Secretary', in: 'Review notes / decision', out: 'Review queue / documents' }],
    stores: [
      { number: 'D1', table: 'applicants', direction: 'both', read: 'Applicant profile', write: 'Rejection ban state' },
      { number: 'D2', table: 'admin_users', direction: 'in', flow: 'Active Dean / department' },
      { number: 'D4', table: 'job', direction: 'in', flow: 'Load department / requirements' },
      { number: 'D5', table: 'job_applicants', direction: 'both', read: 'Secretary-review application', write: 'Review / route / reject status' },
      { number: 'D6', table: 'application_bans', direction: 'out', flow: 'Rejection ban audit' },
      { number: 'D7', table: 'workflow_history', direction: 'out', flow: 'Secretary workflow event' },
      { number: 'D14', table: 'admin_activity', direction: 'out', flow: 'Review action audit' }
    ],
    handoff: { label: '<b>To 7.0</b><br>Notification event', flow: 'Review outcome' }
  }, xs[1], ys[1]);

  addLevel1Card(p, {
    id: 'l1_p6', number: '6.0', plainLabel: 'Schedule & Evaluate Recruitment', label: 'Schedule &amp; Evaluate<br>Recruitment',
    entities: [
      { label: 'Dean<br><span style="font-size:11px">Department Head role</span>', in: 'Schedules, results or decision', out: 'Department queue / stages' },
      { label: 'Applicant / Job Seeker', in: 'Psychological-exam receipt', out: 'Schedule / stage status' }
    ],
    stores: [
      { number: 'D1', table: 'applicants', direction: 'both', read: 'Applicant identity', write: 'Dean rejection ban state' },
      { number: 'D4', table: 'job', direction: 'both', read: 'Teaching-load capacity', write: 'Capacity / closure' },
      { number: 'D5', table: 'job_applicants', direction: 'both', read: 'Department application', write: 'Schedule / result / final status' },
      { number: 'D6', table: 'application_bans', direction: 'out', flow: 'Dean rejection ban audit' },
      { number: 'D15', table: 'rooms', direction: 'in', flow: 'Active interview rooms' },
      { number: 'D14', table: 'admin_activity', direction: 'out', flow: 'Schedule / decision audit' }
    ],
    handoff: { label: '<b>To 7.0</b><br>Notification event', flow: 'Schedule / result event' }
  }, xs[2], ys[1]);

  addLevel1Card(p, {
    id: 'l1_p7', number: '7.0', plainLabel: 'Deliver & Track Notifications', label: 'Deliver &amp; Track<br>Notifications',
    entities: [
      { label: 'Applicant / Job Seeker', in: 'Feed request / read action', out: 'Application alerts / email' },
      { label: 'Secretary / Dean / Administrator', in: 'Feed request / read action', out: 'Staff alerts / email' }
    ],
    stores: [
      { number: 'D2', table: 'admin_users', direction: 'in', flow: 'Active staff recipients' },
      { number: 'D12', table: 'notifications', direction: 'both', read: 'Applicant feed', write: 'Applicant alert / read state' },
      { number: 'D13', table: 'admin_notifications', direction: 'both', read: 'Staff feed', write: 'Staff alert / read state' }
    ],
    external: { label: 'SMTP Email Service', in: 'Workflow email', out: 'Delivery result' },
    handoffIn: { label: '<b>From 5.0 / 6.0</b><br>Workflow events', flow: 'Notification event' }
  }, xs[0], ys[2]);

  addLevel1Card(p, {
    id: 'l1_p8', number: '8.0', plainLabel: 'Administer Staff & Monitor Operations', label: 'Administer Staff &amp;<br>Monitor Operations',
    entities: [{ label: 'Administrator<br><span style="font-size:11px">Admin role</span>', in: 'Account changes / dashboard filters', out: 'Accounts / statistics / audit' }],
    stores: [
      { number: 'D2', table: 'admin_users', direction: 'both', read: 'Staff accounts', write: 'Created / changed / deleted staff' },
      { number: 'D4', table: 'job', direction: 'in', flow: 'Teaching-load totals' },
      { number: 'D5', table: 'job_applicants', direction: 'in', flow: 'Recruitment statistics' },
      { number: 'D14', table: 'admin_activity', direction: 'both', read: 'Activity feed', write: 'Administration audit' }
    ],
    external: { label: 'SMTP Email Service', in: 'Temporary-password email', out: 'Delivery result' }
  }, xs[1], ys[2]);

  addLevel1Card(p, {
    id: 'l1_p9', number: '9.0', plainLabel: 'Provide AI Recruitment Assistant', label: 'Provide AI Recruitment<br>Assistant',
    entities: [
      { label: 'Secretary', in: 'Authorized question', out: 'Role-scoped answer' },
      { label: 'Dean<br><span style="font-size:11px">Department Head role</span>', in: 'Authorized question', out: 'Department-scoped answer' }
    ],
    stores: [
      { number: 'D2', table: 'admin_users', direction: 'in', flow: 'Active staff authorization' },
      { number: 'D1', table: 'applicants', direction: 'in', flow: 'Applicant context' },
      { number: 'D4', table: 'job', direction: 'in', flow: 'Teaching-load context' },
      { number: 'D5', table: 'job_applicants', direction: 'in', flow: 'Application / schedule context' },
      { number: 'D9', table: 'user_education', direction: 'in', flow: 'Education context' },
      { number: 'D10', table: 'user_experience', direction: 'in', flow: 'Experience context' },
      { number: 'D11', table: 'user_skills', direction: 'in', flow: 'Skill context' },
      { number: 'D14', table: 'admin_activity', direction: 'out', flow: 'Chatbot usage audit' }
    ],
    external: { label: 'Groq Language Model API', in: 'Question + role-scoped context', out: 'Generated answer' }
  }, xs[2], ys[2]);

  p.edge('l1_h1', 'l1_p4', 'l1_p5', 'Submitted / resubmitted application', { exitX: 0.5, exitY: 0, entryX: 0.5, entryY: 0, points: [[585, 1115], [1900, 1115]] });
  p.edge('l1_h2', 'l1_p5', 'l1_p6', 'Application ready for Dean', { exitX: 0.62, exitY: 0, entryX: 0.38, entryY: 0, points: [[1960, 1080], [3210, 1080]] });
  addFooter(p, 'Process sequence: 4.0 submission → 5.0 Secretary review → 6.0 Dean workflow → 7.0 notifications. D3 admin_user is intentionally absent because current routes use D2 admin_users.');
  return p;
}

function level2Accounts() {
  const p = new Page('dfd_l2_p1', 'NCHIRE Level 2 — Decomposition of 1.0 Manage Accounts & Access', 3000, 1800,
    'Parent balance: Applicant, Secretary, Dean, Administrator, SMTP Email Service, D1 applicants, D2 admin_users, and D14 admin_activity.');
  p.entity('a_app', 'Applicant / Job Seeker', 80, 280, 270, 90);
  p.entity('a_staff', 'Secretary / Dean / Administrator', 80, 720, 270, 90);
  p.entity('a_email', 'SMTP Email Service', 2630, 510, 270, 90);
  p.process('a_p11', '1.1', 'Receive Applicant<br>Registration', 480, 245, 280, 110);
  p.process('a_p12', '1.2', 'Verify Email Code &amp;<br>Create Applicant', 900, 245, 300, 110);
  p.process('a_p13', '1.3', 'Authenticate Applicant<br>or Staff', 1320, 530, 300, 110);
  p.process('a_p14', '1.4', 'Reset or Change<br>Password', 1740, 790, 290, 110);
  p.process('a_p15', '1.5', 'Establish Authorized<br>Role Session', 2160, 530, 300, 110);
  p.store('a_d1a', 'D1', 'applicants', 850, 520, 300, 62);
  p.store('a_d1b', 'D1', 'applicants', 1350, 910, 300, 62);
  p.store('a_d2', 'D2', 'admin_users', 1350, 1010, 300, 62);
  p.store('a_d14', 'D14', 'admin_activity', 1740, 1010, 300, 62);

  p.edge('a_e1', 'a_app', 'a_p11', 'Name, email and password', { exitX: 1, entryX: 0 });
  p.edge('a_e2', 'a_p11', 'a_email', 'Verification-code email', { exitX: 1, entryX: 0, entryY: 0.25, points: [[790, 180], [2500, 180], [2500, 535]] });
  p.edge('a_e3', 'a_app', 'a_p12', 'Submitted verification code', { exitX: 1, exitY: 0.78, entryX: 0, entryY: 0.72, points: [[420, 350], [420, 330]] });
  p.edge('a_e4', 'a_p12', 'a_d1a', 'Verified applicant account', { exitX: 0.5, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('a_e5', 'a_app', 'a_p13', 'Applicant credentials', { exitX: 1, entryX: 0, points: [[620, 410], [620, 585]] });
  p.edge('a_e6', 'a_staff', 'a_p13', 'Staff credentials', { exitX: 1, entryX: 0, entryY: 0.78, points: [[800, 765], [800, 615]] });
  p.edge('a_e7', 'a_d1b', 'a_p13', 'Applicant credential / verification state', { exitX: 0.5, exitY: 0, entryX: 0.5, entryY: 1 });
  p.edge('a_e8', 'a_d2', 'a_p13', 'Staff hash, role and active status', { exitX: 0.25, exitY: 0, entryX: 0.72, entryY: 1 });
  p.edge('a_e9', 'a_app', 'a_p14', 'Password reset / change request', { exitX: 1, exitY: 0.95, entryX: 0, entryY: 0.25, points: [[430, 420], [430, 830]] });
  p.edge('a_e10', 'a_staff', 'a_p14', 'Staff password request', { exitX: 1, entryX: 0, entryY: 0.70 });
  p.edge('a_e11', 'a_p14', 'a_d1b', 'Applicant password / required-change flag', { exitX: 0, exitY: 0.65, entryX: 1, entryY: 0.35 });
  p.edge('a_e12', 'a_p14', 'a_d2', 'Staff password / required-change flag', { exitX: 0, exitY: 0.85, entryX: 1, entryY: 0.70 });
  p.edge('a_e13', 'a_p14', 'a_email', 'Temporary-password email', { exitX: 1, entryX: 0, entryY: 0.75, points: [[2380, 845], [2380, 575]] });
  p.edge('a_e13b', 'a_p14', 'a_d14', 'Staff password-reset audit', { exitX: 0.65, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('a_e14', 'a_p13', 'a_p15', 'Authenticated identity and role', { exitX: 1, entryX: 0 });
  p.edge('a_e15', 'a_p15', 'a_app', 'Applicant session / access result', { exitX: 0.5, exitY: 0, entryX: 1, entryY: 0.25, points: [[2310, 420], [420, 420], [420, 300]] });
  p.edge('a_e16', 'a_p15', 'a_staff', 'Staff role session / access result', { exitX: 0.5, exitY: 1, entryX: 1, entryY: 0.75, points: [[2310, 720], [420, 720], [420, 785]] });
  p.note('a_note', '<b>Implementation note:</b> Applicant verification data is held in the PHP session until the email code is accepted. Staff authentication uses hashed passwords in admin_users; applicant authentication currently compares the stored applicant password directly.', 760, 1300, 1480, 130);
  addFooter(p, 'Balanced with Process 1.0: the same external actors, email boundary, and ERD-authorized stores enter or leave this decomposition.');
  return p;
}

function level2TeachingLoads() {
  const p = new Page('dfd_l2_p3', 'NCHIRE Level 2 — Decomposition of 3.0 Manage Teaching Loads & Requirements', 3200, 1900,
    'Parent balance: Secretary, Dean, Applicant, D4 job, and D14 admin_activity.');
  p.entity('j_staff', 'Secretary', 80, 280, 250, 80);
  p.entity('j_dean', 'Dean<br><span style="font-size:11px">Department Head role</span>', 80, 650, 250, 90);
  p.entity('j_app', 'Applicant / Job Seeker', 2820, 510, 280, 90);
  p.process('j_p31', '3.1', 'Authorize Staff &amp;<br>Department Scope', 450, 450, 270, 110);
  p.process('j_p32', '3.2', 'Create or Edit<br>Teaching Load', 850, 280, 270, 110);
  p.process('j_p33', '3.3', 'Configure Application<br>Requirements', 850, 650, 270, 110);
  p.process('j_p34', '3.4', 'Activate, Close or<br>Delete Teaching Load', 1300, 280, 290, 110);
  p.process('j_p35', '3.5', 'Publish Searchable<br>Teaching Loads', 1770, 450, 290, 110);
  p.process('j_p36', '3.6', 'Record Job-Management<br>Activity', 1300, 760, 290, 110);
  p.store('j_d4a', 'D4', 'job', 1250, 1080, 340, 62);
  p.store('j_d4b', 'D4', 'job', 1770, 1080, 340, 62);
  p.store('j_d14', 'D14', 'admin_activity', 1250, 1240, 340, 62);

  p.edge('j_e1', 'j_staff', 'j_p31', 'Teaching-load / requirement change', { exitX: 1, entryX: 0, entryY: 0.30, points: [[380, 320], [380, 490]] });
  p.edge('j_e2', 'j_dean', 'j_p31', 'Department teaching-load change', { exitX: 1, entryX: 0, entryY: 0.72, points: [[380, 695], [380, 530]] });
  p.edge('j_e3', 'j_p31', 'j_p32', 'Authorized load data', { exitX: 1, exitY: 0.30, entryX: 0, entryY: 0.65 });
  p.edge('j_e4', 'j_p31', 'j_p33', 'Authorized requirement data', { exitX: 1, exitY: 0.75, entryX: 0, entryY: 0.35 });
  p.edge('j_e5', 'j_p32', 'j_d4a', 'Teaching-load details, units,<br>capacity and deadline', { exitX: 0.5, exitY: 1, entryX: 0.35, entryY: 0 });
  p.edge('j_e6', 'j_p33', 'j_d4a', 'General requirement text /<br>load association', { exitX: 0.5, exitY: 1, entryX: 0, entryY: 0.55, points: [[985, 970], [1220, 970]] });
  p.edge('j_e7', 'j_p32', 'j_p34', 'Saved teaching load', { exitX: 1, entryX: 0 });
  p.edge('j_e8', 'j_p34', 'j_d4b', 'Status or deletion change', { exitX: 0.5, exitY: 1, entryX: 0.25, entryY: 0 });
  p.edge('j_e9', 'j_d4b', 'j_p35', 'Active load, future deadline,<br>remaining vacancies', { exitX: 0.5, exitY: 0, entryX: 0.5, entryY: 1 });
  p.edge('j_e10', 'j_app', 'j_p35', 'Search / filter criteria', { exitX: 0, entryX: 1, entryY: 0.35 });
  p.edge('j_e11', 'j_p35', 'j_app', 'Available teaching loads and details', { exitX: 1, exitY: 0.75, entryX: 0, entryY: 0.75 });
  p.edge('j_e12', 'j_p32', 'j_p36', 'Create / update event', { exitX: 0.75, exitY: 1, entryX: 0, entryY: 0.30, points: [[1100, 570], [1210, 570], [1210, 800]] });
  p.edge('j_e13', 'j_p34', 'j_p36', 'Status / deletion event', { exitX: 0.5, exitY: 1, entryX: 0.65, entryY: 0 });
  p.edge('j_e14', 'j_p36', 'j_d14', 'Job-management audit entry', { exitX: 0.5, exitY: 1, entryX: 0.5, entryY: 0 });
  p.note('j_note', '<b>ERD constraint:</b> The implementation persists per-load dynamic checklists in teaching_load_requirements and submissions in applicant_requirement_submissions. Because those tables are absent from the official ERD, they are not drawn as data stores; only the ERD-authorized D4 job record is shown.', 680, 1460, 1840, 135);
  addFooter(p, 'Balanced with Process 3.0. Requirement configuration remains visible as a verified action, while non-ERD persistence is disclosed instead of inventing a store.');
  return p;
}

function level2Applications() {
  const p = new Page('dfd_l2_p4', 'NCHIRE Level 2 — Decomposition of 4.0 Submit & Maintain Applications', 3400, 2200,
    'Parent balance: Applicant, D1 applicants, D4 job, D5 job_applicants, D8 user_draft_documents, D9 user_education, and D10 user_experience.');
  p.entity('ap_app', 'Applicant / Job Seeker', 70, 510, 270, 95);
  p.process('ap_p41', '4.1', 'Select Available<br>Teaching Load', 460, 250, 280, 110);
  p.process('ap_p42', '4.2', 'Validate Session, Profile,<br>Eligibility, Ban &amp; Vacancy', 860, 250, 330, 110);
  p.process('ap_p43', '4.3', 'Load Draft or Reusable<br>Documents', 1320, 250, 300, 110);
  p.process('ap_p44', '4.4', 'Validate Documents &amp;<br>Create Application', 1760, 250, 300, 110);
  p.process('ap_p45', '4.5', 'Replace Requested<br>Documents', 1320, 770, 300, 110);
  p.process('ap_p46', '4.6', 'Track or Cancel<br>Application', 1760, 770, 300, 110);
  p.ref('ap_to5', '<b>To 5.0</b><br>Review &amp; Route Applications', 2300, 390, 310, 90);
  p.ref('ap_to7', '<b>From 7.0</b><br>Applicant Notifications', 2300, 850, 310, 90);
  p.store('ap_d4', 'D4', 'job', 520, 1180, 310, 62);
  p.store('ap_d1', 'D1', 'applicants', 900, 1180, 310, 62);
  p.store('ap_d9', 'D9', 'user_education', 1280, 1180, 310, 62);
  p.store('ap_d10', 'D10', 'user_experience', 1660, 1180, 310, 62);
  p.store('ap_d8', 'D8', 'user_draft_documents', 1280, 1340, 310, 62);
  p.store('ap_d5a', 'D5', 'job_applicants', 2040, 1180, 310, 62);
  p.store('ap_d5b', 'D5', 'job_applicants', 2040, 1340, 310, 62);

  p.edge('ap_e1', 'ap_app', 'ap_p41', 'Search / selected load', { exitX: 1, entryX: 0, points: [[390, 555], [390, 305]] });
  p.edge('ap_e2', 'ap_d4', 'ap_p41', 'Active load, deadline and vacancy', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('ap_e3', 'ap_p41', 'ap_p42', 'Selected teaching-load ID', { exitX: 1, entryX: 0 });
  p.edge('ap_d1read', 'ap_d1', 'ap_p42', 'Verified profile and active ban state', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('ap_d9read', 'ap_d9', 'ap_p42', 'Education / full-time eligibility', { exitX: 0.2, exitY: 0, entryX: 0.70, entryY: 1, points: [[1340, 1060], [1110, 1060]] });
  p.edge('ap_d10read', 'ap_d10', 'ap_p42', 'Required work-experience record', { exitX: 0.15, exitY: 0, entryX: 1, entryY: 0.75, points: [[1710, 1080], [1240, 1080], [1240, 330]] });
  p.edge('ap_e4', 'ap_p42', 'ap_p43', 'Eligible application context', { exitX: 1, entryX: 0 });
  p.edge('ap_d8read', 'ap_d8', 'ap_p43', 'Saved draft documents', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('ap_d5reuse', 'ap_d5a', 'ap_p43', 'Newest reusable application documents', { exitX: 0, exitY: 0.25, entryX: 0.75, entryY: 1, points: [[1990, 1210], [1510, 1210]] });
  p.edge('ap_e5', 'ap_p43', 'ap_p44', 'Selected / uploaded documents', { exitX: 1, entryX: 0 });
  p.edge('ap_e6', 'ap_app', 'ap_p44', 'Application fields and new files', { exitX: 1, exitY: 0.35, entryX: 0, entryY: 0.75, points: [[760, 520], [1680, 520], [1680, 330]] });
  p.edge('ap_write', 'ap_p44', 'ap_d5a', 'Application + document references;<br>status Submitted / secretary_review', { exitX: 0.65, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('ap_handoff', 'ap_p44', 'ap_to5', 'Submitted application', { exitX: 1, entryX: 0 });
  p.edge('ap_resub_in', 'ap_app', 'ap_p45', 'Requested replacement files', { exitX: 1, exitY: 0.75, entryX: 0, entryY: 0.50, points: [[780, 580], [780, 825]] });
  p.edge('ap_resub_read', 'ap_d5b', 'ap_p45', 'Requested document list and current files', { exitX: 0, exitY: 0.35, entryX: 0.75, entryY: 1, points: [[1970, 1370], [1530, 1370]] });
  p.edge('ap_resub_write', 'ap_p45', 'ap_d5b', 'Replacement files; status Resubmitted', { exitX: 0.70, exitY: 1, entryX: 0, entryY: 0.75, points: [[1650, 1010], [1990, 1010], [1990, 1380]] });
  p.edge('ap_resub_to5', 'ap_p45', 'ap_to5', 'Resubmitted application', { exitX: 1, exitY: 0.35, entryX: 0, entryY: 0.75, points: [[2190, 805], [2190, 455]] });
  p.edge('ap_track_in', 'ap_app', 'ap_p46', 'Status view or cancellation request', { exitX: 1, exitY: 0.92, entryX: 0, entryY: 0.72, points: [[1040, 600], [1040, 850]] });
  p.edge('ap_track_read', 'ap_d5a', 'ap_p46', 'Application status and schedules', { exitX: 0.75, exitY: 0, entryX: 0.65, entryY: 1 });
  p.edge('ap_cancel_write', 'ap_p46', 'ap_d5b', 'Cancelled status / reason', { exitX: 0.70, exitY: 1, entryX: 0.70, entryY: 0 });
  p.edge('ap_ban_write', 'ap_p46', 'ap_d1', 'Four-month cancellation ban date', { exitX: 0, exitY: 0.75, entryX: 0.80, entryY: 0, points: [[1700, 850], [1100, 850], [1100, 1160]] });
  p.edge('ap_notify', 'ap_to7', 'ap_p46', 'Stored notifications / status alerts', { exitX: 0, entryX: 1 });
  p.edge('ap_out', 'ap_p46', 'ap_app', 'Application status / cancellation result', { exitX: 0, exitY: 0.45, entryX: 1, entryY: 0.95, points: [[820, 820], [820, 610]] });
  p.note('ap_note', '<b>ERD constraint:</b> Active document filenames remain in D5 and drafts in D8. The implemented version-history and dynamic-requirement tables are not drawn because they are absent from the official ERD.', 720, 1640, 1960, 115);
  addFooter(p, 'Balanced with Process 4.0. The detailed paths preserve both first submission and Secretary-requested replacement flows.');
  return p;
}

function level2Secretary() {
  const p = new Page('dfd_l2_p5', 'NCHIRE Level 2 — Decomposition of 5.0 Review & Route Applications', 3500, 2250,
    'Parent balance: Secretary; D1, D2, D4, D5, D6, D7, D14; and handoffs to Processes 4.0, 6.0, and 7.0.');
  p.entity('s_sec', 'Secretary', 70, 500, 260, 90);
  p.process('s_p51', '5.1', 'Load Secretary<br>Review Queue', 450, 250, 280, 110);
  p.process('s_p52', '5.2', 'Inspect Application,<br>Profile &amp; Documents', 860, 250, 300, 110);
  p.process('s_p53', '5.3', 'Request Document<br>Replacement', 1320, 610, 290, 110);
  p.process('s_p54', '5.4', 'Transfer Application<br>to Dean', 1320, 250, 290, 110);
  p.process('s_p55', '5.5', 'Reject Application &amp;<br>Apply Four-Month Ban', 1320, 970, 300, 110);
  p.ref('s_to4', '<b>To 4.0</b><br>Applicant replaces requested documents', 1840, 640, 320, 90);
  p.ref('s_to6', '<b>To 6.0</b><br>Waiting for Interview Schedule', 1840, 270, 320, 90);
  p.ref('s_to7', '<b>To 7.0</b><br>Notification / email event', 2300, 620, 300, 90);
  p.store('s_d5a', 'D5', 'job_applicants', 450, 1320, 310, 62);
  p.store('s_d1', 'D1', 'applicants', 820, 1320, 310, 62);
  p.store('s_d4', 'D4', 'job', 1190, 1320, 310, 62);
  p.store('s_d2', 'D2', 'admin_users', 1560, 1320, 310, 62);
  p.store('s_d7', 'D7', 'workflow_history', 450, 1490, 310, 62);
  p.store('s_d6', 'D6', 'application_bans', 820, 1490, 310, 62);
  p.store('s_d14', 'D14', 'admin_activity', 1190, 1490, 310, 62);
  p.store('s_d5b', 'D5', 'job_applicants', 1560, 1490, 310, 62);

  p.edge('s_e1', 's_sec', 's_p51', 'Open review queue / filters', { exitX: 1, entryX: 0, points: [[390, 545], [390, 305]] });
  p.edge('s_e2', 's_d5a', 's_p51', 'Applications in secretary_review', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('s_e3', 's_p51', 's_p52', 'Selected application', { exitX: 1, entryX: 0 });
  p.edge('s_d1read', 's_d1', 's_p52', 'Applicant identity / profile', { exitX: 0.35, exitY: 0, entryX: 0.25, entryY: 1 });
  p.edge('s_d4read', 's_d4', 's_p52', 'Teaching-load department and requirements', { exitX: 0.20, exitY: 0, entryX: 0.70, entryY: 1, points: [[1250, 1200], [1080, 1200]] });
  p.edge('s_d5read', 's_d5b', 's_p52', 'Application fields and documents', { exitX: 0.15, exitY: 0, entryX: 1, entryY: 0.75, points: [[1600, 1250], [1200, 1250], [1200, 330]] });
  p.edge('s_decision', 's_sec', 's_p52', 'Review notes / decision', { exitX: 1, exitY: 0.30, entryX: 0, entryY: 0.75, points: [[620, 500], [620, 330]] });
  p.edge('s_branch1', 's_p52', 's_p54', 'Documents complete', { exitX: 1, exitY: 0.30, entryX: 0 });
  p.edge('s_branch2', 's_p52', 's_p53', 'Replacement needed', { exitX: 0.70, exitY: 1, entryX: 0, entryY: 0.30, points: [[1070, 500], [1240, 500], [1240, 650]] });
  p.edge('s_branch3', 's_p52', 's_p55', 'Reject with reason', { exitX: 0.35, exitY: 1, entryX: 0, entryY: 0.50, points: [[950, 820], [1240, 820], [1240, 1025]] });
  p.edge('s_transfer_job', 's_d4', 's_p54', 'Department routing value', { exitX: 0.65, exitY: 0, entryX: 0.45, entryY: 1 });
  p.edge('s_transfer_admin', 's_d2', 's_p54', 'Active Dean recipient', { exitX: 0.35, exitY: 0, entryX: 0.80, entryY: 1 });
  p.edge('s_transfer_write', 's_p54', 's_d5b', 'waiting_interview_schedule;<br>Secretary review metadata', { exitX: 0.5, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('s_transfer_hist', 's_p54', 's_d7', 'Transfer workflow event', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.25, points: [[1260, 340], [380, 340], [380, 1520]] });
  p.edge('s_transfer_out', 's_p54', 's_to6', 'Routed application', { exitX: 1, entryX: 0 });
  p.edge('s_resub_write', 's_p53', 's_d5b', 'Resubmission Required;<br>requested document list', { exitX: 0.5, exitY: 1, entryX: 0.25, entryY: 0 });
  p.edge('s_resub_hist', 's_p53', 's_d7', 'Resubmission-request event', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.55, points: [[1180, 680], [400, 680], [400, 1530]] });
  p.edge('s_resub_out', 's_p53', 's_to4', 'Requested document list / reason', { exitX: 1, entryX: 0 });
  p.edge('s_reject_app', 's_p55', 's_d5b', 'Rejected status and reason', { exitX: 0.65, exitY: 1, entryX: 0.75, entryY: 0 });
  p.edge('s_reject_user', 's_p55', 's_d1', 'Ban-until date, reason and count', { exitX: 0, exitY: 0.60, entryX: 1, entryY: 0.75, points: [[1150, 1030], [1150, 1365]] });
  p.edge('s_reject_ban', 's_p55', 's_d6', 'Rejection ban audit', { exitX: 0.20, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('s_reject_hist', 's_p55', 's_d7', 'Rejection workflow event', { exitX: 0, exitY: 0.85, entryX: 1, entryY: 0.80, points: [[1160, 1050], [1160, 1600], [790, 1600], [790, 1540]] });
  p.edge('s_audit_transfer', 's_p54', 's_d14', 'Transfer audit', { exitX: 0.25, exitY: 1, entryX: 0.65, entryY: 0 });
  p.edge('s_audit_resub', 's_p53', 's_d14', 'Resubmission audit', { exitX: 0.55, exitY: 1, entryX: 0.35, entryY: 0 });
  p.edge('s_audit_reject', 's_p55', 's_d14', 'Rejection audit', { exitX: 0.35, exitY: 1, entryX: 0.80, entryY: 0 });
  p.edge('s_notify1', 's_p53', 's_to7', 'Resubmission email event', { exitX: 1, exitY: 0.65, entryX: 0, entryY: 0.35 });
  p.edge('s_notify2', 's_p54', 's_to7', 'Applicant + Dean notification event', { exitX: 1, exitY: 0.30, entryX: 0, entryY: 0.25, points: [[2220, 305], [2220, 650]] });
  p.edge('s_notify3', 's_p55', 's_to7', 'Rejection email event', { exitX: 1, exitY: 0.50, entryX: 0, entryY: 0.75, points: [[2200, 1025], [2200, 685]] });
  p.edge('s_out', 's_p52', 's_sec', 'Application details / review result', { exitX: 0, exitY: 0.25, entryX: 1, entryY: 0.75, points: [[600, 270], [600, 570]] });
  p.note('s_note', '<b>Actual logging behavior:</b> Secretary transfer, resubmission request, and rejection write D7 workflow_history. Dean-stage updates do not currently write D7 in the implemented endpoint.', 2040, 1320, 900, 105);
  addFooter(p, 'Balanced with Process 5.0. Secretary review decisions and workflow outcomes are entered by authorized staff.');
  return p;
}

function level2Dean() {
  const p = new Page('dfd_l2_p6', 'NCHIRE Level 2 — Decomposition of 6.0 Schedule & Evaluate Recruitment', 3900, 2450,
    'Parent balance: Dean, Applicant, D1 applicants, D4 job, D5 job_applicants, D6 application_bans, D14 admin_activity, D15 rooms, and notification handoff.');
  p.entity('d_dean', 'Dean<br><span style="font-size:11px">Department Head role</span>', 70, 520, 270, 95);
  p.entity('d_app', 'Applicant / Job Seeker', 70, 1130, 270, 90);
  p.process('d_p61', '6.1', 'Load Department-Scoped<br>Application', 460, 250, 310, 110);
  p.process('d_p62', '6.2', 'Schedule or Reschedule<br>Interview', 900, 250, 310, 110);
  p.process('d_p63', '6.3', 'Record Interview<br>Result', 1340, 250, 290, 110);
  p.process('d_p64', '6.4', 'Schedule or Reschedule<br>Demo Teaching', 1780, 250, 310, 110);
  p.process('d_p65', '6.5', 'Record Demo Result &amp;<br>Schedule Psych Exam', 2220, 250, 320, 110);
  p.process('d_p66', '6.6', 'Receive Psychological-<br>Exam Receipt', 2660, 660, 320, 110);
  p.process('d_p67', '6.7', 'Finalize Passing &amp;<br>Teaching-Load Capacity', 3100, 250, 320, 110);
  p.process('d_p68', '6.8', 'Reject Application &amp;<br>Apply Ban', 1780, 900, 310, 110);
  p.ref('d_to7', '<b>To 7.0</b><br>Schedule / result notification event', 3100, 850, 330, 90);
  p.store('d_d5a', 'D5', 'job_applicants', 500, 1370, 320, 62);
  p.store('d_d15', 'D15', 'rooms', 900, 1370, 320, 62);
  p.store('d_d5b', 'D5', 'job_applicants', 1300, 1370, 320, 62);
  p.store('d_d5c', 'D5', 'job_applicants', 1700, 1370, 320, 62);
  p.store('d_d5d', 'D5', 'job_applicants', 2100, 1370, 320, 62);
  p.store('d_d1', 'D1', 'applicants', 2500, 1370, 320, 62);
  p.store('d_d6', 'D6', 'application_bans', 2900, 1370, 320, 62);
  p.store('d_d4', 'D4', 'job', 3300, 1370, 320, 62);
  p.store('d_d14', 'D14', 'admin_activity', 1700, 1570, 320, 62);

  p.edge('d_e1', 'd_dean', 'd_p61', 'Open assigned application', { exitX: 1, entryX: 0, points: [[400, 565], [400, 305]] });
  p.edge('d_e2', 'd_d5a', 'd_p61', 'Department and workflow-scoped record', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('d_e3', 'd_p61', 'd_p62', 'waiting_interview_schedule application', { exitX: 1, entryX: 0 });
  p.edge('d_room', 'd_d15', 'd_p62', 'Active room and campus location', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('d_sched_in', 'd_dean', 'd_p62', 'Interview date, time, room and notes', { exitX: 1, exitY: 0.25, entryX: 0, entryY: 0.75, points: [[620, 515], [840, 515], [840, 330]] });
  p.edge('d_sched_write', 'd_p62', 'd_d5b', 'Interview schedule / room ID;<br>stage interview_scheduled', { exitX: 0.65, exitY: 1, entryX: 0.30, entryY: 0 });
  p.edge('d_e4', 'd_p62', 'd_p63', 'Scheduled interview', { exitX: 1, entryX: 0 });
  p.edge('d_result_in', 'd_dean', 'd_p63', 'Interview approval and notes', { exitX: 1, exitY: 0.42, entryX: 0, entryY: 0.75, points: [[840, 540], [1280, 540], [1280, 330]] });
  p.edge('d_result_write', 'd_p63', 'd_d5b', 'Interview Passed;<br>stage interview_completed', { exitX: 0.5, exitY: 1, entryX: 0.70, entryY: 0 });
  p.edge('d_e5', 'd_p63', 'd_p64', 'Approved interview', { exitX: 1, entryX: 0 });
  p.edge('d_demo_in', 'd_dean', 'd_p64', 'Demo date, time, venue and notes', { exitX: 1, exitY: 0.60, entryX: 0, entryY: 0.75, points: [[1260, 570], [1720, 570], [1720, 330]] });
  p.edge('d_demo_write', 'd_p64', 'd_d5c', 'Demo schedule;<br>stage demo_scheduled', { exitX: 0.45, exitY: 1, entryX: 0.35, entryY: 0 });
  p.edge('d_e6', 'd_p64', 'd_p65', 'Scheduled demo', { exitX: 1, entryX: 0 });
  p.edge('d_demo_result', 'd_dean', 'd_p65', 'Demo approval and psych schedule', { exitX: 1, exitY: 0.78, entryX: 0, entryY: 0.75, points: [[1690, 600], [2160, 600], [2160, 330]] });
  p.edge('d_psych_write', 'd_p65', 'd_d5d', 'Demo Passed / psych schedule;<br>stages demo_completed, psych_scheduled', { exitX: 0.45, exitY: 1, entryX: 0.35, entryY: 0 });
  p.edge('d_e7', 'd_p65', 'd_p66', 'Psych exam request', { exitX: 1, exitY: 0.75, entryX: 0, entryY: 0.25, points: [[2600, 330], [2600, 700]] });
  p.edge('d_receipt', 'd_app', 'd_p66', 'Psychological-exam receipt file', { exitX: 1, entryX: 0, entryY: 0.75, points: [[1120, 1175], [1120, 740]] });
  p.edge('d_receipt_write', 'd_p66', 'd_d5d', 'Receipt reference;<br>stage psych_completed', { exitX: 0.35, exitY: 1, entryX: 0.75, entryY: 0 });
  p.edge('d_e8', 'd_p66', 'd_p67', 'Receipt-complete application', { exitX: 1, exitY: 0.25, entryX: 0.5, entryY: 1, points: [[3260, 700], [3260, 390]] });
  p.edge('d_final_decision', 'd_dean', 'd_p67', 'Final passing decision / notes', { exitX: 1, exitY: 0.95, entryX: 0, entryY: 0.75, points: [[2810, 610], [3040, 610], [3040, 330]] });
  p.edge('d_final_app', 'd_p67', 'd_d5d', 'Passed status / final notes', { exitX: 0.35, exitY: 1, entryX: 1, entryY: 0.30, points: [[3190, 1200], [2460, 1200], [2460, 1390]] });
  p.edge('d_final_job', 'd_p67', 'd_d4', 'Decrease available sections;<br>close load at zero capacity', { exitX: 0.65, exitY: 1, entryX: 0.5, entryY: 0 });
  p.edge('d_reject_in', 'd_dean', 'd_p68', 'Rejection reason', { exitX: 1, exitY: 0.88, entryX: 0, entryY: 0.55, points: [[1200, 610], [1200, 955]] });
  p.edge('d_reject_app', 'd_p68', 'd_d5c', 'Rejected status / reason', { exitX: 0.35, exitY: 1, entryX: 0.65, entryY: 0 });
  p.edge('d_reject_user', 'd_p68', 'd_d1', 'Ban-until date and reason', { exitX: 1, exitY: 0.70, entryX: 0, entryY: 0.65, points: [[2360, 980], [2360, 1410]] });
  p.edge('d_reject_ban', 'd_p68', 'd_d6', 'Rejection ban audit', { exitX: 1, exitY: 0.85, entryX: 0, entryY: 0.80, points: [[2770, 1000], [2770, 1420]] });
  p.edge('d_audit1', 'd_p62', 'd_d14', 'Interview scheduling audit', { exitX: 0, exitY: 0.80, entryX: 0, entryY: 0.25, points: [[840, 340], [840, 1680], [1660, 1680]] });
  p.edge('d_audit2', 'd_p64', 'd_d14', 'Demo scheduling audit', { exitX: 0.25, exitY: 1, entryX: 0.30, entryY: 0 });
  p.edge('d_audit3', 'd_p67', 'd_d14', 'Final decision audit', { exitX: 0.15, exitY: 1, entryX: 1, entryY: 0.35, points: [[3040, 1200], [2100, 1200], [2100, 1600]] });
  p.edge('d_notify', 'd_p65', 'd_to7', 'Schedule / result event', { exitX: 1, exitY: 0.65, entryX: 0, entryY: 0.25, points: [[3020, 330], [3020, 875]] });
  p.edge('d_notify2', 'd_p66', 'd_to7', 'Receipt-upload event', { exitX: 1, entryX: 0, entryY: 0.55 });
  p.edge('d_notify3', 'd_p67', 'd_to7', 'Passing event', { exitX: 0.65, exitY: 1, entryX: 0.70, entryY: 0, points: [[3350, 600], [3350, 820]] });
  p.edge('d_out', 'd_p67', 'd_app', 'Final status', { exitX: 0, exitY: 0.45, entryX: 1, entryY: 0.25, points: [[3000, 300], [3000, 1080], [400, 1080], [400, 1150]] });
  p.note('d_note', '<b>ERD constraint:</b> Final passing invokes teaching_assignments in the current code before D4/D5 are finalized. That table is absent from the ERD, so it is disclosed here but not drawn as a data store.', 1260, 1880, 1440, 110);
  addFooter(p, 'All interview, demo, psychological-exam, rejection, and passing decisions are entered by authorized staff or the applicant.');
  return p;
}

function level2Notifications() {
  const p = new Page('dfd_l2_p7', 'NCHIRE Level 2 — Decomposition of 7.0 Deliver & Track Notifications', 3100, 1900,
    'Parent balance: Applicant, Secretary, Dean, Administrator, SMTP Email Service, D2 admin_users, D12 notifications, and D13 admin_notifications.');
  p.ref('n_events', '<b>From Processes 1.0, 5.0, 6.0 &amp; 8.0</b><br>Verification, review, schedule, decision or account event', 90, 280, 380, 95);
  p.entity('n_app', 'Applicant / Job Seeker', 90, 930, 280, 90);
  p.entity('n_staff', 'Secretary / Dean / Administrator', 90, 1250, 280, 90);
  p.entity('n_email', 'SMTP Email Service', 2720, 600, 280, 90);
  p.process('n_p71', '7.1', 'Receive Notification<br>Event', 600, 280, 280, 110);
  p.process('n_p72', '7.2', 'Resolve Applicant or<br>Staff Recipients', 1010, 280, 290, 110);
  p.process('n_p73', '7.3', 'Store In-App<br>Notification', 1420, 280, 280, 110);
  p.process('n_p74', '7.4', 'Send Workflow<br>Email', 1830, 280, 280, 110);
  p.process('n_p75', '7.5', 'Retrieve Notification<br>Feed', 1010, 1030, 290, 110);
  p.process('n_p76', '7.6', 'Mark One or All<br>Notifications Read', 1420, 1030, 290, 110);
  p.store('n_d2', 'D2', 'admin_users', 970, 650, 320, 62);
  p.store('n_d12', 'D12', 'notifications', 1390, 650, 320, 62);
  p.store('n_d13', 'D13', 'admin_notifications', 1810, 650, 320, 62);

  p.edge('n_e1', 'n_events', 'n_p71', 'Event data', { exitX: 1, entryX: 0 });
  p.edge('n_e2', 'n_p71', 'n_p72', 'Title, message, event type and subject', { exitX: 1, entryX: 0 });
  p.edge('n_d2read', 'n_d2', 'n_p72', 'Active staff IDs and email addresses', { exitX: 0.5, exitY: 0, entryX: 0.40, entryY: 1 });
  p.edge('n_e3', 'n_p72', 'n_p73', 'Resolved recipient and message', { exitX: 1, entryX: 0 });
  p.edge('n_write_app', 'n_p73', 'n_d12', 'Applicant notification', { exitX: 0.40, exitY: 1, entryX: 0.35, entryY: 0 });
  p.edge('n_write_staff', 'n_p73', 'n_d13', 'Staff notification', { exitX: 0.75, exitY: 1, entryX: 0.25, entryY: 0, points: [[1700, 540], [1870, 540]] });
  p.edge('n_e4', 'n_p72', 'n_p74', 'Email recipient and message', { exitX: 0.75, exitY: 0, entryX: 0, entryY: 0.35, points: [[1230, 190], [1760, 190], [1760, 320]] });
  p.edge('n_e5', 'n_p74', 'n_email', 'SMTP email payload', { exitX: 1, entryX: 0, points: [[2360, 335], [2360, 645]] });
  p.edge('n_e6', 'n_email', 'n_p74', 'Delivery result', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.75, points: [[2310, 670], [2310, 360]] });
  p.edge('n_app_req', 'n_app', 'n_p75', 'Applicant feed request', { exitX: 1, entryX: 0, entryY: 0.35 });
  p.edge('n_staff_req', 'n_staff', 'n_p75', 'Staff feed request', { exitX: 1, entryX: 0, entryY: 0.75, points: [[700, 1295], [700, 1110]] });
  p.edge('n_d12read', 'n_d12', 'n_p75', 'Applicant notifications', { exitX: 0.35, exitY: 1, entryX: 0.60, entryY: 0, points: [[1500, 900], [1180, 900]] });
  p.edge('n_d13read', 'n_d13', 'n_p75', 'Staff notifications', { exitX: 0.25, exitY: 1, entryX: 1, entryY: 0.25, points: [[1870, 930], [1340, 930], [1340, 1060]] });
  p.edge('n_feed_app', 'n_p75', 'n_app', 'Applicant feed / unread count', { exitX: 0, exitY: 0.55, entryX: 1, entryY: 0.75 });
  p.edge('n_feed_staff', 'n_p75', 'n_staff', 'Staff feed / unread count', { exitX: 0, exitY: 0.80, entryX: 1, entryY: 0.25, points: [[700, 1120], [700, 1270]] });
  p.edge('n_e7', 'n_p75', 'n_p76', 'Notification ID / mark-all request', { exitX: 1, entryX: 0 });
  p.edge('n_mark_app', 'n_p76', 'n_d12', 'Applicant read-state update', { exitX: 0.35, exitY: 0, entryX: 0.65, entryY: 1 });
  p.edge('n_mark_staff', 'n_p76', 'n_d13', 'Staff read-state update', { exitX: 0.70, exitY: 0, entryX: 0.65, entryY: 1, points: [[1620, 900], [2020, 900]] });
  p.note('n_note', '<b>Verified delivery channels:</b> Applicant notifications are keyed primarily by user_email; staff notifications use admin_id or a global NULL recipient. Email delivery is performed by PHPMailer over Gmail SMTP.', 760, 1450, 1580, 105);
  addFooter(p, 'Balanced with Process 7.0. Email is an external service; D12 and D13 remain the only notification data stores authorized by the ERD.');
  return p;
}

function level2Admin() {
  const p = new Page('dfd_l2_p8', 'NCHIRE Level 2 — Decomposition of 8.0 Administer Staff & Monitor Operations', 3200, 1950,
    'Parent balance: Administrator, D2 admin_users, D4 job, D5 job_applicants, D14 admin_activity, plus password-email handoff.');
  p.entity('m_admin', 'Administrator<br><span style="font-size:11px">Admin role</span>', 80, 500, 280, 90);
  p.entity('m_email', 'SMTP Email Service', 2800, 310, 280, 90);
  p.process('m_p81', '8.1', 'Authorize Administrator<br>Request', 480, 250, 300, 110);
  p.process('m_p82', '8.2', 'List, Create, Update or<br>Delete Staff Account', 920, 250, 320, 110);
  p.process('m_p83', '8.3', 'Issue Temporary<br>Password', 1380, 250, 290, 110);
  p.process('m_p84', '8.4', 'Compute Role-Scoped<br>Dashboard Statistics', 920, 800, 320, 110);
  p.process('m_p85', '8.5', 'Present Activity &amp;<br>Operations Monitor', 1380, 800, 300, 110);
  p.store('m_d2', 'D2', 'admin_users', 900, 1170, 330, 62);
  p.store('m_d4', 'D4', 'job', 1310, 1170, 330, 62);
  p.store('m_d5', 'D5', 'job_applicants', 1720, 1170, 330, 62);
  p.store('m_d14', 'D14', 'admin_activity', 2130, 1170, 330, 62);

  p.edge('m_e1', 'm_admin', 'm_p81', 'Account-management or dashboard request', { exitX: 1, entryX: 0, points: [[420, 545], [420, 305]] });
  p.edge('m_e2', 'm_p81', 'm_p82', 'Authorized account operation', { exitX: 1, entryX: 0 });
  p.edge('m_e3', 'm_p81', 'm_p84', 'Authorized dashboard filters', { exitX: 0.65, exitY: 1, entryX: 0, entryY: 0.35, points: [[680, 650], [850, 650], [850, 840]] });
  p.edge('m_d2read', 'm_d2', 'm_p82', 'Existing staff accounts', { exitX: 0.35, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('m_d2write', 'm_p82', 'm_d2', 'Created / changed / deleted account', { exitX: 0.65, exitY: 1, entryX: 0.70, entryY: 0 });
  p.edge('m_e4', 'm_p82', 'm_p83', 'New staff identity and generated password', { exitX: 1, entryX: 0 });
  p.edge('m_email_out', 'm_p83', 'm_email', 'Temporary-password email', { exitX: 1, entryX: 0 });
  p.edge('m_email_back', 'm_email', 'm_p83', 'Delivery result', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.75, points: [[2730, 385], [2730, 350]] });
  p.edge('m_job_read', 'm_d4', 'm_p84', 'Teaching-load totals', { exitX: 0.25, exitY: 0, entryX: 0.60, entryY: 1 });
  p.edge('m_app_read', 'm_d5', 'm_p84', 'Recruitment counts and recent records', { exitX: 0.20, exitY: 0, entryX: 1, entryY: 0.75, points: [[1760, 1050], [1280, 1050], [1280, 870]] });
  p.edge('m_e5', 'm_p84', 'm_p85', 'Statistics and recent activity request', { exitX: 1, entryX: 0 });
  p.edge('m_log_read', 'm_d14', 'm_p85', 'Activity feed', { exitX: 0.25, exitY: 0, entryX: 1, entryY: 0.75, points: [[2200, 1030], [1720, 1030], [1720, 870]] });
  p.edge('m_log_write', 'm_p82', 'm_d14', 'Staff-account audit entry', { exitX: 0.75, exitY: 1, entryX: 0.60, entryY: 0, points: [[1180, 620], [2320, 620], [2320, 1150]] });
  p.edge('m_out1', 'm_p82', 'm_admin', 'Updated staff account list', { exitX: 0, exitY: 0.25, entryX: 1, entryY: 0.35, points: [[820, 270], [420, 270], [420, 530]] });
  p.edge('m_out2', 'm_p85', 'm_admin', 'Dashboard statistics and audit feed', { exitX: 0, exitY: 0.70, entryX: 1, entryY: 0.75, points: [[780, 875], [780, 570]] });
  p.note('m_note', '<b>Role boundary:</b> Only the Admin role can create or delete staff accounts. Dashboard data is a regular aggregation of D4, D5, and D14 records.', 760, 1450, 1700, 105);
  addFooter(p, 'Balanced with Process 8.0. D3 admin_user is not shown because no current route reads or writes it.');
  return p;
}

function level2Chatbot() {
  const p = new Page('dfd_l2_p9', 'NCHIRE Level 2 — Decomposition of 9.0 Provide AI Recruitment Assistant', 3700, 2300,
    'Parent balance: Secretary, Dean, Groq Language Model API, D1, D2, D4, D5, D9, D10, D11, and D14. The chatbot is read-only with session-based conversation history.');
  p.entity('c_sec', 'Secretary', 70, 340, 250, 80);
  p.entity('c_dean', 'Dean<br><span style="font-size:11px">Department Head role</span>', 70, 690, 250, 90);
  p.entity('c_groq', 'Groq Language Model API', 3290, 490, 300, 90);
  p.process('c_p91', '9.1', 'Authenticate Active<br>Staff Role', 440, 460, 290, 110);
  p.process('c_p92', '9.2', 'Classify Intent &amp;<br>Session Context', 850, 460, 290, 110);
  p.process('c_p93', '9.3', 'Retrieve Role-Scoped<br>Recruitment Records', 1260, 460, 310, 110);
  p.process('c_p94', '9.4', 'Build Deterministic<br>Answer or Safe Prompt', 1710, 460, 310, 110);
  p.process('c_p95', '9.5', 'Generate Chatbot<br>Answer', 2160, 460, 290, 110);
  p.process('c_p96', '9.6', 'Return Response &amp;<br>Update Session History', 2580, 460, 320, 110);
  p.process('c_p97', '9.7', 'Log Chatbot<br>Usage', 2160, 930, 290, 110);
  p.store('c_d2', 'D2', 'admin_users', 430, 1250, 320, 62);
  p.store('c_d1', 'D1', 'applicants', 830, 1250, 320, 62);
  p.store('c_d4', 'D4', 'job', 1230, 1250, 320, 62);
  p.store('c_d5', 'D5', 'job_applicants', 1630, 1250, 320, 62);
  p.store('c_d9', 'D9', 'user_education', 830, 1410, 320, 62);
  p.store('c_d10', 'D10', 'user_experience', 1230, 1410, 320, 62);
  p.store('c_d11', 'D11', 'user_skills', 1630, 1410, 320, 62);
  p.store('c_d14', 'D14', 'admin_activity', 2160, 1410, 320, 62);

  p.edge('c_e1', 'c_sec', 'c_p91', 'Chatbot question', { exitX: 1, entryX: 0, entryY: 0.30, points: [[380, 380], [380, 495]] });
  p.edge('c_e2', 'c_dean', 'c_p91', 'Chatbot question', { exitX: 1, entryX: 0, entryY: 0.72, points: [[380, 735], [380, 535]] });
  p.edge('c_auth', 'c_d2', 'c_p91', 'Active status and role', { exitX: 0.5, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('c_e3', 'c_p91', 'c_p92', 'Authorized message', { exitX: 1, entryX: 0 });
  p.edge('c_e4', 'c_p92', 'c_p93', 'Statistics, job, applicant,<br>schedule or general intent', { exitX: 1, entryX: 0 });
  p.edge('c_r1', 'c_d1', 'c_p93', 'Applicant identity', { exitX: 0.5, exitY: 0, entryX: 0.15, entryY: 1 });
  p.edge('c_r2', 'c_d4', 'c_p93', 'Vacancy and teaching-load data', { exitX: 0.35, exitY: 0, entryX: 0.35, entryY: 1 });
  p.edge('c_r3', 'c_d5', 'c_p93', 'Application, status and schedule data', { exitX: 0.20, exitY: 0, entryX: 0.65, entryY: 1, points: [[1680, 1130], [1470, 1130]] });
  p.edge('c_r4', 'c_d9', 'c_p93', 'Education context', { exitX: 0.5, exitY: 0, entryX: 0.15, entryY: 1, points: [[990, 1110], [1340, 1110]] });
  p.edge('c_r5', 'c_d10', 'c_p93', 'Experience context', { exitX: 0.5, exitY: 0, entryX: 0.50, entryY: 1 });
  p.edge('c_r6', 'c_d11', 'c_p93', 'Skill context', { exitX: 0.30, exitY: 0, entryX: 0.85, entryY: 1, points: [[1730, 1090], [1510, 1090]] });
  p.edge('c_e5', 'c_p93', 'c_p94', 'Sanitized role-scoped records', { exitX: 1, entryX: 0 });
  p.edge('c_local', 'c_p94', 'c_p96', 'Local statistics, list or status answer', { exitX: 0.60, exitY: 0, entryX: 0.45, entryY: 0, points: [[1895, 270], [2740, 270], [2740, 440]] });
  p.edge('c_prompt', 'c_p94', 'c_p95', 'Safe prompt + selected context', { exitX: 1, entryX: 0 });
  p.edge('c_groq_out', 'c_p95', 'c_groq', 'Question and context', { exitX: 1, entryX: 0, entryY: 0.35 });
  p.edge('c_groq_in', 'c_groq', 'c_p95', 'Generated answer / API error', { exitX: 0, exitY: 0.75, entryX: 1, entryY: 0.75 });
  p.edge('c_e6', 'c_p95', 'c_p96', 'AI answer or safe fallback', { exitX: 1, entryX: 0 });
  p.edge('c_out1', 'c_p96', 'c_sec', 'Chatbot response', { exitX: 0.55, exitY: 0, entryX: 1, entryY: 0.35, points: [[2750, 220], [380, 220], [380, 370]] });
  p.edge('c_out2', 'c_p96', 'c_dean', 'Chatbot response', { exitX: 0.35, exitY: 1, entryX: 1, entryY: 0.70, points: [[2680, 760], [380, 760]] });
  p.edge('c_log', 'c_p96', 'c_p97', 'Intent and success / failure result', { exitX: 0.5, exitY: 1, entryX: 1, entryY: 0.35, points: [[2740, 980], [2500, 980]] });
  p.edge('c_log_write', 'c_p97', 'c_d14', 'chatbot_* activity record', { exitX: 0.5, exitY: 1, entryX: 0.5, entryY: 0 });
  p.note('c_note1', '<b>No chatbot data store:</b> The last eight messages and follow-up context are stored only in the authenticated PHP session. Clearing the conversation unsets those session values.', 680, 1700, 1300, 115);
  p.note('c_note2', '<b>Scope boundary:</b> The assistant reads authorized, role-scoped recruitment records and answers staff questions. It cannot update applicant, teaching-load, application, or workflow records.', 2070, 1700, 1200, 115);
  addFooter(p, 'Groq connectivity was verified on 22 October 2026. Conversation history remains session-based and is not persisted in a database table.');
  return p;
}

const pages = {
  level0: level0(),
  level1: level1(),
  level2Accounts: level2Accounts(),
  level2TeachingLoads: level2TeachingLoads(),
  level2Applications: level2Applications(),
  level2Secretary: level2Secretary(),
  level2Dean: level2Dean(),
  level2Notifications: level2Notifications(),
  level2Admin: level2Admin(),
  level2Chatbot: level2Chatbot(),
};

const level2Pages = [
  pages.level2Accounts,
  pages.level2TeachingLoads,
  pages.level2Applications,
  pages.level2Secretary,
  pages.level2Dean,
  pages.level2Notifications,
  pages.level2Admin,
  pages.level2Chatbot,
];

const outputs = [
  ['NCHIRE_DFD_LEVEL_0_REVISED.drawio', [pages.level0]],
  ['NCHIRE_DFD_LEVEL_1_REVISED.drawio', [pages.level1]],
  ['NCHIRE_DFD_LEVEL_2_REVISED.drawio', level2Pages],
  ['NCHIRE_COMPLETE_REVISED_DFD.drawio', [pages.level0, pages.level1, ...level2Pages]],
];

for (const [fileName, filePages] of outputs) {
  fs.writeFileSync(path.join(outDir, fileName), mxfile(filePages), 'utf8');
}

// One-page exports are build inputs for diagrams.net PNG rendering and make
// each Level 2 decomposition independently inspectable during validation.
const exportDir = path.join(outDir, '.render_sources');
fs.mkdirSync(exportDir, { recursive: true });
for (const page of Object.values(pages)) {
  fs.writeFileSync(path.join(exportDir, `${page.id}.drawio`), mxfile([page]), 'utf8');
}

console.log(JSON.stringify({ outDir, files: outputs.map(([name]) => name), pages: Object.values(pages).map(p => ({ id: p.id, name: p.name, width: p.width, height: p.height })) }, null, 2));
