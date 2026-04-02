<?php
session_start();
require_once __DIR__ . '/includes/db.php';
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = md5(trim($_POST['password'] ?? ''));
    $role = $_POST['role'] ?? 'admin';
    $u_esc = $conn->real_escape_string($u);
    if ($role === 'admin') {
        $r = $conn->query("SELECT * FROM admins WHERE username='$u_esc' AND password='$p'");
        if ($r && $r->num_rows > 0) {
            $row = $r->fetch_assoc();
            $_SESSION['user'] = $row['username']; $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = 'admin'; $_SESSION['user_id'] = $row['id'];
            header("Location: dashboard.php"); exit();
        } else { $error = "Invalid admin username or password."; }
    } else {
        $r = $conn->query("SELECT * FROM consumers WHERE consumer_id='$u_esc' AND password='$p' AND status='Active'");
        if ($r && $r->num_rows > 0) {
            $row = $r->fetch_assoc();
            $_SESSION['user'] = $row['consumer_id']; $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = 'consumer'; $_SESSION['user_id'] = $row['id'];
            header("Location: consumer.php"); exit();
        } else { $error = "Invalid Consumer ID or password."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login — EIS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
:root{--bg:#0a0a0f;--bgc:#0f0f16;--bgi:#13131c;--accent:#c9a96e;--ahi:#e8c98a;--adim:rgba(201,169,110,0.13);--aglow:rgba(201,169,110,0.30);--purple:#7c6fcd;--ivory:#f0ece2;--muted:rgba(240,236,226,0.45);--border:rgba(201,169,110,0.18);--bhi:rgba(201,169,110,0.45);--pa:#c9a96e;--pb:#7a5a10;--pc:#f0d898;--pd:#3a2a08;}
[data-theme="red"]{--accent:#e84545;--ahi:#ff8a8a;--adim:rgba(232,69,69,0.13);--aglow:rgba(232,69,69,0.30);--border:rgba(232,69,69,0.18);--bhi:rgba(232,69,69,0.45);--pa:#e84545;--pb:#7a1010;--pc:#ffaaaa;--pd:#3a0808;}
[data-theme="blue"]{--accent:#3a9bdc;--ahi:#8ecfff;--adim:rgba(58,155,220,0.13);--aglow:rgba(58,155,220,0.30);--border:rgba(58,155,220,0.18);--bhi:rgba(58,155,220,0.45);--pa:#3a9bdc;--pb:#0e3d6e;--pc:#aaddff;--pd:#061828;}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}html,body{width:100%;height:100%;overflow:hidden;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ivory);display:flex;align-items:center;justify-content:center;}
#c{position:fixed;top:0;left:0;width:100%;height:100%;z-index:1;pointer-events:none;}
.brand{position:fixed;top:1.4rem;left:1.8rem;z-index:100;display:flex;align-items:center;gap:10px;}
.brand-icon{width:34px;height:34px;border:1.5px solid var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:1rem;background:var(--adim);}
.brand-name{font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:600;letter-spacing:.06em;}.brand-name span{color:var(--accent);}
.tdock{position:fixed;top:1.4rem;right:1.8rem;z-index:100;display:flex;align-items:center;gap:9px;background:rgba(15,15,22,.82);border:1px solid var(--border);border-radius:100px;padding:.42rem .85rem;backdrop-filter:blur(16px);}
.tdock span{font-size:.6rem;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-right:2px;}
.tb{width:15px;height:15px;border-radius:50%;border:2px solid transparent;cursor:pointer;outline:none;appearance:none;transition:transform .2s;}.tb:hover{transform:scale(1.25);}.tb.on{border-color:rgba(255,255,255,.8);transform:scale(1.2);}
.tb[data-t=gold]{background:#c9a96e;}.tb[data-t=red]{background:#e84545;}.tb[data-t=blue]{background:#3a9bdc;}
.wrap{position:relative;z-index:10;width:100%;max-width:415px;padding:1rem;animation:up .7s cubic-bezier(.16,1,.3,1) both;}
@keyframes up{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:none;}}
.card{background:var(--bgc);border:1px solid var(--border);border-radius:20px;padding:2.6rem 2.4rem 2.2rem;box-shadow:0 28px 56px rgba(0,0,0,.7),0 0 55px var(--adim);backdrop-filter:blur(28px);position:relative;overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:10%;right:10%;height:1.5px;background:linear-gradient(90deg,transparent,var(--accent),transparent);}
.card::after{content:'';position:absolute;top:-55px;right:-55px;width:170px;height:170px;background:radial-gradient(circle,var(--adim),transparent 70%);pointer-events:none;}
.ch{text-align:center;margin-bottom:1.9rem;}
.ci{width:50px;height:50px;margin:0 auto .9rem;border:1.5px solid var(--bhi);border-radius:13px;display:flex;align-items:center;justify-content:center;background:var(--adim);font-size:1.35rem;}
.ct{font-family:'Cormorant Garamond',serif;font-size:1.7rem;font-weight:700;color:var(--ivory);margin-bottom:.28rem;}
.cs{font-size:.72rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;}
.cd{width:38px;height:1.5px;background:linear-gradient(90deg,transparent,var(--accent),transparent);margin:.7rem auto 0;border-radius:100px;}
.rg{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:1.3rem;}
.rb{display:flex;flex-direction:column;align-items:center;gap:4px;padding:.7rem .4rem;background:var(--bgi);border:1px solid var(--border);border-radius:10px;color:var(--muted);font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;}
.rb .ri{font-size:1.15rem;}.rb.on,.rb:hover{border-color:var(--accent);color:var(--ahi);background:var(--adim);}
.field{margin-bottom:1.1rem;}
.field label{display:block;font-size:.65rem;letter-spacing:.13em;text-transform:uppercase;color:var(--muted);margin-bottom:.45rem;font-weight:500;}
.iw{position:relative;}
.iw .ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--accent);font-size:.88rem;opacity:.65;pointer-events:none;}
.iw input{width:100%;background:var(--bgi);border:1px solid var(--border);border-radius:10px;padding:.75rem 1rem .75rem 2.5rem;color:var(--ivory);font-family:'Inter',sans-serif;font-size:.87rem;outline:none;transition:border-color .25s,box-shadow .25s;}
.iw input::placeholder{color:rgba(240,236,226,.17);}
.iw input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--adim);}
.hint{font-size:.63rem;color:var(--muted);margin-top:.28rem;padding-left:.2rem;}
.err{display:flex;align-items:center;gap:7px;background:rgba(224,92,92,.1);border:1px solid rgba(224,92,92,.3);border-radius:8px;color:#ff9191;font-size:.77rem;padding:.55rem .85rem;margin-bottom:1rem;}
.btn{width:100%;padding:.85rem;background:linear-gradient(135deg,var(--accent),var(--ahi));border:none;border-radius:10px;color:#0a0a0f;font-family:'Inter',sans-serif;font-size:.82rem;font-weight:600;letter-spacing:.13em;text-transform:uppercase;cursor:pointer;transition:opacity .2s,transform .15s;box-shadow:0 4px 22px var(--aglow);margin-top:.25rem;}
.btn:hover{opacity:.88;transform:translateY(-1px);}
.cf{text-align:center;margin-top:1.4rem;font-size:.68rem;color:var(--muted);border-top:1px solid rgba(255,255,255,.05);padding-top:1rem;}
.cf span{color:var(--purple);}
</style>
</head>
<body data-theme="gold">
<canvas id="c"></canvas>
<div class="brand"><div class="brand-icon">⚡</div><div class="brand-name">E<span>IS</span></div></div>
<div class="tdock"><span>Theme</span>
  <button class="tb on" data-t="gold" onclick="setT('gold',this)"></button>
  <button class="tb" data-t="red" onclick="setT('red',this)"></button>
  <button class="tb" data-t="blue" onclick="setT('blue',this)"></button>
</div>
<div class="wrap"><div class="card">
  <div class="ch"><div class="ci">⚡</div><div class="ct">Welcome Back</div><div class="cs">Electricity Information System</div><div class="cd"></div></div>
  <div class="rg">
    <button type="button" class="rb on" id="ra" onclick="selR('admin')"><span class="ri">🛡️</span>Admin</button>
    <button type="button" class="rb" id="rc" onclick="selR('consumer')"><span class="ri">👤</span>Consumer</button>
  </div>
  <?php if($error):?><div class="err">⚠ <?=htmlspecialchars($error)?></div><?php endif;?>
  <form method="POST" action="login.php">
    <input type="hidden" name="role" id="role_input" value="admin"/>
    <div class="field">
      <label id="uLabel">Username</label>
      <div class="iw"><span class="ico">👤</span>
        <input type="text" name="username" id="uInput" placeholder="Admin username" value="<?=htmlspecialchars($_POST['username']??'')?>" required/>
      </div>
      <div class="hint" id="uHint">Use your admin username</div>
    </div>
    <div class="field"><label>Password</label>
      <div class="iw"><span class="ico">🔒</span>
        <input type="password" name="password" placeholder="Enter password" required/>
      </div>
    </div>
    <button type="submit" class="btn">Sign In →</button>
  </form>
  <div class="cf">Secure access · <span>Electricity Dept.</span> · <?=date('Y')?></div>
</div></div>
<script>
const cv=document.getElementById('c'),ctx=cv.getContext('2d');let W,H,sparks=[];
const pR=()=>H*0.22,pCX=()=>-pR()*0.52,pCY=()=>H*0.50;
const gR=()=>{const pr=pR(),cx=pCX(),mxRX=W-cx-10,mnRX=pr*1.35,n=9;return Array.from({length:n},(_,i)=>{const t=i/(n-1),rx=mnRX+(mxRX-mnRX)*t,ry=rx*(0.13-t*0.04),w=Math.max(1,24-i*2.4),a=0.75-t*0.68;return{rx,ry,w,a};});};
function cV(v){return getComputedStyle(document.body).getPropertyValue(v).trim();}
function h2r(h){h=h.replace('#','');if(h.length===3)h=h.split('').map(c=>c+c).join('');const n=parseInt(h,16);return[(n>>16)&255,(n>>8)&255,n&255];}
function rga(h,a){const[r,g,b]=h2r(h);return`rgba(${r},${g},${b},${Math.min(Math.max(a,0),1).toFixed(3)})`;}
function spwn(){sparks=[];gR().forEach((rng,ri)=>{for(let i=0;i<Math.max(8,60-ri*5);i++)sparks.push({ri,angle:Math.random()*Math.PI*2,off:(Math.random()-.5)*.04,sz:.6+Math.random()*2.2,sp:.00012+Math.random()*.00022,ph:Math.random()*Math.PI*2,tw:.012+Math.random()*.035,br:.4+Math.random()*.6});});}
function rsz(){W=cv.width=window.innerWidth;H=cv.height=window.innerHeight;spwn();}
window.addEventListener('resize',rsz);rsz();
let tk=0;
function frm(){tk+=.01;ctx.clearRect(0,0,W,H);const pr=pR(),cx=pCX(),cy=pCY(),rings=gR(),ac=cV('--accent'),pa=cV('--pa'),pb=cV('--pb'),pc=cV('--pc'),pd=cV('--pd');ctx.save();ctx.translate(cx,cy);rings.forEach(r=>{const g=ctx.createLinearGradient(-r.rx,0,r.rx,0);g.addColorStop(0,rga(ac,0));g.addColorStop(.12,rga(ac,r.a*.4));g.addColorStop(.45,rga(ac,r.a*.9));g.addColorStop(.75,rga(ac,r.a*.7));g.addColorStop(1,rga(ac,r.a*.15));ctx.beginPath();ctx.ellipse(0,0,r.rx,r.ry,0,Math.PI,Math.PI*2);ctx.strokeStyle=g;ctx.lineWidth=r.w;ctx.stroke();});const hl=ctx.createRadialGradient(0,0,pr*.75,0,0,pr*2.2);hl.addColorStop(0,rga(ac,.22));hl.addColorStop(1,rga(ac,0));ctx.beginPath();ctx.arc(0,0,pr*2.2,0,Math.PI*2);ctx.fillStyle=hl;ctx.fill();const gd=ctx.createRadialGradient(pr*.18,-pr*.25,pr*.02,0,0,pr);gd.addColorStop(0,pc);gd.addColorStop(.3,pa);gd.addColorStop(.7,pb);gd.addColorStop(1,pd);ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.fillStyle=gd;ctx.fill();[[-0.4,.10],[-0.18,.08],[0.06,.09],[0.28,.07],[0.46,.08]].forEach(([y,h],i)=>{ctx.save();ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.clip();ctx.fillStyle=i%2===0?'rgba(0,0,0,0.15)':'rgba(255,255,255,0.06)';ctx.fillRect(-pr*2,y*pr,pr*4,h*pr);ctx.restore();});const sp=ctx.createRadialGradient(-pr*.22,-pr*.28,0,-pr*.15,-pr*.18,pr*.48);sp.addColorStop(0,'rgba(255,255,255,0.22)');sp.addColorStop(1,'transparent');ctx.beginPath();ctx.arc(0,0,pr,0,Math.PI*2);ctx.fillStyle=sp;ctx.fill();rings.forEach(r=>{const g=ctx.createLinearGradient(-r.rx,0,r.rx,0);g.addColorStop(0,rga(ac,0));g.addColorStop(.12,rga(ac,r.a*.35));g.addColorStop(.45,rga(ac,r.a*.85));g.addColorStop(.75,rga(ac,r.a*.6));g.addColorStop(1,rga(ac,r.a*.12));ctx.beginPath();ctx.ellipse(0,0,r.rx,r.ry,0,0,Math.PI);ctx.strokeStyle=g;ctx.lineWidth=r.w;ctx.stroke();});sparks.forEach(s=>{s.angle+=s.sp;const r=rings[s.ri];if(!r)return;const x=Math.cos(s.angle)*r.rx,y=Math.sin(s.angle)*(r.ry+s.off*r.ry*3);if(cx+x<W+10&&cx+x>-10){const tw=.2+.8*Math.abs(Math.sin(tk*s.tw*60+s.ph)),al=Math.min(tw*s.br*r.a*3.2,1),sz=s.sz*(.4+.6*tw);const hg=ctx.createRadialGradient(x,y,0,x,y,sz*5);hg.addColorStop(0,rga(ac,al*.55));hg.addColorStop(1,rga(ac,0));ctx.beginPath();ctx.arc(x,y,sz*5,0,Math.PI*2);ctx.fillStyle=hg;ctx.fill();ctx.beginPath();ctx.arc(x,y,sz,0,Math.PI*2);ctx.fillStyle=rga(ac,al);ctx.fill();}});ctx.restore();requestAnimationFrame(frm);}frm();
function setT(t,el){document.body.setAttribute('data-theme',t);document.querySelectorAll('.tb').forEach(b=>b.classList.remove('on'));el.classList.add('on');localStorage.setItem('eis-theme',t);}
const sv=localStorage.getItem('eis-theme');if(sv){document.body.setAttribute('data-theme',sv);document.querySelectorAll('.tb').forEach(b=>b.classList.toggle('on',b.dataset.t===sv));}
function selR(r){document.getElementById('role_input').value=r;document.getElementById('ra').classList.toggle('on',r==='admin');document.getElementById('rc').classList.toggle('on',r==='consumer');document.getElementById('uLabel').textContent=r==='admin'?'Username':'Consumer ID';document.getElementById('uInput').placeholder=r==='admin'?'Admin username':'e.g. CON-001';document.getElementById('uHint').textContent=r==='admin'?'Use your admin username':'Use your Consumer ID given by admin';}
</script>
</body></html>