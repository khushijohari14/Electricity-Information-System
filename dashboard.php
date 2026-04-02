<?php
session_start();
require_once __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
$admin = $_SESSION['name'] ?? 'Admin';
$msg = $msg_type = '';

// ── HANDLE LOGOUT ──
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit(); }

// ── HANDLE FORMS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_consumer') {
        $cid   = 'CON-' . str_pad($conn->query("SELECT COUNT(*)+1 c FROM consumers")->fetch_assoc()['c'], 3, '0', STR_PAD_LEFT);
        $name  = $conn->real_escape_string(trim($_POST['name']));
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $addr  = $conn->real_escape_string(trim($_POST['address']));
        $city  = $conn->real_escape_string(trim($_POST['city']));
        $state = $conn->real_escape_string(trim($_POST['state']));
        $pin   = $conn->real_escape_string(trim($_POST['pincode']));
        $ctype = $conn->real_escape_string(trim($_POST['connection_type']));
        $rdate = $conn->real_escape_string(trim($_POST['registration_date']));
        $pass  = md5(trim($_POST['password']));
        $conn->query("INSERT INTO consumers (consumer_id,name,phone,email,address,city,state,pincode,connection_type,registration_date,password) VALUES ('$cid','$name','$phone','$email','$addr','$city','$state','$pin','$ctype','$rdate','$pass')");
        if ($conn->affected_rows > 0) { logActivity($conn,"Consumer $cid - $name registered",'consumer'); $msg="Consumer '$name' added! Consumer ID: $cid"; $msg_type='success'; }
        else { $msg="Error: ".$conn->error; $msg_type='error'; }
    }

    elseif ($action === 'assign_meter') {
        $cid   = intval($_POST['consumer_id']);
        $mnum  = $conn->real_escape_string(trim($_POST['meter_number']));
        $mtype = $conn->real_escape_string(trim($_POST['meter_type']));
        $cdate = $conn->real_escape_string(trim($_POST['connection_date']));
        $initr = floatval($_POST['initial_reading']);
        $conn->query("INSERT INTO meters (consumer_id,meter_number,meter_type,connection_date,initial_reading) VALUES ('$cid','$mnum','$mtype','$cdate','$initr')");
        if ($conn->affected_rows > 0) { $cn=$conn->query("SELECT name FROM consumers WHERE id=$cid")->fetch_assoc()['name']; logActivity($conn,"Meter $mnum assigned to $cn",'meter'); $msg="Meter $mnum assigned!"; $msg_type='success'; }
        else { $msg="Error: ".$conn->error; $msg_type='error'; }
    }

    elseif ($action === 'enter_reading') {
        $mid   = intval($_POST['meter_id']);
        $curr  = floatval($_POST['current_reading']);
        $rdate = $conn->real_escape_string(trim($_POST['reading_date']));
        $rmon  = $conn->real_escape_string(trim($_POST['reading_month']));
        $prev_r = $conn->query("SELECT current_reading FROM meter_readings WHERE meter_id=$mid ORDER BY id DESC LIMIT 1");
        $prev   = ($prev_r && $prev_r->num_rows>0) ? $prev_r->fetch_assoc()['current_reading'] : $conn->query("SELECT initial_reading FROM meters WHERE id=$mid")->fetch_assoc()['initial_reading'];
        $units  = round($curr - $prev, 2);
        $conn->query("INSERT INTO meter_readings (meter_id,previous_reading,current_reading,units_consumed,reading_date,reading_month) VALUES ('$mid','$prev','$curr','$units','$rdate','$rmon')");
        if ($conn->affected_rows > 0) { logActivity($conn,"Reading entered for meter ID $mid: $curr kWh ($units units)",'reading'); $msg="Reading saved! Units consumed: $units kWh"; $msg_type='success'; }
        else { $msg="Error: ".$conn->error; $msg_type='error'; }
    }

    elseif ($action === 'generate_bill') {
        $rid   = intval($_POST['reading_id']);
        $due   = $conn->real_escape_string(trim($_POST['due_date']));
        // fetch reading details
        $rdata = $conn->query("SELECT r.*,m.consumer_id as cid, m.id as mid FROM meter_readings r JOIN meters m ON r.meter_id=m.id WHERE r.id=$rid")->fetch_assoc();
        if ($rdata) {
            $cid    = $rdata['cid']; $mid = $rdata['mid']; $units = $rdata['units_consumed']; $month = $rdata['reading_month'];
            $ctype  = $conn->query("SELECT connection_type FROM consumers WHERE id=$cid")->fetch_assoc()['connection_type'];
            $tr     = $conn->query("SELECT price_per_unit FROM tariffs WHERE category='$ctype' AND min_units<=$units AND max_units>=$units LIMIT 1");
            $rate   = ($tr && $tr->num_rows>0) ? $tr->fetch_assoc()['price_per_unit'] : 5.00;
            $amount = round($units * $rate, 2);
            $bnum   = 'BILL-'.date('Y').'-'.str_pad($conn->query("SELECT COUNT(*)+1 c FROM bills")->fetch_assoc()['c'],4,'0',STR_PAD_LEFT);
            $conn->query("INSERT INTO bills (consumer_id,meter_id,reading_id,bill_number,billing_month,units_consumed,amount,due_date) VALUES ('$cid','$mid','$rid','$bnum','$month','$units','$amount','$due')");
            if ($conn->affected_rows>0) { $cn=$conn->query("SELECT name FROM consumers WHERE id=$cid")->fetch_assoc()['name']; logActivity($conn,"Bill $bnum generated for $cn — ₹$amount",'bill'); $msg="Bill $bnum generated for ₹$amount (Rate: ₹$rate/unit)"; $msg_type='success'; }
            else { $msg="Error: ".$conn->error; $msg_type='error'; }
        } else { $msg="Reading not found."; $msg_type='error'; }
    }

    elseif ($action === 'record_payment') {
        $bid   = intval($_POST['bill_id']);
        $amt   = floatval($_POST['amount_paid']);
        $pdate = $conn->real_escape_string(trim($_POST['payment_date']));
        $pmode = $conn->real_escape_string(trim($_POST['payment_mode']));
        $txn   = $conn->real_escape_string(trim($_POST['transaction_id']));
        if ($bid > 0 && $amt > 0) {
            $bill_res = $conn->query("SELECT b.consumer_id, c.name FROM bills b JOIN consumers c ON b.consumer_id=c.id WHERE b.id=$bid");
            if ($bill_res && $bill_res->num_rows > 0) {
                $bill = $bill_res->fetch_assoc();
                $cid  = $bill['consumer_id'];
                $cn   = $bill['name'];
                $conn->query("INSERT INTO payments (bill_id,consumer_id,amount_paid,payment_date,payment_mode,transaction_id) VALUES ('$bid','$cid','$amt','$pdate','$pmode','$txn')");
                if ($conn->affected_rows > 0) {
                    $conn->query("UPDATE bills SET status='Paid' WHERE id=$bid");
                    logActivity($conn,"Payment Rs.$amt recorded for $cn via $pmode",'payment');
                    $msg="Payment of Rs.$amt recorded successfully!"; $msg_type='success';
                } else { $msg="Insert error: ".$conn->error; $msg_type='error'; }
            } else { $msg="Bill ID $bid not found."; $msg_type='error'; }
        } else { $msg="Please select a bill and enter amount."; $msg_type='error'; }
    }
}

