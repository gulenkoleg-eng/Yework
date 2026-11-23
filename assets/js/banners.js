/* YEWORK 0.5.12 — живий перегляд банера в конструкторі (кабінет роботодавця й адмінка). */
(function(){var f=document.getElementById('bnrLive')&&document.getElementById('bnrLive').closest('form'),b=document.getElementById('bnrLive');if(!f||!b)return;
var q=function(s){return b.querySelector(s)},h3=q('h3'),p=q('p'),ul=q('.bnr-perks'),bd=q('.bnr-badge'),cta=q('.bnr-cta>span'),ph=q('.bnr-photo'),im=q('.bnr-img'),req=document.getElementById('adImgReq');
var E=f.elements,ex={title:h3.textContent,body:p.textContent,cta:cta.textContent},exPerks=f.querySelectorAll('[name="perks[]"]'),url='';
function tpl(){var r=f.querySelector('input[name=template]:checked');return r?r.value:'classic'}
function upd(){var t=tpl();b.className=b.className.replace(/\bt-\w+/,'t-'+t);
h3.textContent=E.title.value.trim()||ex.title;p.textContent=E.body.value.trim()||ex.body;
var ps=[].map.call(exPerks,function(i){return i.value.trim()}).filter(Boolean),any=[].some.call(exPerks,function(i){return i.value.trim()});
if(!any)ps=[].map.call(exPerks,function(i){return i.placeholder});ul.innerHTML='';ps.forEach(function(x){var li=document.createElement('li');li.textContent=x;ul.appendChild(li)});ul.hidden=!ps.length;
var bv=E.badge.value.trim();bd.hidden=!bv;bd.querySelector('span').textContent=bv;
cta.textContent=E.cta.value.trim()||E.cta.placeholder||ex.cta;
ph.hidden=!url;im.hidden=!url||t==='photo';if(url){ph.src=url;im.src=url}
req.textContent=t==='photo'?'(обов’язково для «Фото»)':'(необов’язково)';}
E.image.addEventListener('change',function(){if(url)URL.revokeObjectURL(url);var x=E.image.files&&E.image.files[0];url=x&&/^image\//.test(x.type)?URL.createObjectURL(x):'';upd()});
f.addEventListener('input',upd);f.addEventListener('change',upd);upd();})();

/* Адмінка: вибір роботодавця змінює назву, логотип і текст кнопки в перегляді. */
(function(){var b=document.getElementById('bnrLive'),s=document.getElementById('f_employer_id');if(!b||!s)return;
var top=b.querySelector('.bnr-top'),nm=b.querySelector('.bnr-emp b'),cta=document.getElementById('f_cta');
function upd(){var o=s.options[s.selectedIndex];if(!o||!o.value)return;nm.textContent=o.dataset.name;
var old=top.querySelector('.elogo'),n;if(o.dataset.logo){n=document.createElement('img');n.className='elogo';n.src=o.dataset.logo;n.alt='';n.width=42;n.height=42}else{n=document.createElement('span');n.className='elogo ph';n.textContent=o.dataset.ini}
old.replaceWith(n);if(cta){cta.placeholder=o.dataset.cta;cta.dispatchEvent(new Event('input',{bubbles:true}))}}
s.addEventListener('change',upd);upd();})();
