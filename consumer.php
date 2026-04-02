<?php
session_start();
require_once __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'consumer') { header("Location: login.php"); exit(); }
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit(); }

$uid  = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Fetch consumer info
$consumer = $conn->query("SELECT * FROM consumers WHERE id=$uid")->fetch_assoc();
$meter    = $conn->query("SELECT * FROM meters WHERE consumer_id=$uid LIMIT 1")->fetch_assoc();

// Stats
$total_bills   = $conn->query("SELECT COUNT(*) c FROM bills WHERE consumer_id=$uid")->fetch_assoc()['c'];
$paid_bills    = $conn->query("SELECT COUNT(*) c FROM bills WHERE consumer_id=$uid AND status='Paid'")->fetch_assoc()['c'];
$pending_bills = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM bills WHERE consumer_id=$uid AND status='Pending'")->fetch_assoc();
$overdue_bills = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM bills WHERE consumer_id=$uid AND status='Overdue'")->fetch_assoc();
$total_paid    = $conn->query("SELECT COALESCE(SUM(amount_paid),0) s FROM payments WHERE consumer_id=$uid")->fetch_assoc()['s'];
$last_reading  = $conn->query("SELECT * FROM meter_readings WHERE meter_id=".($meter['id']??0)." ORDER BY reading_date DESC LIMIT 1")->fetch_assoc();