// Update overdue
$conn->query("UPDATE bills SET status='Overdue' WHERE status='Pending' AND due_date < CURDATE()");

// Stats
$total_consumers  = $conn->query("SELECT COUNT(*) c FROM consumers")->fetch_assoc()['c'];
$total_bills      = $conn->query("SELECT COUNT(*) c FROM bills")->fetch_assoc()['c'];
$total_revenue    = $conn->query("SELECT COALESCE(SUM(amount_paid),0) s FROM payments")->fetch_assoc()['s'];
$pending_amount   = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM bills WHERE status='Pending'")->fetch_assoc()['s'];
$units_month      = $conn->query("SELECT COALESCE(SUM(units_consumed),0) s FROM meter_readings WHERE MONTH(reading_date)=MONTH(CURDATE()) AND YEAR(reading_date)=YEAR(CURDATE())")->fetch_assoc()['s'];
$overdue_count    = $conn->query("SELECT COUNT(*) c FROM bills WHERE status='Overdue'")->fetch_assoc()['c'];
$bills_today      = $conn->query("SELECT COUNT(*) c FROM bills WHERE DATE(generated_at)=CURDATE()")->fetch_assoc()['c'];
$payments_today   = $conn->query("SELECT COALESCE(SUM(amount_paid),0) s FROM payments WHERE payment_date=CURDATE()")->fetch_assoc()['s'];
$unpaid_consumers = $conn->query("SELECT COUNT(DISTINCT consumer_id) c FROM bills WHERE status IN ('Pending','Overdue')")->fetch_assoc()['c'];

// Dropdowns
$all_consumers   = $conn->query("SELECT id,consumer_id,name FROM consumers ORDER BY name");
$all_meters      = $conn->query("SELECT m.id,m.meter_number,c.name,c.consumer_id FROM meters m JOIN consumers c ON m.consumer_id=c.id ORDER BY m.meter_number");
$pending_bills   = $conn->query("SELECT b.id,b.bill_number,b.amount,c.name,c.consumer_id FROM bills b JOIN consumers c ON b.consumer_id=c.id WHERE b.status IN ('Pending','Overdue') ORDER BY b.generated_at DESC");
$latest_readings = $conn->query("SELECT r.id,r.units_consumed,r.reading_month,m.id mid,m.meter_number,c.id cid,c.name,c.consumer_id FROM meter_readings r JOIN meters m ON r.meter_id=m.id JOIN consumers c ON m.consumer_id=c.id ORDER BY r.created_at DESC LIMIT 50");
$activity        = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 8");
$overdue_bills   = $conn->query("SELECT b.*,c.name,c.consumer_id FROM bills b JOIN consumers c ON b.consumer_id=c.id WHERE b.status='Overdue' ORDER BY b.due_date ASC LIMIT 6");

