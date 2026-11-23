/* YEWORK 0.5.12 — живий перегляд банера в конструкторі (кабінет роботодавця й адмінка). */
(function(){var f=document.getElementById('bnrLive')&&document.getElementById('bnrLive').closest('form'),b=document.getElementById('bnrLive');if(!f||!b)return;
var q=function(s){return b.querySelector(s)},h3=q('h3'),p=q('p'),ul=q('.bnr-perks'),bd=q('.bnr-badge'),cta=q('.bnr-cta>span'),ph=q('.bnr-photo'),im=q('.bnr-img'),req=document.getElementById('adImgReq');
var E=f.elements,ex={title:h3.textContent,body:p.textContent,cta:cta.textContent},exPerks=f.querySelectorAll('[name="perks[]"]'),url='',cur=ph.getAttribute('src')||'';
function tpl(){var r=f.querySelector('input[name=template]:checked');return r?r.value:'classic'}
function upd(){var t=tpl();b.className=b.className.replace(/\bt-\w+/,'t-'+t);
h3.textContent=E.title.value.trim()||ex.title;p.textContent=E.body.value.trim()||ex.body;
var ps=[].map.call(exPerks,function(i){return i.value.trim()}).filter(Boolean),any=[].some.call(exPerks,function(i){return i.value.trim()});
if(!any&&!f.hasAttribute("data-edit"))ps=[].map.call(exPerks,function(i){return i.placeholder});ul.innerHTML='';ps.forEach(function(x){var li=document.createElement('li');li.textContent=x;ul.appendChild(li)});ul.hidden=!ps.length;
var bv=E.badge.value.trim();bd.hidden=!bv;bd.querySelector('span').textContent=bv;
cta.textContent=E.cta.value.trim()||E.cta.placeholder||ex.cta;
var u=url||(E.drop_image&&E.drop_image.checked?'':cur);ph.hidden=!u;im.hidden=!u||t==='photo';if(u){ph.src=u;im.src=u}
req.textContent=t==='photo'?'(обов’язково для «Фото»)':'(необов’язково)';}
E.image.addEventListener('change',function(){if(url)URL.revokeObjectURL(url);var x=E.image.files&&E.image.files[0];url=x&&/^image\//.test(x.type)?URL.createObjectURL(x):'';upd()});
f.addEventListener('input',upd);f.addEventListener('change',upd);upd();})();

/* Куди веде кнопка (0.5.15): у адмінці показуємо лише вакансії обраного роботодавця; текст кнопки за замовчуванням залежить від вибору. */
(function(){var t=document.getElementById('f_target'),c=document.getElementById('f_cta'),s=document.getElementById('f_employer_id');if(!t)return;
function upd(){if(s){var emp=s.value,o;for(var i=0;i<t.options.length;i++){o=t.options[i];if(!o.dataset.emp)continue;var off=o.dataset.emp!==emp;o.hidden=off;o.disabled=off;if(off&&o.selected)t.value=''}}
if(c){var all=c.dataset.all||c.placeholder;c.placeholder=/^\d+$/.test(t.value)?'Дивитися вакансію':all;c.dispatchEvent(new Event('input',{bubbles:true}))}}
t.addEventListener('change',upd);if(s)s.addEventListener('change',upd);upd();})();

/* Адмінка: вибір роботодавця змінює назву, логотип і текст кнопки в перегляді. */
(function(){var b=document.getElementById('bnrLive'),s=document.getElementById('f_employer_id');if(!b||!s)return;
var top=b.querySelector('.bnr-top'),nm=b.querySelector('.bnr-emp b'),cta=document.getElementById('f_cta');
function upd(){var o=s.options[s.selectedIndex];if(!o||!o.value)return;nm.textContent=o.dataset.name;
var old=top.querySelector('.elogo'),n;if(o.dataset.logo){n=document.createElement('img');n.className='elogo';n.src=o.dataset.logo;n.alt='';n.width=42;n.height=42}else{n=document.createElement('span');n.className='elogo ph';n.textContent=o.dataset.ini}
old.replaceWith(n);if(cta){cta.dataset.all=o.dataset.cta;var tg=document.getElementById('f_target');cta.placeholder=tg&&/^\d+$/.test(tg.value)?'Дивитися вакансію':o.dataset.cta;cta.dispatchEvent(new Event('input',{bubbles:true}))}}
s.addEventListener('change',upd);upd();})();

/* Зайнятість місць (0.5.19): під датами показуємо, чи вільне обране місце, а якщо ні — з якого дня, і кнопку «Поставити цю дату».
   Біля кожного місця — з якого дня воно вільне на обрану кількість днів. Остаточно перевіряє сервер. */
(function(){var box=document.getElementById('adFree');if(!box)return;var f=box.closest('form'),o;try{o=JSON.parse(box.dataset.occ)}catch(e){return}
var E=f.elements,DAY=864e5;
function ts(s){var p=s.split('-');return Date.UTC(+p[0],p[1]-1,+p[2])}
function iso(t){return new Date(t).toISOString().slice(0,10)}
function dm(t,y){var d=new Date(t);return ('0'+d.getUTCDate()).slice(-2)+'.'+('0'+(d.getUTCMonth()+1)).slice(-2)+(y?'.'+d.getUTCFullYear():'')}
function places(p){return p==='both'?['home','jobs']:(o.limits[p]?[p]:[])}
function busy(pl,t){var n=0,d=iso(t);(o.busy[pl]||[]).forEach(function(r){if(r[0]<=d&&r[1]>=d)n++});return n}
function firstFull(p,from,days){for(var i=0;i<days;i++){var t=from+i*DAY,ps=places(p);for(var j=0;j<ps.length;j++)if(busy(ps[j],t)>=o.limits[ps[j]])return t}return null}
function nextFree(p,days,from){var t=Math.max(from,ts(o.today));for(var k=0;k<400;k++){var x=firstFull(p,t,days);if(x===null)return t;t=x+DAY}return null}
function pl(n){var a=n%10,b=n%100;return n+' '+(a===1&&b!==11?'день':(a>=2&&a<=4&&(b<12||b>14)?'дні':'днів'))}
function days(){return Math.max(1,+(E.days&&E.days.value)||1)}
function upd(){var r=f.querySelector('input[name=placement]:checked'),n=days(),today=ts(o.today);
[].forEach.call(f.querySelectorAll('.place-free'),function(el){var t=nextFree(el.dataset.for,n,today);el.className='place-free '+(t===today?'ok':'warn');
el.textContent=t===null?'Зайнято на рік уперед':(t===today?'Вільно з сьогодні':'Вільне з '+dm(t))});
var s=E.starts_on&&E.starts_on.value;if(!r||!s||!E.starts_on.type||E.starts_on.type==='hidden'){box.hidden=true;return}
var from=ts(s),full=firstFull(r.value,from,n);box.hidden=false;box.innerHTML='';
if(full===null){box.className='ad-free ok';box.textContent='Місце вільне на всі обрані дні: з '+dm(from)+' по '+dm(from+(n-1)*DAY,1)+'.';return}
var nx=nextFree(r.value,n,from);box.className='ad-free warn';
var t=document.createElement('span');t.textContent='На '+dm(full)+' це місце вже зайняте. '+(nx!==null?'Найближче вільне на '+pl(n)+': з '+dm(nx,1)+'.':'Оберіть інше місце показу.');box.appendChild(t);
if(nx!==null){var b=document.createElement('button');b.type='button';b.className='btn sm';b.textContent='Поставити з '+dm(nx);b.onclick=function(){E.starts_on.value=iso(nx);upd();E.starts_on.dispatchEvent(new Event('change',{bubbles:true}))};box.appendChild(b)}}
f.addEventListener('change',upd);f.addEventListener('input',function(e){if(e.target===E.starts_on)upd()});upd();})();