// Bills list
$bills   = $conn->query("SELECT * FROM bills WHERE consumer_id=$uid ORDER BY generated_at DESC");
$payments= $conn->query("SELECT p.*,b.bill_number FROM payments p JOIN bills b ON p.bill_id=b.id WHERE p.consumer_id=$uid ORDER BY p.payment_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Account — EIS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
:root{--bg:#0a0a0f;--bg2:#0f0f16;--bg3:#13131c;--accent:#c9a96e;--ahi:#e8c98a;--adim:rgba(201,169,110,0.12);--aglow:rgba(201,169,110,0.28);--purple:#7c6fcd;--ivory:#f0ece2;--muted:rgba(240,236,226,0.45);--border:rgba(201,169,110,0.15);--bhi:rgba(201,169,110,0.38);--pa:#c9a96e;--pb:#7a5a10;--pc:#f0d898;--pd:#3a2a08;--ok:#4caf82;--wa:#e8a245;--er:#e05c5c;--in:#5b9bd5;}
[data-theme="red"]{--accent:#e84545;--ahi:#ff8a8a;--adim:rgba(232,69,69,0.12);--aglow:rgba(232,69,69,0.28);--border:rgba(232,69,69,0.15);--bhi:rgba(232,69,69,0.38);--pa:#e84545;--pb:#7a1010;--pc:#ffaaaa;--pd:#3a0808;}
[data-theme="blue"]{--accent:#3a9bdc;--ahi:#8ecfff;--adim:rgba(58,155,220,0.12);--aglow:rgba(58,155,220,0.28);--border:rgba(58,155,220,0.15);--bhi:rgba(58,155,220,0.38);--pa:#3a9bdc;--pb:#0e3d6e;--pc:#aaddff;--pd:#061828;}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}html,body{width:100%;height:100%;overflow:hidden;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ivory);}
#c{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;}
.topbar{position:fixed;top:0;left:0;right:0;z-index:200;height:54px;background:rgba(10,10,15,0.95);border-bottom:1px solid var(--border);backdrop-filter:blur(18px);display:flex;align-items:center;gap:.8rem;padding:0 1.5rem;}
.logo{display:flex;align-items:center;gap:8px;}
.logo-icon{width:30px;height:30px;border:1.5px solid var(--accent);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:.9rem;background:var(--adim);}
.logo-name{font-family:'Cormorant Garamond',serif;font-size:.95rem;font-weight:700;letter-spacing:.05em;}.logo-name span{color:var(--accent);}
.tb-r{display:flex;align-items:center;gap:.4rem;margin-left:auto;}
.tds{display:flex;align-items:center;gap:5px;}
.td{width:12px;height:12px;border-radius:50%;border:2px solid transparent;cursor:pointer;outline:none;appearance:none;transition:transform .2s;}.td:hover{transform:scale(1.25);}.td.on{border-color:rgba(255,255,255,.8);}
.td[data-t=gold]{background:#c9a96e;}.td[data-t=red]{background:#e84545;}.td[data-t=blue]{background:#3a9bdc;}
.user-chip{display:flex;align-items:center;gap:7px;background:var(--adim);border:1px solid var(--bhi);border-radius:7px;padding:.3rem .8rem;color:var(--ivory);font-size:.73rem;font-weight:500;}
.av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--ahi));display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;color:#0a0a0f;}
.logout-btn{display:flex;align-items:center;gap:4px;background:rgba(224,92,92,.12);border:1px solid rgba(224,92,92,.3);border-radius:7px;padding:.3rem .65rem;color:#ff9191;font-size:.7rem;cursor:pointer;font-family:'Inter',sans-serif;text-decoration:none;}
/* NAVROW */
.navrow{position:fixed;top:54px;left:0;right:0;z-index:190;height:42px;background:rgba(8,8,13,0.97);border-bottom:1px solid rgba(255,255,255,0.04);backdrop-filter:blur(14px);display:flex;align-items:center;padding:0 1.5rem;gap:0;}
.nl{display:flex;align-items:center;gap:5px;padding:.42rem .9rem;color:var(--muted);font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;font-weight:500;cursor:pointer;border:none;background:transparent;font-family:'Inter',sans-serif;transition:color .2s;white-space:nowrap;position:relative;}
.nl::after{content:'';position:absolute;bottom:0;left:15%;right:15%;height:2px;background:var(--accent);border-radius:2px;transform:scaleX(0);transition:transform .25s;}
.nl:hover{color:var(--ivory);}.nl:hover::after,.nl.active::after{transform:scaleX(1);}.nl.active{color:var(--accent);}
/* MAIN */
.main{position:fixed;top:96px;left:0;right:0;bottom:0;overflow-y:auto;z-index:10;padding:1.5rem 1.8rem 3rem;}
.main::-webkit-scrollbar{width:4px;}.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
.page{display:none;}.page.active{display:block;}
/* page header */
.ph{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:1.4rem;}
.pt{font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:700;line-height:1;}.pt span{color:var(--accent);}
.ps{font-size:.7rem;color:var(--muted);margin-top:.28rem;}
/* stats */
.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:.85rem;margin-bottom:1.5rem;}
.sc{background:rgba(15,15,22,0.82);border:1px solid var(--border);border-radius:13px;padding:1rem 1.1rem;backdrop-filter:blur(16px);position:relative;overflow:hidden;transition:transform .2s;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--cc,var(--accent)),transparent);}
.sc:hover{transform:translateY(-2px);}
.sci{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem;margin-bottom:.65rem;}
.scl{font-size:.6rem;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);margin-bottom:.22rem;}
.scv{font-size:1.48rem;font-weight:600;color:var(--ivory);line-height:1;}
/* card */
.card{background:rgba(15,15,22,0.82);border:1px solid var(--border);border-radius:13px;backdrop-filter:blur(16px);overflow:hidden;margin-bottom:1rem;}
.card-h{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.2rem;border-bottom:1px solid rgba(255,255,255,.04);}
.card-ht{font-size:.75rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;}.card-ht span{color:var(--accent);}
/* table */
.tw{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.77rem;}
thead tr{border-bottom:1px solid rgba(255,255,255,.06);}
th{padding:.6rem 1rem;text-align:left;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-weight:500;}
tbody tr{border-bottom:1px solid rgba(255,255,255,.025);}
tbody tr:hover{background:rgba(255,255,255,.02);}
td{padding:.6rem 1rem;color:var(--ivory);}
.badge{display:inline-flex;padding:.14rem .52rem;border-radius:100px;font-size:.6rem;font-weight:500;}
.badge.paid{background:rgba(76,175,130,.15);color:var(--ok);border:1px solid rgba(76,175,130,.3);}
.badge.pending{background:rgba(232,162,69,.15);color:var(--wa);border:1px solid rgba(232,162,69,.3);}
.badge.overdue{background:rgba(224,92,92,.15);color:var(--er);border:1px solid rgba(224,92,92,.3);}
/* profile */
.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.prow{padding:.6rem 1.2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.03);font-size:.8rem;}
.prow:last-child{border:none;}
.plabel{color:var(--muted);font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;}
.pval{color:var(--ivory);font-weight:500;}
/* alert box */
.alert-box{background:rgba(224,92,92,.1);border:1px solid rgba(224,92,92,.25);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.7rem;}
.alert-box .ai{font-size:1.1rem;flex-shrink:0;}
.alert-box p{font-size:.8rem;color:#ffaaaa;line-height:1.5;}
.ok-box{background:rgba(76,175,130,.1);border:1px solid rgba(76,175,130,.25);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem;display:flex;align-items:center;gap:.7rem;font-size:.8rem;color:#a8f0d0;}
</style>
</head>
<body data-theme="gold">
<canvas id="c"></canvas>

<div class="topbar">
  <div class="logo"><div class="logo-icon">⚡</div><div class="logo-name">E<span>IS</span></div></div>
  <div class="tb-r">
    <div class="tds">
      <button class="td on" data-t="gold" onclick="setT('gold',this)"></button>
      <button class="td" data-t="red" onclick="setT('red',this)"></button>
      <button class="td" data-t="blue" onclick="setT('blue',this)"></button>
    </div>
    <div class="user-chip"><div class="av"><?=strtoupper(substr($name,0,2))?></div><?=htmlspecialchars($name)?></div>
    <a class="logout-btn" href="consumer_dashboard.php?logout=1">🚪 Logout</a>
  </div>
</div>

<div class="navrow">
  <button class="nl active" onclick="showP('home',this)">🏠 Overview</button>
  <button class="nl" onclick="showP('bills',this)">🧾 My Bills</button>
  <button class="nl" onclick="showP('payments',this)">💳 Payment History</button>
  <button class="nl" onclick="showP('profile',this)">👤 My Profile</button>
</div>

<div class="main">

<!-- HOME -->
<div class="page active" id="page-home">
  <div class="ph">
    <div><div class="pt">Hello, <span><?=htmlspecialchars(explode(' ',$name)[0])?></span> 👋</div>
    <div class="ps">Consumer ID: <?=htmlspecialchars($consumer['consumer_id'])?> · <?=date('l, d M Y')?></div></div>
  </div>

  <?php if($overdue_bills['c'] > 0):?>
  <div class="alert-box"><span class="ai">⚠️</span><p>You have <strong><?=$overdue_bills['c']?> overdue bill(s)</strong> totalling <strong>₹<?=number_format($overdue_bills['s'],2)?></strong>. Please pay immediately to avoid disconnection.</p></div>
  <?php elseif($pending_bills['c'] > 0):?>
  <div class="alert-box" style="background:rgba(232,162,69,.1);border-color:rgba(232,162,69,.25);"><span class="ai">📋</span><p style="color:#ffe0a0;">You have <strong><?=$pending_bills['c']?> pending bill(s)</strong> totalling <strong>₹<?=number_format($pending_bills['s'],2)?></strong>. Please pay before the due date.</p></div>
  <?php else:?>
  <div class="ok-box">✅ All your bills are paid. You're up to date!</div>
  <?php endif;?>

  <div class="sg">
    <div class="sc" style="--cc:var(--accent);"><div class="sci" style="background:var(--adim);">🧾</div><div class="scl">Total Bills</div><div class="scv"><?=$total_bills?></div></div>
    <div class="sc" style="--cc:var(--ok);"><div class="sci" style="background:rgba(76,175,130,.12);">✅</div><div class="scl">Bills Paid</div><div class="scv"><?=$paid_bills?></div></div>
    <div class="sc" style="--cc:var(--wa);"><div class="sci" style="background:rgba(232,162,69,.12);">⏳</div><div class="scl">Pending Bills</div><div class="scv"><?=$pending_bills['c']?></div></div>
    <div class="sc" style="--cc:var(--er);"><div class="sci" style="background:rgba(224,92,92,.12);">🚨</div><div class="scl">Overdue</div><div class="scv"><?=$overdue_bills['c']?></div></div>
    <div class="sc" style="--cc:var(--ok);"><div class="sci" style="background:rgba(76,175,130,.12);">💰</div><div class="scl">Total Paid</div><div class="scv">₹<?=number_format($total_paid,0)?></div></div>
    <div class="sc" style="--cc:var(--in);"><div class="sci" style="background:rgba(91,155,213,.12);">⚡</div><div class="scl">Last Reading</div><div class="scv"><?=$last_reading ? $last_reading['current_reading'].' kWh' : 'N/A'?></div></div>
  </div>

  <!-- Meter info -->
  <?php if($meter):?>
  <div class="card">
    <div class="card-h"><div class="card-ht">My <span>Meter</span></div></div>
    <div class="prow"><span class="plabel">Meter Number</span><span class="pval"><?=htmlspecialchars($meter['meter_number'])?></span></div>
    <div class="prow"><span class="plabel">Meter Type</span><span class="pval"><?=htmlspecialchars($meter['meter_type'])?></span></div>
    <div class="prow"><span class="plabel">Connection Date</span><span class="pval"><?=$meter['connection_date']?></span></div>
    <div class="prow"><span class="plabel">Status</span><span class="pval" style="color:var(--ok);"><?=$meter['status']?></span></div>
    <?php if($last_reading):?>
    <div class="prow"><span class="plabel">Last Reading</span><span class="pval"><?=$last_reading['current_reading']?> kWh (<?=$last_reading['reading_month']?>)</span></div>
    <?php endif;?>
  </div>
  <?php endif;?>

  <!-- Tariff Table -->
  <div class="card">
    <div class="card-h"><div class="card-ht">Electricity <span>Tariff Rates</span></div></div>
    <div class="tw"><table>
      <thead><tr><th>Connection Type</th><th>Units (kWh)</th><th>Rate per Unit</th></tr></thead>
      <tbody>
      <?php if($tariff_table&&$tariff_table->num_rows>0): while($t=$tariff_table->fetch_assoc()):?>
      <tr>
        <td><?=htmlspecialchars($t['category'])?></td>
        <td><?=$t['min_units']?> – <?=$t['max_units']==999999?'Above '.$t['min_units']:$t['max_units']?> units</td>
        <td style="color:var(--accent);font-weight:600;">₹<?=number_format($t['price_per_unit'],2)?>/unit</td>
      </tr>
      <?php endwhile; endif;?>
      </tbody>
    </table></div>
  </div>

  <!-- Latest bill -->
  <?php $lb=$conn->query("SELECT * FROM bills WHERE consumer_id=$uid ORDER BY generated_at DESC LIMIT 1")->fetch_assoc();
  if($lb):?>
  <div class="card">
    <div class="card-h"><div class="card-ht">Latest <span>Bill</span></div><span style="font-size:.6rem;padding:.15rem .5rem;border-radius:100px;background:rgba(<?=$lb['status']==='Paid'?'76,175,130':'224,92,92'?>,.15);color:var(--<?=$lb['status']==='Paid'?'ok':'er'?>);border:1px solid rgba(<?=$lb['status']==='Paid'?'76,175,130':'224,92,92'?>,.3);"><?=$lb['status']?></span></div>
    <div class="prow"><span class="plabel">Bill Number</span><span class="pval"><?=htmlspecialchars($lb['bill_number'])?></span></div>
    <div class="prow"><span class="plabel">Billing Month</span><span class="pval"><?=$lb['billing_month']?></span></div>
    <div class="prow"><span class="plabel">Units Consumed</span><span class="pval"><?=$lb['units_consumed']?> kWh</span></div>
    <div class="prow"><span class="plabel">Amount Due</span><span class="pval" style="color:var(--accent);font-size:1.1rem;">₹<?=number_format($lb['amount'],2)?></span></div>
    <div class="prow"><span class="plabel">Due Date</span><span class="pval"><?=$lb['due_date']?></span></div>
  </div>
  <?php endif;?>
</div>

<!-- MY BILLS -->
<div class="page" id="page-bills">
  <div class="ph"><div><div class="pt">My <span>Bills</span></div><div class="ps">All your electricity bills</div></div></div>
  <div class="card">
    <div class="tw"><table>
      <thead><tr><th>Bill No.</th><th>Month</th><th>Units</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php if($bills&&$bills->num_rows>0): while($b=$bills->fetch_assoc()):?>
      <tr><td><?=htmlspecialchars($b['bill_number'])?></td><td><?=$b['billing_month']?></td><td><?=$b['units_consumed']?> kWh</td><td>₹<?=number_format($b['amount'],2)?></td><td><?=$b['due_date']?></td><td><span class="badge <?=strtolower($b['status'])?>"><?=$b['status']?></span></td></tr>
      <?php endwhile; else:?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No bills yet</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- PAYMENT HISTORY -->
<div class="page" id="page-payments">
  <div class="ph"><div><div class="pt">Payment <span>History</span></div><div class="ps">All your payment records</div></div></div>
  <div class="card">
    <div class="tw"><table>
      <thead><tr><th>Bill Ref</th><th>Amount Paid</th><th>Payment Mode</th><th>Transaction ID</th><th>Date</th></tr></thead>
      <tbody>
      <?php if($payments&&$payments->num_rows>0): while($p=$payments->fetch_assoc()):?>
      <tr><td><?=$p['bill_number']?></td><td>₹<?=number_format($p['amount_paid'],2)?></td><td><?=$p['payment_mode']?></td><td><?=$p['transaction_id']?:'-'?></td><td><?=$p['payment_date']?></td></tr>
      <?php endwhile; else:?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem;">No payments yet</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- PROFILE -->
<div class="page" id="page-profile">
  <div class="ph"><div><div class="pt">My <span>Profile</span></div><div class="ps">Your account details</div></div></div>
  <div class="card">
    <div class="card-h"><div class="card-ht">Account <span>Details</span></div></div>
    <div class="prow"><span class="plabel">Consumer ID</span><span class="pval" style="color:var(--accent);"><?=htmlspecialchars($consumer['consumer_id'])?></span></div>
    <div class="prow"><span class="plabel">Full Name</span><span class="pval"><?=htmlspecialchars($consumer['name'])?></span></div>
    <div class="prow"><span class="plabel">Phone</span><span class="pval"><?=htmlspecialchars($consumer['phone']??'-')?></span></div>
    <div class="prow"><span class="plabel">Email</span><span class="pval"><?=htmlspecialchars($consumer['email']??'-')?></span></div>
    <div class="prow"><span class="plabel">Address</span><span class="pval"><?=htmlspecialchars($consumer['address']??'-')?></span></div>
    <div class="prow"><span class="plabel">City</span><span class="pval"><?=htmlspecialchars($consumer['city']??'-')?></span></div>
    <div class="prow"><span class="plabel">State</span><span class="pval"><?=htmlspecialchars($consumer['state']??'-')?></span></div>
    <div class="prow"><span class="plabel">Connection Type</span><span class="pval"><?=htmlspecialchars($consumer['connection_type'])?></span></div>
    <div class="prow"><span class="plabel">Registration Date</span><span class="pval"><?=$consumer['registration_date']?></span></div>
    <div class="prow"><span class="plabel">Status</span><span class="pval" style="color:var(--ok);"><?=$consumer['status']?></span></div>
  </div>
</div>

</div>
<script>
const cv=document.getElementById('c'),ctx=cv.getContext('2d');let W,H,sp=[];
const pR=()=>H*0.22,pCX=()=>-pR()*0.52,pCY=()=>H*0.50;
const gR=()=>{const pr=pR(),cx=pCX(),mx=W-cx-10,mn=pr*1.35,n=9;return Array.from({length:n},(_,i)=>{const t=i/(n-1),rx=mn+(mx-mn)*t,ry=rx*(0.13-t*0.04),w=Math.max(1,24-i*2.4),a=0.75-t*0.68;return{rx,ry,w,a};});};
function cV(v){return getComputedStyle(document.body).getPropertyValue(v).trim();}
function h2r(h){h=h.replace('#','');if(h.length===3)h=h.split('').map(c=>c+c).join('');const n=parseInt(h,16);return[(n>>16)&255,(n>>8)&255,n&255];}
function rga(h,a){const[r,g,b]=h2r(h);return`rgba(${r},${g},${b},${Math.min(Math.max(a,0),1).toFixed(3)})`;}
function spwn(){sp=[];gR().forEach((rng,ri)=>{for(let i=0;i<Math.max(8,60-ri*5);i++)sp.push({ri,angle:Math.random()*Math.PI*2,off:(Math.random()-.5)*.04,sz:.6+Math.random()*2.2,spd:.00012+Math.random()*.00022,ph:Math.random()*Math.PI*2,tw:.012+Math.random()*.035,br:.4+Math.random()*.6});});}
function rsz(){W=cv.width=window.innerWidth;H=cv.height=window.innerHeight;spwn();}
window.addEventListener('resize',rsz);rsz();
let tk=0;
function frm(){tk+=.01;ctx.clearRect(0,0,W,H);const pr=pR(),cx=pCX(),cy=pCY(),rings=gR(),ac=cV('--accent'),pa=cV('--pa'),pb=cV('--pb'),pc=cV('--pc'),pd=cV('--pd');ctx.save();ctx.translate(cx,cy);rings.forEach(r=>{const g=ctx.createLinearGradient(-r.rx,0,r.rx,0);g.addColorStop(0,rga(ac,0));g.addColorStop(.12,rga(ac,r.a*.4));g.addColorStop(.45,rga(ac,r.a*.9));g.addColorStop(.75,rga(ac,r.a*.7));g.addColorStop(1,rga(ac,r.a*.15));ctx.beginPath();ctx.ellipse(0,0,r.rx,r.ry,0,Math.PI,Math.PI*2);ctx.strokeStyle=g;ctx.lineWidth=r.w;ctx.stroke();});const hl=ctx.createRadialGradient(0,0,pr*.75,0,0,pr*2.2);hl.addColorStop(0,rga(ac,.22));hl.addColorStop(1,rga(ac,0));ctx.beginPath();ctx.arc(0,0,pr*2.2,0,Math.PI*2);ctx.fillStyle=hl;ctx.fill();const gd=ctx.createRadialGradient(pr*.18,-pr*.25,pr*.02,0,0,pr);gd.addColorStop(0,pc);gd.addColorStop(.3,pa);gd.addColorStop(.7,pb);gd.addColorStop(1,pd);ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.fillStyle=gd;ctx.fill();[[-0.4,.10],[-0.18,.08],[0.06,.09],[0.28,.07],[0.46,.08]].forEach(([y,h],i)=>{ctx.save();ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.clip();ctx.fillStyle=i%2===0?'rgba(0,0,0,0.15)':'rgba(255,255,255,0.06)';ctx.fillRect(-pr*2,y*pr,pr*4,h*pr);ctx.restore();});const spc=ctx.createRadialGradient(-pr*.22,-pr*.28,0,-pr*.15,-pr*.18,pr*.48);spc.addColorStop(0,'rgba(255,255,255,0.22)');spc.addColorStop(1,'transparent');ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.fillStyle=spc;ctx.fill();rings.forEach(r=>{const g=ctx.createLinearGradient(-r.rx,0,r.rx,0);g.addColorStop(0,rga(ac,0));g.addColorStop(.12,rga(ac,r.a*.35));g.addColorStop(.45,rga(ac,r.a*.85));g.addColorStop(.75,rga(ac,r.a*.6));g.addColorStop(1,rga(ac,r.a*.12));ctx.beginPath();ctx.ellipse(0,0,r.rx,r.ry,0,0,Math.PI);ctx.strokeStyle=g;ctx.lineWidth=r.w;ctx.stroke();});sp.forEach(s=>{s.angle+=s.spd;const r=rings[s.ri];if(!r)return;const x=Math.cos(s.angle)*r.rx,y=Math.sin(s.angle)*(r.ry+s.off*r.ry*3);if(cx+x<W+10&&cx+x>-10){const tw=.2+.8*Math.abs(Math.sin(tk*s.tw*60+s.ph)),al=Math.min(tw*s.br*r.a*3.2,1),sz=s.sz*(.4+.6*tw);const hg=ctx.createRadialGradient(x,y,0,x,y,sz*5);hg.addColorStop(0,rga(ac,al*.55));hg.addColorStop(1,rga(ac,0));ctx.beginPath();ctx.arc(x,y,sz*5,0,Math.PI*2);ctx.fillStyle=hg;ctx.fill();ctx.beginPath();ctx.arc(x,y,sz,0,Math.PI*2);ctx.fillStyle=rga(ac,al);ctx.fill();}});ctx.restore();requestAnimationFrame(frm);}frm();
function showP(id,btn){document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));document.querySelectorAll('.nl').forEach(l=>l.classList.remove('active'));document.getElementById('page-'+id).classList.add('active');if(btn)btn.classList.add('active');}
function setT(t,el){document.body.setAttribute('data-theme',t);document.querySelectorAll('.td').forEach(b=>b.classList.remove('on'));el.classList.add('on');localStorage.setItem('eis-theme',t);}
const sv=localStorage.getItem('eis-theme');if(sv){document.body.setAttribute('data-theme',sv);document.querySelectorAll('.td').forEach(b=>b.classList.toggle('on',b.dataset.t===sv));}
</script>
</body></html>