function human_ago($dt){$d=time()-strtotime($dt);if($d<60)return'Just now';if($d<3600)return floor($d/60).' min ago';if($d<86400)return floor($d/3600).' hr ago';return floor($d/86400).' days ago';}
function dot_class($type){return['consumer'=>'g','payment'=>'','bill'=>'b','meter'=>'w','reading'=>'b'][$type]??'';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard — EIS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
:root{--bg:#0a0a0f;--bg2:#0f0f16;--bg3:#13131c;--accent:#c9a96e;--ahi:#e8c98a;--adim:rgba(201,169,110,0.12);--aglow:rgba(201,169,110,0.28);--purple:#7c6fcd;--ivory:#f0ece2;--muted:rgba(240,236,226,0.45);--border:rgba(201,169,110,0.15);--bhi:rgba(201,169,110,0.38);--pa:#c9a96e;--pb:#7a5a10;--pc:#f0d898;--pd:#3a2a08;--ok:#4caf82;--wa:#e8a245;--er:#e05c5c;--in:#5b9bd5;}
[data-theme="red"]{--accent:#e84545;--ahi:#ff8a8a;--adim:rgba(232,69,69,0.12);--aglow:rgba(232,69,69,0.28);--border:rgba(232,69,69,0.15);--bhi:rgba(232,69,69,0.38);--pa:#e84545;--pb:#7a1010;--pc:#ffaaaa;--pd:#3a0808;}
[data-theme="blue"]{--accent:#3a9bdc;--ahi:#8ecfff;--adim:rgba(58,155,220,0.12);--aglow:rgba(58,155,220,0.28);--border:rgba(58,155,220,0.15);--bhi:rgba(58,155,220,0.38);--pa:#3a9bdc;--pb:#0e3d6e;--pc:#aaddff;--pd:#061828;}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}html,body{width:100%;height:100%;overflow:hidden;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ivory);}
#c{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;}
/* TOPBAR */
.topbar{position:fixed;top:0;left:0;right:0;z-index:200;height:54px;background:rgba(10,10,15,0.95);border-bottom:1px solid var(--border);backdrop-filter:blur(18px);display:flex;align-items:center;gap:.8rem;padding:0 1.3rem;}
.logo{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.logo-icon{width:30px;height:30px;border:1.5px solid var(--accent);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:.9rem;background:var(--adim);}
.logo-name{font-family:'Cormorant Garamond',serif;font-size:.95rem;font-weight:700;letter-spacing:.05em;}.logo-name span{color:var(--accent);}
.srch{flex:1;max-width:480px;position:relative;}
.srch input{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:100px;padding:.42rem 1rem .42rem 2.3rem;color:var(--ivory);font-family:'Inter',sans-serif;font-size:.8rem;outline:none;transition:border-color .25s,box-shadow .25s;}
.srch input::placeholder{color:var(--muted);}
.srch input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--adim);}
.srch .si{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--accent);font-size:.82rem;opacity:.65;pointer-events:none;}
.tb-r{display:flex;align-items:center;gap:.35rem;margin-left:auto;}
.ib{display:flex;align-items:center;gap:4px;background:transparent;border:1px solid var(--border);border-radius:7px;padding:.3rem .6rem;color:var(--muted);font-size:.7rem;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;white-space:nowrap;}
.ib:hover{border-color:var(--accent);color:var(--ivory);background:var(--adim);}
.ab{display:flex;align-items:center;gap:6px;background:var(--adim);border:1px solid var(--bhi);border-radius:7px;padding:.3rem .75rem;color:var(--ivory);font-size:.73rem;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;}
.av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--ahi));display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;color:#0a0a0f;}
.tds{display:flex;align-items:center;gap:5px;padding:0 .2rem;}
.td{width:12px;height:12px;border-radius:50%;border:2px solid transparent;cursor:pointer;outline:none;appearance:none;transition:transform .2s;}.td:hover{transform:scale(1.25);}.td.on{border-color:rgba(255,255,255,.8);transform:scale(1.18);}
.td[data-t=gold]{background:#c9a96e;}.td[data-t=red]{background:#e84545;}.td[data-t=blue]{background:#3a9bdc;}
.logout-btn{display:flex;align-items:center;gap:4px;background:rgba(224,92,92,.12);border:1px solid rgba(224,92,92,.3);border-radius:7px;padding:.3rem .65rem;color:#ff9191;font-size:.7rem;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;text-decoration:none;}
.logout-btn:hover{background:rgba(224,92,92,.22);}
/* NAVROW */
.navrow{position:fixed;top:54px;left:0;right:0;z-index:190;height:42px;background:rgba(8,8,13,0.97);border-bottom:1px solid rgba(255,255,255,0.04);backdrop-filter:blur(14px);display:flex;align-items:center;padding:0 1.3rem;overflow-x:auto;}
.navrow::-webkit-scrollbar{display:none;}
.nl{display:flex;align-items:center;gap:5px;padding:.42rem .9rem;color:var(--muted);font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;font-weight:500;cursor:pointer;border:none;background:transparent;font-family:'Inter',sans-serif;transition:color .2s;white-space:nowrap;position:relative;}
.nl::after{content:'';position:absolute;bottom:0;left:15%;right:15%;height:2px;background:var(--accent);border-radius:2px;transform:scaleX(0);transition:transform .25s;}
.nl:hover{color:var(--ivory);}.nl:hover::after,.nl.active::after{transform:scaleX(1);}.nl.active{color:var(--accent);}
.na{color:rgba(255,255,255,0.15);font-size:.62rem;padding:0 .05rem;flex-shrink:0;}
/* SEARCH RESULTS */
.srch-results{position:fixed;top:54px;left:250px;right:0;z-index:300;background:rgba(15,15,22,0.98);border-bottom:1px solid var(--border);padding:.8rem 1.5rem;display:none;backdrop-filter:blur(20px);}
.srch-results.show{display:block;}
.sr-title{font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:.6rem;}
.sr-item{display:flex;align-items:center;gap:.7rem;padding:.5rem .8rem;border-radius:8px;cursor:pointer;transition:background .15s;font-size:.8rem;}
.sr-item:hover{background:var(--adim);}
.sr-tag{font-size:.6rem;padding:.12rem .45rem;border-radius:4px;background:var(--adim);color:var(--accent);border:1px solid var(--border);}
/* MAIN */
.main{position:fixed;top:96px;left:0;right:0;bottom:0;overflow-y:auto;z-index:10;padding:1.5rem 1.8rem 3rem;}
.main::-webkit-scrollbar{width:4px;}.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
.page{display:none;}.page.active{display:block;}
/* flash */
.flash{padding:.65rem 1rem;border-radius:9px;margin-bottom:1.2rem;font-size:.8rem;font-weight:500;}
.flash.success{background:rgba(76,175,130,.12);border:1px solid rgba(76,175,130,.3);color:var(--ok);}
.flash.error{background:rgba(224,92,92,.12);border:1px solid rgba(224,92,92,.3);color:var(--er);}
/* page header */
.ph{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:1.4rem;}
.pt{font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:700;line-height:1;}.pt span{color:var(--accent);}
.ps{font-size:.7rem;color:var(--muted);margin-top:.28rem;letter-spacing:.04em;}
.pact{display:flex;gap:.5rem;}
.ba{padding:.45rem .95rem;background:linear-gradient(135deg,var(--accent),var(--ahi));border:none;border-radius:8px;color:#0a0a0f;font-family:'Inter',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;box-shadow:0 3px 12px var(--aglow);}
.ba:hover{opacity:.88;}
.bg{padding:.45rem .95rem;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--muted);font-family:'Inter',sans-serif;font-size:.72rem;font-weight:500;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:all .2s;}
.bg:hover{border-color:var(--accent);color:var(--ivory);background:var(--adim);}
/* today row */
.trow{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-bottom:1.3rem;}
.tc{background:rgba(15,15,22,0.8);border:1px solid var(--border);border-radius:11px;padding:.85rem 1rem;text-align:center;}
.tcl{font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem;}
.tcv{font-size:1.22rem;font-weight:600;}
.tcv.a{color:var(--accent);}.tcv.s{color:var(--ok);}.tcv.d{color:var(--er);}.tcv.w{color:var(--wa);}
/* stat cards */
.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.85rem;margin-bottom:1.5rem;}
.sc{background:rgba(15,15,22,0.82);border:1px solid var(--border);border-radius:13px;padding:1rem 1.1rem;backdrop-filter:blur(16px);position:relative;overflow:hidden;transition:transform .2s;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--cc,var(--accent)),transparent);}
.sc:hover{transform:translateY(-2px);}
.sci{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem;margin-bottom:.65rem;}
.scl{font-size:.6rem;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);margin-bottom:.22rem;}
.scv{font-size:1.48rem;font-weight:600;color:var(--ivory);line-height:1;margin-bottom:.22rem;}
.scs{font-size:.65rem;color:var(--ok);}.scs.d{color:var(--er);}.scs.w{color:var(--wa);}
/* content grid */
.cg{display:grid;grid-template-columns:1fr 330px;gap:1rem;}
@media(max-width:1050px){.cg{grid-template-columns:1fr;}}
/* card */
.card{background:rgba(15,15,22,0.82);border:1px solid var(--border);border-radius:13px;backdrop-filter:blur(16px);overflow:hidden;}
.card-h{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.2rem;border-bottom:1px solid rgba(255,255,255,.04);}
.card-ht{font-size:.75rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;}.card-ht span{color:var(--accent);}
.cbg{font-size:.6rem;padding:.15rem .5rem;border-radius:100px;background:var(--adim);border:1px solid var(--border);color:var(--accent);}
.cbg.d{color:var(--er);border-color:var(--er);background:rgba(224,92,92,.1);}
/* activity */
.al{padding:.3rem 0;}
.ai{display:flex;align-items:flex-start;gap:.75rem;padding:.6rem 1.2rem;border-bottom:1px solid rgba(255,255,255,.025);}
.ai:last-child{border:none;}.ai:hover{background:rgba(255,255,255,.02);}
.adot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px;background:var(--accent);}
.adot.g{background:var(--ok);}.adot.r{background:var(--er);}.adot.b{background:var(--in);}.adot.w{background:var(--wa);}
.at{font-size:.76rem;color:var(--ivory);line-height:1.4;}
.atm{font-size:.63rem;color:var(--muted);margin-top:.12rem;}
/* quick actions */
.qa{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;padding:.85rem 1.1rem;}
.qb{display:flex;flex-direction:column;align-items:center;gap:4px;padding:.75rem .4rem;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--muted);font-size:.63rem;letter-spacing:.07em;text-transform:uppercase;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;}
.qb:hover{border-color:var(--accent);color:var(--ahi);background:var(--adim);}
.qi{font-size:1.15rem;}
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
/* form */
.fc{background:rgba(15,15,22,0.9);border:1px solid var(--border);border-radius:14px;padding:1.7rem;backdrop-filter:blur(20px);max-width:700px;}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}
.fd{font-size:.6rem;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);margin:1rem 0 .75rem;padding-bottom:.38rem;border-bottom:1px solid rgba(255,255,255,.04);}
.field{margin:0;}
.field label{display:block;font-size:.62rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem;font-weight:500;}
.field input,.field select,.field textarea{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:.62rem .82rem;color:var(--ivory);font-family:'Inter',sans-serif;font-size:.82rem;outline:none;transition:border-color .25s,box-shadow .25s;}
.field select option{background:#13131c;color:var(--ivory);}
.field input::placeholder{color:rgba(240,236,226,.17);}
.field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--adim);}
.fa{display:flex;gap:.65rem;margin-top:1.3rem;}
.info-box{background:var(--adim);border:1px solid var(--border);border-radius:8px;padding:.6rem .9rem;font-size:.75rem;color:var(--ahi);margin-bottom:1rem;}
</style>
</head>
<body data-theme="gold">
<canvas id="c"></canvas>

<!-- TOPBAR -->
<div class="topbar">
  <div class="logo"><div class="logo-icon">⚡</div><div class="logo-name">E<span>IS</span></div></div>
  <div class="srch">
    <span class="si">🔍</span>
    <input type="text" id="searchBox" placeholder="Search by Consumer ID or Meter Number..." oninput="doSearch(this.value)" onblur="setTimeout(()=>hideSR(),200)"/>
  </div>
  <div class="tb-r">
    <div class="tds">
      <button class="td on" data-t="gold" onclick="setT('gold',this)"></button>
      <button class="td" data-t="red" onclick="setT('red',this)"></button>
      <button class="td" data-t="blue" onclick="setT('blue',this)"></button>
    </div>
    <button class="ab" onclick="togDrop('acctDrop')">
      <div class="av"><?=strtoupper(substr($admin,0,2))?></div><?=htmlspecialchars($admin)?> ▾
    </button>
    <a class="logout-btn" href="dashboard.php?logout=1">🚪 Logout</a>
  </div>
</div>

<!-- Search results panel -->
<div class="srch-results" id="srchResults">
  <div class="sr-title">Search Results</div>
  <div id="srchList"></div>
</div>

<!-- Account dropdown -->
<div style="position:fixed;top:60px;right:85px;z-index:500;background:var(--bg2);border:1px solid var(--border);border-radius:11px;box-shadow:0 16px 48px rgba(0,0,0,.65);display:none;overflow:hidden;width:185px;" id="acctDrop">
  <div style="padding:.85rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);text-align:center;">
    <div style="font-size:.85rem;font-weight:600;color:var(--ivory);"><?=htmlspecialchars($admin)?></div>
    <div style="font-size:.62rem;color:var(--accent);letter-spacing:.08em;text-transform:uppercase;">Administrator</div>
  </div>
  <a href="dashboard.php?logout=1" style="display:flex;align-items:center;gap:.55rem;padding:.62rem 1rem;font-size:.76rem;color:var(--er);cursor:pointer;text-decoration:none;">🚪 Logout</a>
</div>

<!-- NAVROW -->
<div class="navrow">
  <button class="nl active" onclick="showP('home',this)">🏠 Home</button>
  <span class="na">›</span>
  <button class="nl" onclick="showP('add-consumer',this)">👤 Add Consumer</button>
  <span class="na">›</span>
  <button class="nl" onclick="showP('assign-meter',this)">📟 Assign Meter</button>
  <span class="na">›</span>
  <button class="nl" onclick="showP('enter-reading',this)">📊 Enter Reading</button>
  <span class="na">›</span>
  <button class="nl" onclick="showP('generate-bill',this)">🧾 Generate Bill</button>
  <span class="na">›</span>
  <button class="nl" onclick="showP('record-payment',this)">💳 Record Payment</button>
</div>

<!-- MAIN -->
<div class="main">
<?php if($msg):?><div class="flash <?=$msg_type?>"><?=htmlspecialchars($msg)?></div><?php endif;?>

<!-- HOME -->
<div class="page active" id="page-home">
  <div class="ph">
    <div><div class="pt">Dashboard <span>Overview</span></div><div class="ps">Welcome, <?=htmlspecialchars($admin)?> · <?=date('l, d M Y')?></div></div>
    <div class="pact"><button class="bg" onclick="showP('add-consumer',document.querySelectorAll('.nl')[1])">+ Consumer</button><button class="ba" onclick="showP('generate-bill',document.querySelectorAll('.nl')[4])">Generate Bills</button></div>
  </div>
  <div class="trow">
    <div class="tc"><div class="tcl">Bills Today</div><div class="tcv a"><?=$bills_today?></div></div>
    <div class="tc"><div class="tcl">Revenue Today</div><div class="tcv s">₹<?=number_format($payments_today,2)?></div></div>
    <div class="tc"><div class="tcl">Overdue Bills</div><div class="tcv d"><?=$overdue_count?></div></div>
    <div class="tc"><div class="tcl">Unpaid Consumers</div><div class="tcv w"><?=$unpaid_consumers?></div></div>
  </div>
  <div class="sg">
    <div class="sc" style="--cc:var(--accent);"><div class="sci" style="background:var(--adim);">👥</div><div class="scl">Total Consumers</div><div class="scv"><?=number_format($total_consumers)?></div></div>
    <div class="sc" style="--cc:var(--in);"><div class="sci" style="background:rgba(91,155,213,.12);">🧾</div><div class="scl">Bills Generated</div><div class="scv"><?=number_format($total_bills)?></div></div>
    <div class="sc" style="--cc:var(--ok);"><div class="sci" style="background:rgba(76,175,130,.12);">💰</div><div class="scl">Total Revenue</div><div class="scv">₹<?=number_format($total_revenue,0)?></div></div>
    <div class="sc" style="--cc:var(--wa);"><div class="sci" style="background:rgba(232,162,69,.12);">⏳</div><div class="scl">Pending Amount</div><div class="scv">₹<?=number_format($pending_amount,0)?></div></div>
    <div class="sc" style="--cc:var(--in);"><div class="sci" style="background:rgba(91,155,213,.12);">⚡</div><div class="scl">Units This Month</div><div class="scv"><?=number_format($units_month,0)?></div><div class="scs">kWh</div></div>
    <div class="sc" style="--cc:var(--er);"><div class="sci" style="background:rgba(224,92,92,.12);">🚨</div><div class="scl">Overdue Bills</div><div class="scv"><?=$overdue_count?></div><div class="scs d">Needs attention</div></div>
  </div>
  <div class="cg">
    <div class="card">
      <div class="card-h"><div class="card-ht">Recent <span>Activity</span></div><span class="cbg">Live</span></div>
      <div class="al">
        <?php if($activity&&$activity->num_rows>0): while($a=$activity->fetch_assoc()):?>
        <div class="ai"><div class="adot <?=dot_class($a['type'])?>"></div><div><div class="at"><?=htmlspecialchars($a['action'])?></div><div class="atm"><?=human_ago($a['created_at'])?></div></div></div>
        <?php endwhile; else:?><div class="ai"><div class="at" style="color:var(--muted);">No activity yet. Start by adding a consumer!</div></div><?php endif;?>
      </div>
    </div>
    <div>
      <div class="card" style="margin-bottom:.9rem;">
        <div class="card-h"><div class="card-ht">Quick <span>Actions</span></div></div>
        <div class="qa">
          <button class="qb" onclick="showP('add-consumer',document.querySelectorAll('.nl')[1])"><span class="qi">👤</span>Add Consumer</button>
          <button class="qb" onclick="showP('assign-meter',document.querySelectorAll('.nl')[2])"><span class="qi">📟</span>Assign Meter</button>
          <button class="qb" onclick="showP('enter-reading',document.querySelectorAll('.nl')[3])"><span class="qi">📊</span>Enter Reading</button>
          <button class="qb" onclick="showP('generate-bill',document.querySelectorAll('.nl')[4])"><span class="qi">🧾</span>Gen. Bill</button>
          <button class="qb" onclick="showP('record-payment',document.querySelectorAll('.nl')[5])"><span class="qi">💳</span>Payment</button>
          <button class="qb"><span class="qi">📥</span>Export</button>
        </div>
      </div>
      <div class="card">
        <div class="card-h"><div class="card-ht">Overdue <span>Bills</span></div><span class="cbg d"><?=$overdue_count?> Pending</span></div>
        <div class="tw"><table><thead><tr><th>Consumer ID</th><th>Name</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php if($overdue_bills&&$overdue_bills->num_rows>0): while($ob=$overdue_bills->fetch_assoc()):?>
        <tr><td><?=htmlspecialchars($ob['consumer_id'])?></td><td><?=htmlspecialchars($ob['name'])?></td><td>₹<?=number_format($ob['amount'],2)?></td><td><span class="badge overdue">Overdue</span></td></tr>
        <?php endwhile; else:?><tr><td colspan="4" style="text-align:center;color:var(--muted);">No overdue bills</td></tr><?php endif;?>
        </tbody></table></div>
      </div>
    </div>
  </div>
</div>

<!-- ADD CONSUMER -->
<div class="page" id="page-add-consumer">
  <div class="ph"><div><div class="pt">Add <span>Consumer</span></div><div class="ps">Consumer ID will be auto-generated (e.g. CON-001)</div></div></div>
  <div class="info-box">ℹ️ Consumer ID is auto-generated. Share it with the consumer for login.</div>
  <div class="fc">
    <form method="POST">
      <input type="hidden" name="action" value="add_consumer"/>
      <div class="fd">Personal Information</div>
      <div class="fg">
        <div class="field"><label>Full Name *</label><input type="text" name="name" placeholder="Full name" required/></div>
        <div class="field"><label>Phone *</label><input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required/></div>
        <div class="field"><label>Email</label><input type="email" name="email" placeholder="email@example.com"/></div>
        <div class="field"><label>Aadhaar / ID</label><input type="text" name="aadhaar" placeholder="XXXX XXXX XXXX"/></div>
      </div>
      <div class="fd">Address</div>
      <div class="fg">
        <div class="field"><label>Address</label><input type="text" name="address" placeholder="House / Street"/></div>
        <div class="field"><label>City</label><input type="text" name="city" placeholder="City"/></div>
        <div class="field"><label>State</label><input type="text" name="state" placeholder="State"/></div>
        <div class="field"><label>Pin Code</label><input type="text" name="pincode" placeholder="XXXXXX"/></div>
      </div>
      <div class="fd">Connection &amp; Login</div>
      <div class="fg">
        <div class="field"><label>Connection Type *</label><select name="connection_type"><option>Residential</option><option>Commercial</option><option>Industrial</option></select></div>
        <div class="field"><label>Registration Date *</label><input type="date" name="registration_date" value="<?=date('Y-m-d')?>" required/></div>
        <div class="field"><label>Consumer Password *</label><input type="password" name="password" placeholder="Set login password" required/></div>
      </div>
      <div class="fa"><button type="submit" class="ba">Register Consumer →</button><button type="reset" class="bg">Clear</button></div>
    </form>
  </div>
</div>

<!-- ASSIGN METER -->
<div class="page" id="page-assign-meter">
  <div class="ph"><div><div class="pt">Assign <span>Meter</span></div><div class="ps">Link a meter to a registered consumer</div></div></div>
  <div class="fc">
    <form method="POST">
      <input type="hidden" name="action" value="assign_meter"/>
      <div class="fd">Select Consumer &amp; Meter Details</div>
      <div class="fg">
        <div class="field"><label>Select Consumer *</label>
          <select name="consumer_id" required>
            <option value="">-- Select Consumer --</option>
            <?php if($all_consumers){$all_consumers->data_seek(0);while($c=$all_consumers->fetch_assoc()):?>
            <option value="<?=$c['id']?>"><?=htmlspecialchars($c['consumer_id'])?> — <?=htmlspecialchars($c['name'])?></option>
            <?php endwhile;}?>
          </select>
        </div>
        <div class="field"><label>Meter Number *</label><input type="text" name="meter_number" placeholder="e.g. M-4430" required/></div>
        <div class="field"><label>Meter Type *</label><select name="meter_type"><option>Single Phase</option><option>Three Phase</option><option>Smart Meter</option></select></div>
        <div class="field"><label>Connection Date *</label><input type="date" name="connection_date" value="<?=date('Y-m-d')?>" required/></div>
        <div class="field"><label>Initial Reading (kWh)</label><input type="number" name="initial_reading" value="0" step="0.01"/></div>
      </div>
      <div class="fa"><button type="submit" class="ba">Assign Meter →</button><button type="reset" class="bg">Clear</button></div>
    </form>
  </div>
</div>

<!-- ENTER READING -->
<div class="page" id="page-enter-reading">
  <div class="ph"><div><div class="pt">Enter <span>Reading</span></div><div class="ps">Record monthly meter readings</div></div></div>
  <div class="fc">
    <form method="POST">
      <input type="hidden" name="action" value="enter_reading"/>
      <div class="fd">Meter Reading Entry</div>
      <div class="fg">
        <div class="field"><label>Select Meter *</label>
          <select name="meter_id" required>
            <option value="">-- Select Meter --</option>
            <?php if($all_meters){$all_meters->data_seek(0);while($m=$all_meters->fetch_assoc()):?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['meter_number'])?> — <?=htmlspecialchars($m['consumer_id'])?> (<?=htmlspecialchars($m['name'])?>)</option>
            <?php endwhile;}?>
          </select>
        </div>
        <div class="field"><label>Reading Month *</label>
          <select name="reading_month">
            <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $mn):?>
            <option <?=$mn==date('F')?'selected':''?>><?=$mn?> <?=date('Y')?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="field"><label>Current Reading (kWh) *</label><input type="number" name="current_reading" placeholder="Enter current reading" step="0.01" required/></div>
        <div class="field"><label>Reading Date *</label><input type="date" name="reading_date" value="<?=date('Y-m-d')?>" required/></div>
      </div>
      <div class="fa"><button type="submit" class="ba">Save Reading →</button><button type="reset" class="bg">Clear</button></div>
    </form>
  </div>
</div>

<!-- GENERATE BILL -->
<div class="page" id="page-generate-bill">
  <div class="ph"><div><div class="pt">Generate <span>Bill</span></div><div class="ps">Bills are calculated automatically based on tariff rates</div></div></div>
  <div class="info-box">💡 Tariff: Residential 0-100 units @₹3.50 | 101-300 @₹5.00 | 300+ @₹7.50 | Commercial @₹9.00 | Industrial @₹6.50</div>
  <div class="fc" style="margin-bottom:1.2rem;">
    <form method="POST">
      <input type="hidden" name="action" value="generate_bill"/>
      <div class="fd">Select Reading to Generate Bill</div>
      <div class="fg">
        <div class="field"><label>Select Reading *</label>
          <select name="reading_id" required>
            <option value="">-- Select Reading --</option>
            <?php if($latest_readings){$latest_readings->data_seek(0);while($r=$latest_readings->fetch_assoc()):?>
            <option value="<?=$r['id']?>"><?=htmlspecialchars($r['consumer_id'])?> — <?=htmlspecialchars($r['name'])?> — <?=$r['meter_number']?> — <?=$r['units_consumed']?> kWh (<?=$r['reading_month']?>)</option>
            <?php endwhile;}?>
          </select>
        </div>
        <div class="field"><label>Due Date *</label><input type="date" name="due_date" value="<?=date('Y-m-d',strtotime('+30 days'))?>" required/></div>
      </div>
      <div class="fa"><button type="submit" class="ba">Generate Bill →</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-h"><div class="card-ht">All <span>Bills</span></div></div>
    <div class="tw"><table><thead><tr><th>Bill No.</th><th>Consumer ID</th><th>Name</th><th>Month</th><th>Units</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead><tbody>
    <?php $rb=$conn->query("SELECT b.*,c.name,c.consumer_id FROM bills b JOIN consumers c ON b.consumer_id=c.id ORDER BY b.generated_at DESC LIMIT 15");
    if($rb&&$rb->num_rows>0): while($r=$rb->fetch_assoc()):?>
    <tr><td><?=htmlspecialchars($r['bill_number'])?></td><td><?=htmlspecialchars($r['consumer_id'])?></td><td><?=htmlspecialchars($r['name'])?></td><td><?=$r['billing_month']?></td><td><?=$r['units_consumed']?> kWh</td><td>₹<?=number_format($r['amount'],2)?></td><td><?=$r['due_date']?></td><td><span class="badge <?=strtolower($r['status'])?>"><?=$r['status']?></span></td></tr>
    <?php endwhile; else:?><tr><td colspan="8" style="text-align:center;color:var(--muted);">No bills yet</td></tr><?php endif;?>
    </tbody></table></div>
  </div>
</div>

<!-- RECORD PAYMENT -->
<div class="page" id="page-record-payment">
  <div class="ph"><div><div class="pt">Record <span>Payment</span></div><div class="ps">Log bill payments from consumers</div></div></div>
  <div class="fc" style="margin-bottom:1.2rem;">
    <form method="POST">
      <input type="hidden" name="action" value="record_payment"/>
      <div class="fd">Payment Details</div>
      <div class="fg">
        <div class="field"><label>Select Pending Bill *</label>
          <select name="bill_id" id="billSel" onchange="fillPay(this)" required>
            <option value="">-- Select Bill --</option>
            <?php if($pending_bills){$pending_bills->data_seek(0);while($pb=$pending_bills->fetch_assoc()):?>
            <option value="<?=$pb['id']?>" data-amt="<?=$pb['amount']?>"><?=htmlspecialchars($pb['bill_number'])?> — <?=htmlspecialchars($pb['consumer_id'])?> (<?=htmlspecialchars($pb['name'])?>) — ₹<?=number_format($pb['amount'],2)?></option>
            <?php endwhile;}?>
          </select>
        </div>
        <div class="field"><label>Amount Paid (₹) *</label><input type="number" name="amount_paid" id="payAmt" placeholder="0.00" step="0.01" required/></div>
        <div class="field"><label>Payment Date *</label><input type="date" name="payment_date" value="<?=date('Y-m-d')?>" required/></div>
        <div class="field"><label>Payment Mode *</label><select name="payment_mode"><option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>Cheque</option><option>Online Portal</option></select></div>
        <div class="field"><label>Transaction ID</label><input type="text" name="transaction_id" placeholder="Optional"/></div>
      </div>
      <div class="fa"><button type="submit" class="ba">Record Payment →</button><button type="reset" class="bg">Clear</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-h"><div class="card-ht">Recent <span>Payments</span></div></div>
    <div class="tw"><table><thead><tr><th>Consumer ID</th><th>Name</th><th>Bill Ref</th><th>Amount</th><th>Mode</th><th>Date</th></tr></thead><tbody>
    <?php $rp=$conn->query("SELECT p.*,c.name,c.consumer_id,b.bill_number FROM payments p JOIN consumers c ON p.consumer_id=c.id JOIN bills b ON p.bill_id=b.id ORDER BY p.created_at DESC LIMIT 10");
    if($rp&&$rp->num_rows>0): while($r=$rp->fetch_assoc()):?>
    <tr><td><?=htmlspecialchars($r['consumer_id'])?></td><td><?=htmlspecialchars($r['name'])?></td><td><?=$r['bill_number']?></td><td>₹<?=number_format($r['amount_paid'],2)?></td><td><?=$r['payment_mode']?></td><td><?=$r['payment_date']?></td></tr>
    <?php endwhile; else:?><tr><td colspan="6" style="text-align:center;color:var(--muted);">No payments yet</td></tr><?php endif;?>
    </tbody></table></div>
  </div>
</div>

</div><!-- /main -->

<script>
// Saturn
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

// UI
function showP(id,btn){document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));document.querySelectorAll('.nl').forEach(l=>l.classList.remove('active'));document.getElementById('page-'+id).classList.add('active');if(btn)btn.classList.add('active');closeDrops();}
function setT(t,el){document.body.setAttribute('data-theme',t);document.querySelectorAll('.td').forEach(b=>b.classList.remove('on'));el.classList.add('on');localStorage.setItem('eis-theme',t);}
const sv=localStorage.getItem('eis-theme');if(sv){document.body.setAttribute('data-theme',sv);document.querySelectorAll('.td').forEach(b=>b.classList.toggle('on',b.dataset.t===sv));}
function togDrop(id){const el=document.getElementById(id);el.style.display=el.style.display==='block'?'none':'block';}
function closeDrops(){document.querySelectorAll('[id$="Drop"]').forEach(d=>d.style.display='none');}
document.addEventListener('click',e=>{if(!e.target.closest('[id$="Drop"]')&&!e.target.closest('.ab'))closeDrops();});
function fillPay(sel){const o=sel.options[sel.selectedIndex];document.getElementById('payAmt').value=o.dataset.amt||'';}

// Search by Consumer ID or Meter Number
function doSearch(v){
  v=v.trim();
  if(!v){hideSR();return;}
  fetch('search.php?q='+encodeURIComponent(v))
    .then(r=>r.json()).then(data=>{
      const list=document.getElementById('srchList');
      if(!data.length){list.innerHTML='<div style="padding:.5rem .8rem;font-size:.77rem;color:var(--muted);">No results found</div>';document.getElementById('srchResults').classList.add('show');return;}
      list.innerHTML=data.map(d=>`<div class="sr-item" onclick="showP('${d.page}',document.querySelectorAll('.nl')[${d.nav}])"><span class="sr-tag">${d.type}</span>${d.label}</div>`).join('');
      document.getElementById('srchResults').classList.add('show');
    }).catch(()=>{
      // fallback if search.php not available
      hideSR();
    });
}
function hideSR(){document.getElementById('srchResults').classList.remove('show');}
</script>
</body></html>