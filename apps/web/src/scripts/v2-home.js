import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

(function(){
  var d = document, root = d.documentElement;
  root.classList.add('js');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var clamp = function(v,a,b){ return v < a ? a : v > b ? b : v; };
  var ph = function(p,a,b){ return clamp((p - a) / (b - a), 0, 1); };
  var easeOut = function(t){ return 1 - Math.pow(1 - t, 3); };
  var easeInOut = function(t){ return t < .5 ? 4*t*t*t : 1 - Math.pow(-2*t + 2, 3) / 2; };

  /* ---------- road graph edges: x1,y1,x2,y2,dist-from-pin,length,class ---------- */
  var EDGE_SRC = "814,124,818,92,201,32,M|808,170,814,124,155,46,M|794,250,808,170,73,81,M|780,322,794,250,0,73,M|780,322,772,400,0,78,M|772,400,767,440,78,40,M|767,440,761,495,119,55,M|761,495,756,530,174,35,M|756,530,750,580,209,50,M|750,580,742,618,260,39,M|742,618,730,708,299,91,M|730,708,716,1100,389,392,M|0,350,-300,356,781,300,M|180,342,0,350,601,180,M|232,345,180,342,549,52,M|316,348,232,345,465,84,M|330,348,316,348,451,14,M|472,340,330,348,309,142,M|592,336,472,340,188,120,M|685,329,592,336,95,93,M|780,322,685,329,0,95,M|780,322,860,315,0,80,M|860,315,940,310,80,80,M|940,310,1020,306,160,80,M|1020,306,1090,303,241,70,M|1090,303,1128,301,311,38,M|1128,301,1500,290,349,372,M|240,100,250,-300,794,400,M|232,345,240,100,549,245,M|232,345,236,600,549,255,M|236,600,250,1100,804,500,M|592,336,580,260,188,77,m|580,260,592,192,266,69,m|592,192,630,150,334,57,m|690,130,630,150,288,63,m|760,128,690,130,218,70,m|814,124,760,128,201,54,m|814,124,880,118,201,66,m|880,118,1028,146,250,151,m|1028,146,1090,156,305,63,m|1090,156,1128,162,368,38,m|1128,162,1400,170,407,272,m|808,170,760,128,155,64,m|780,322,828,280,0,64,m|828,280,880,236,64,68,m|880,236,935,198,132,67,m|935,198,988,166,199,62,m|988,166,1028,146,261,45,m|1092,92,1094,-300,432,392,m|1090,156,1092,92,368,64,m|1090,156,1090,220,368,64,m|1090,303,1090,220,311,83,m|1090,303,1094,362,311,59,m|1094,362,1084,424,370,63,m|1084,424,1078,500,402,76,m|1078,500,1070,570,478,70,m|1069,590,1070,570,499,20,m|1069,590,1066,708,499,118,m|1066,708,1060,1100,617,392,m|1020,306,1030,366,241,61,m|1030,366,1036,418,301,52,m|1036,418,1084,424,354,48,m|100,632,-300,640,941,400,m|290,640,100,632,751,190,m|472,630,290,640,569,182,m|670,624,472,630,371,198,m|742,618,670,624,299,72,m|742,618,810,628,299,69,m|810,628,878,620,367,68,m|930,596,878,620,359,57,m|930,596,1000,600,359,70,m|1000,600,1069,590,429,70,m|1069,590,1400,585,499,331,m|954,548,930,596,306,54,m|952,496,954,548,254,52,m|938,448,952,496,204,50,m|908,412,938,448,157,47,m|868,380,908,412,105,51,m|824,351,868,380,53,53,m|780,322,824,351,0,53,m|938,448,950,380,204,69,m|940,310,950,380,160,71,m|878,620,884,708,416,88,m|884,708,900,1100,505,392,m|767,440,732,460,119,40,m|732,460,718,492,159,35,m|718,492,730,520,194,30,m|756,530,730,520,209,28,m|718,492,652,488,194,66,m|652,488,630,487,260,22,m|630,487,472,482,282,158,m|472,482,300,476,440,172,m|300,476,130,484,594,170,m|130,484,-300,480,764,430,m|592,336,604,410,188,75,m|604,410,630,487,264,81,m|630,487,650,570,282,85,m|650,570,670,624,368,58,m|761,495,820,504,174,60,m|820,504,880,520,234,62,m|880,520,954,548,296,79,m|880,236,884,180,132,56,m|884,180,880,118,188,62,m|580,260,472,250,266,108,m|472,250,300,244,374,172,m|300,244,120,250,546,180,m|120,250,-300,240,726,420,m|630,150,626,92,352,58,m|685,329,694,400,95,72,m|732,460,694,400,159,71,m|316,348,300,244,465,105,m|316,348,300,476,465,129,m|300,476,290,640,594,164,m|290,640,282,1100,751,460,m|240,100,0,40,794,247,m|626,92,240,100,410,386,m|626,92,626,-8,410,100,m|1500,290,1300,420,721,238,m|1400,700,1300,420,951,297,m|1066,708,1400,700,617,334,m";
  var EDGES = EDGE_SRC.split('|').map(function(s){ var a = s.split(','); return { x1:+a[0], y1:+a[1], x2:+a[2], y2:+a[3], d:+a[4], l:+a[5], M:a[6]==='M' }; });
  var MAXD = 1342;
  var roads = d.getElementById('roads');
  var ctx = roads.getContext('2d', { alpha: true });
  var NORTH = [[780,322],[685,329],[592,336],[580,260],[592,192],[630,150],[690,130],[760,128],[808,170],[794,250],[780,322]];
  var EAST = [[780,322],[860,315],[940,310],[1020,306],[1030,366],[1036,418],[1084,424],[1094,362],[1090,303],[1090,220],[1090,156],[1028,146],[988,166],[935,198],[880,236],[828,280],[780,322]];
  var WIN_A = [[780,322],[772,400],[767,440]];
  var WIN_OLD = [[767,440],[761,495],[756,530]];
  var WIN_VIA = [[767,440],[732,460],[718,492],[730,520],[756,530]];
  var WIN_B = [[756,530],[750,580],[742,618],[810,628],[878,620],[930,596],[954,548],[952,496],[938,448],[908,412],[868,380],[824,351],[780,322]];
  var ARROWS = [[769.8,418,187.1],[844,624,83.3],[953,522,357.8],[846,365.5,303.4]];

  /* ---------- elements ---------- */
  var $ = function(id){ return d.getElementById(id); };
  var track = $('generate'), stage = $('stage'), hdr = $('hdr'), hero = $('hero'), mapbox = $('mapbox'), map = $('map'), world = $('world');
  var ring = $('ring'), lN = $('l-north'), lE = $('l-east');
  var wA = $('w-A'), wOld = $('w-old'), wVia = $('w-via'), wB = $('w-B');
  var gA = $('g-A'), gOld = $('g-old'), gVia = $('g-via'), gB = $('g-B');
  var viadot = $('viadot'), arrows = Array.prototype.slice.call(d.querySelectorAll('#arrows .arrow'));
  var narr = Array.prototype.slice.call(d.querySelectorAll('.narr p'));
  var device = $('device'), devLoop = $('dev-loop');
  var vRoads = $('v-roads'), vCand = $('v-cand'), vLoop = $('v-loop'), vAsc = $('v-asc'), vCourse = $('v-course');
  var hRoads = $('h-roads'), hCand = $('h-cand'), hLoop = $('h-loop'), hAsc = $('h-asc'), hCourse = $('h-course');
  var rail = Array.prototype.slice.call(d.querySelectorAll('#rail li'));
  var mapdim = $('mapdim'), actN = $('act-n'), actT = $('act-t'), lastAct = -1;
  var heroBtn = d.querySelector('.hero-btn');

  var LA = 118.7, LO = 90.7, LB = 642.8, LT = LA + LO + LB; /* winner piece lengths */
  var fA = LA / LT, fO = LO / LT;

  var setOff = function(el, v){ if (el) el.style.strokeDashoffset = v; };
  var setOp = function(el, v){ if (el) el.style.opacity = v; };
  var lastRoads = '', lastLoop = '', lastCand = '', lastAsc = '', lastCourse = '';
  var narrow = window.innerWidth < 1024;
  var lastWidth = window.innerWidth;
  var lastSideways = null;
  var view = [0, 0, 1200, 800];

  function syncCanvas(){
    var rect = mapbox.getBoundingClientRect();
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var w = Math.max(1, Math.round(rect.width * dpr));
    var h = Math.max(1, Math.round(rect.height * dpr));
    if (roads.width !== w || roads.height !== h){
      roads.width = w;
      roads.height = h;
    }
    var parts = map.getAttribute('viewBox').split(/[\s,]+/).map(Number);
    view = parts;
    var s = Math.min(rect.width / parts[2], rect.height / parts[3]);
    var ox = (rect.width - parts[2] * s) / 2;
    var oy = (rect.height - parts[3] * s) / 2;
    ctx.setTransform(dpr * s, 0, 0, dpr * s, dpr * ox - dpr * s * parts[0], dpr * oy - dpr * s * parts[1]);
  }

  function clearCanvas(){
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, roads.width, roads.height);
    ctx.restore();
  }

  function seg(x1, y1, x2, y2, f, width, color, alpha){
    if (f <= 0 || alpha <= 0.01) return;
    ctx.globalAlpha = alpha;
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x1 + (x2 - x1) * f, y1 + (y2 - y1) * f);
    ctx.stroke();
  }

  function poly(pts, t, width, color, alpha){
    if (t <= 0 || alpha <= 0.01 || pts.length < 2) return;
    var total = 0, lens = [];
    for (var i = 1; i < pts.length; i++){
      var L = Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]);
      lens.push(L);
      total += L;
    }
    var remain = total * Math.min(1, t);
    ctx.globalAlpha = alpha;
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (var j = 1; j < pts.length && remain > 0; j++){
      if (lens[j - 1] <= remain){
        ctx.lineTo(pts[j][0], pts[j][1]);
        remain -= lens[j - 1];
      } else {
        var u = remain / lens[j - 1];
        ctx.lineTo(pts[j - 1][0] + (pts[j][0] - pts[j - 1][0]) * u, pts[j - 1][1] + (pts[j][1] - pts[j - 1][1]) * u);
        remain = 0;
      }
    }
    ctx.stroke();
  }

  function arrow(x, y, deg, alpha){
    if (alpha <= 0.01) return;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(deg * Math.PI / 180);
    ctx.globalAlpha = alpha;
    ctx.fillStyle = 'oklch(0.97 0.012 95)';
    ctx.beginPath();
    ctx.roundRect(-9, -9, 18, 18, 4.5);
    ctx.fill();
    ctx.fillStyle = 'oklch(0.22 0.03 200)';
    ctx.beginPath();
    ctx.moveTo(0, -5.5);
    ctx.lineTo(5.5, 1);
    ctx.lineTo(2, 1);
    ctx.lineTo(2, 5.5);
    ctx.lineTo(-2, 5.5);
    ctx.lineTo(-2, 1);
    ctx.lineTo(-5.5, 1);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  }

  /* sentence windows [start, end] in progress */
  var SW = [[0.085,0.345],[0.345,0.56],[0.56,0.655],[0.655,0.735],[0.735,0.845],[0.845,1.01]];
  var ACTS = [0, 0.085, 0.345, 0.56, 0.655, 0.735, 0.845];

  /* Desktop shows the whole sheet beside the copy. On a phone or tablet the
     map fills the stage; the part where the story happens, the pin, the three
     loops and the via, is framed in the clear window between the sentence on
     top and the readout below, as large as that window allows. */
  var FOCUS = { cx: 838, cy: 376, w: 620, h: 580 };
  var copyInner = d.querySelector('.copy-inner'), copyBox = d.querySelector('.copy'), hud = $('hud');
  var sideways = window.matchMedia('(max-width: 1023px) and (max-height: 520px) and (orientation: landscape)');
  lastSideways = sideways.matches;
  function fitMap(){
    var r = mapbox.getBoundingClientRect();
    var box = '0 0 1200 800';
    if (narrow && r.width > 0 && r.height > 0){
      var left = 0, top;
      var bottom = r.bottom - hud.getBoundingClientRect().top;
      if (sideways.matches){
        left = copyBox.getBoundingClientRect().right - r.left;
        top = 44;
      } else {
        top = copyInner.getBoundingClientRect().bottom - r.top + 22;
      }
      var ww = r.width - left;
      var wh = Math.max(120, r.height - top - bottom - 20);
      var s = Math.min(ww / FOCUS.w, wh / FOCUS.h);
      var x0 = FOCUS.cx - (left + ww / 2) / s;
      var y0 = FOCUS.cy - (top + 6 + wh / 2) / s;
      box = [x0, y0, r.width / s, r.height / s].map(function(v){ return Math.round(v * 10) / 10; }).join(' ');
      stage.style.setProperty('--clear-left', Math.round(left) + 'px');
      stage.style.setProperty('--clear-top', Math.round(top) + 'px');
      stage.style.setProperty('--clear-bottom', Math.round(bottom) + 'px');
      stage.style.setProperty('--hero-h', Math.round(hero.getBoundingClientRect().bottom - stage.getBoundingClientRect().top) + 'px');
    }
    if (map.getAttribute('viewBox') === box) return;
    map.setAttribute('viewBox', box);
    var pinlayer = d.getElementById('pinlayer');
    if (pinlayer) pinlayer.setAttribute('viewBox', box);
  }

  var lastP = -1;
  function render(p){
    lastP = p;
    /* hero */
    var h = ph(p, 0.025, 0.085);
    hero.style.opacity = 1 - h;
    hero.style.transform = 'translateY(' + (-18 * h) + 'px)';
    hero.style.visibility = h >= 1 ? 'hidden' : 'visible';
    stage.style.setProperty('--hero', String(1 - h));

    var rp = ph(p, 0.05, 0.2);
    var wv = easeOut(ph(p, 0.085, 0.345));
    var R = wv * MAXD;
    var fade = 1 - ph(p, 0.345, 0.42);
    var q = easeInOut(ph(p, 0.345, 0.56));
    var chosen = ph(p, 0.56, 0.655);
    var lop = q > 0 ? 0.72 * (1 - chosen) : 0;
    var pa = clamp(q / fA, 0, 1), po = clamp((q - fA) / fO, 0, 1), pb = clamp((q - fA - fO) / (1 - fA - fO), 0, 1);
    var vdot = ph(p, 0.735, 0.765);
    var vdraw = ph(p, 0.75, 0.835);
    var paper = 'oklch(0.97 0.012 95)';
    var chart = 'oklch(0.78 0.17 128)';
    var reached = 0;

    syncCanvas();
    clearCanvas();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    for (var n = 0; n < EDGES.length; n++){
      var e = EDGES[n];
      var f = clamp((R - e.d) / e.l, 0, 1);
      if (f <= 0) continue;
      if (f >= 1) reached++;
      var behind = R - (e.d + e.l);
      var op = (f < 1 ? 1 : clamp(1 - behind / 300, 0, 1)) * fade;
      if (f >= 1) seg(e.x1, e.y1, e.x2, e.y2, 1, e.M ? 2.8 : 1.6, paper, e.M ? 0.62 : 0.42);
      if (op > 0.02) seg(e.x1, e.y1, e.x2, e.y2, Math.min(f, 1), e.M ? 3.4 : 2.4, chart, op);
    }
    if (rp > 0 && rp < 1){
      ctx.beginPath();
      ctx.arc(780, 322, 16 + rp * 300, 0, Math.PI * 2);
      ctx.strokeStyle = chart;
      ctx.globalAlpha = (1 - rp) * 0.45;
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }
    poly(NORTH, q, 2.6, paper, lop);
    poly(EAST, q, 2.6, paper, lop);
    poly(WIN_A, pa, 5.2, paper, 1);
    poly(WIN_B, pb, 5.2, paper, 1);
    poly(WIN_OLD, po, 5.2, paper, 1 - vdraw);
    poly(WIN_VIA, vdraw, 5.2, paper, vdraw > 0 ? 1 : 0);
    if (vdot > 0){
      ctx.beginPath();
      ctx.arc(718, 492, 4 + 3 * vdot, 0, Math.PI * 2);
      ctx.fillStyle = chart;
      ctx.globalAlpha = vdot;
      ctx.fill();
    }
    for (var ak = 0; ak < ARROWS.length; ak++){
      var amt = ph(p, 0.655 + ak * 0.018, 0.675 + ak * 0.018);
      arrow(ARROWS[ak][0], ARROWS[ak][1], ARROWS[ak][2], amt);
    }
    ctx.globalAlpha = 1;

    var roadsLabel = reached + ' of ' + EDGES.length;
    if (roadsLabel !== lastRoads){
      lastRoads = roadsLabel;
      vRoads.textContent = roadsLabel;
    }
    hRoads.className = reached > 0 && reached < EDGES.length ? 'hot' : (reached >= EDGES.length ? '' : 'dim');

    /* distance readout */
    var km;
    if (p < 0.56) km = q * 40.2; else km = 40.2 + 1.4 * vdraw;
    var loopLabel = km.toFixed(1) + ' km';
    if (loopLabel !== lastLoop){ lastLoop = loopLabel; vLoop.textContent = loopLabel; }
    hLoop.className = (q > 0 && q < 1) || (vdraw > 0 && vdraw < 1) ? 'hot' : '';
    var candLabel = q <= 0 ? '—' : (chosen >= 1 ? '3 · best picked' : '3');
    if (candLabel !== lastCand){ lastCand = candLabel; vCand.textContent = candLabel; }
    hCand.className = q <= 0 ? 'dim' : '';
    var ascLabel = chosen > 0 ? '180 m' : '—';
    if (ascLabel !== lastAsc){ lastAsc = ascLabel; vAsc.textContent = ascLabel; }
    hAsc.className = chosen > 0 ? '' : 'dim';

    /* device */
    var dv = ph(p, 0.845, 0.905);
    device.style.opacity = dv;
    device.style.transform = (narrow ? 'translateX(-50%) ' : '') + 'translateY(' + (24 * (1 - dv)) + 'px)';
    if (narrow) mapdim.style.opacity = 0.62 * dv;
    setOff(devLoop, 1 - ph(p, 0.88, 0.97));
    var done = p >= 0.955;
    var courseLabel = done ? 'On your device' : 'Planning…';
    if (courseLabel !== lastCourse){ lastCourse = courseLabel; vCourse.textContent = courseLabel; }
    hCourse.className = done ? 'hot' : '';

    /* sentences */
    for (var s = 0; s < narr.length; s++){
      var w = SW[s], a = w[0], b = w[1];
      var o = Math.min(ph(p, a, a + 0.025), 1 - ph(p, b - 0.02, b));
      if (s === narr.length - 1) o = ph(p, a, a + 0.025);
      narr[s].style.opacity = o;
      narr[s].style.transform = 'translateY(' + (10 * (1 - o)) + 'px)';
    }

    /* rail */
    var act = 0;
    for (var r = 0; r < ACTS.length; r++) if (p >= ACTS[r]) act = r;
    for (var r2 = 0; r2 < rail.length; r2++) rail[r2].className = r2 === act ? 'on' : (r2 < act ? 'done' : '');
    if (act !== lastAct && rail[act]){
      lastAct = act;
      actN.textContent = (act < 9 ? '0' : '') + (act + 1);
      actT.textContent = rail[act].textContent;
    }
    syncHeaderCta();
  }

  /* The hero carries the filled button. The header's copy stays out of the
     way until the hero has faded or scrolled off, so there is one at a time. */
  function syncHeaderCta(){
    if (!heroBtn) return;
    var shown = hero.style.visibility !== 'hidden' && (hero.style.opacity === '' || +hero.style.opacity > 0.35);
    if (shown){
      var b = heroBtn.getBoundingClientRect();
      shown = b.bottom > hdr.getBoundingClientRect().bottom && b.top < window.innerHeight;
    }
    hdr.classList.toggle('cta-away', shown);
  }

  function headerTheme(){
    var r = track.getBoundingClientRect();
    var hh = hdr.getBoundingClientRect().height;
    var onDark = r.top <= hh + 1 && r.bottom > hh + 8;
    hdr.classList.toggle('on-dark', onDark);
  }

  function progress(){
    var r = track.getBoundingClientRect();
    var hh = hdr.getBoundingClientRect().height;
    var range = r.height - stage.getBoundingClientRect().height;
    if (range <= 0) return 1;
    return clamp((hh - r.top) / range, 0, 1);
  }

  /* A phone fires resize when its toolbar shows or hides. Only a change of
     width means a new layout; refreshing on height alone makes the pinned
     scene jump under the reader's thumb. */
  function onResize(){
    fitMap();
    if (window.innerWidth !== lastWidth || sideways.matches !== lastSideways){
      lastSideways = sideways.matches;
      lastWidth = window.innerWidth;
      narrow = window.innerWidth < 1024;
      fitMap();
      if (!reduce) ScrollTrigger.refresh();
    }
    if (reduce) renderStill(); else render(lastP < 0 ? 0 : lastP);
  }

  gsap.registerPlugin(ScrollTrigger);
  fitMap();
  /* Reduced motion: the finished scene, still, with the hero on top. */
  function renderStill(){
    render(1);
    hero.style.opacity = 1; hero.style.transform = 'none'; hero.style.visibility = 'visible';
    stage.style.setProperty('--hero', '1');
    narr.forEach(function(n){ n.style.opacity = 0; });
    headerTheme();
    syncHeaderCta();
  }

  if (reduce){
    renderStill();
  } else {
    /* Smooth wheel scrolling for a mouse or trackpad. A finger keeps the
       browser's own scrolling and momentum, which Lenis would only fight. */
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches){
      var lenis = new Lenis({
        lerp: 0.075,
        smoothWheel: true,
        wheelMultiplier: 0.85,
        syncTouch: false,
        anchors: { offset: -hdr.offsetHeight },
        autoRaf: false,
      });
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add(function(time){ lenis.raf(time * 1000); });
    } else {
      root.classList.add('native-scroll');
    }
    gsap.ticker.lagSmoothing(0);
    ScrollTrigger.create({
      trigger: track,
      start: function(){ return 'top ' + hdr.offsetHeight + 'px'; },
      end: 'bottom bottom',
      scrub: true,
      onToggle: function(self){ hdr.classList.toggle('on-dark', self.isActive); },
      onUpdate: function(self){
        hdr.classList.toggle('on-dark', self.isActive);
        render(self.progress);
      },
    });
    render(0);
  }
  window.addEventListener('resize', onResize);
  window.addEventListener('scroll', function(){ if (reduce){ headerTheme(); syncHeaderCta(); } }, { passive: true });
  syncHeaderCta();

  /* ---------- ask your AI: the chat plans a loop while it is on screen ---------- */
  var chat = $('chat');
  if (chat && !reduce){
    var ask = $('chat-ask'), typing = $('chat-typing'), tool = $('chat-tool'), toolT = $('chat-tool-t');
    var card = $('chat-card'), loop = $('chat-loop'), km = $('chat-km'), reply = $('chat-reply');
    var stops = chat.querySelectorAll('.rcard-stop'), facts = chat.querySelectorAll('.rcard-facts li');
    var start = chat.querySelector('.rcard-start'), words = ask.getAttribute('data-text');
    var typed = { n: 0 }, counted = { v: 0 }, kmTo = parseFloat(km.textContent);
    // The start of the scene, applied at once and again on every loop. A
    // set inside a paused timeline waits for play, so the finished card
    // would show until the chat is far enough in view to start.
    var resetChat = function(){
      ask.textContent = '\u00a0';
      tool.classList.remove('done');
      toolT.textContent = 'Traceway · planning a loop';
      km.textContent = '0.0';
      gsap.set([tool, card, reply], { autoAlpha: 0, y: 12 });
      gsap.set(typing, { autoAlpha: 0, y: 8 });
      gsap.set(loop, { strokeDashoffset: 1 });
      gsap.set(stops, { autoAlpha: 0, scale: 0.3, transformOrigin: '50% 50%' });
      gsap.set(start, { autoAlpha: 0, scale: 0.3, transformOrigin: '50% 50%' });
      gsap.set(facts, { autoAlpha: 0, y: 6 });
    };
    resetChat();
    var chatTl = gsap.timeline({ paused: true, repeat: -1, repeatDelay: 3.5, defaults: { ease: 'power3.out' } });
    chatTl
      .call(resetChat)
      .to(typed, { n: words.length, duration: 1.9, ease: 'none', onUpdate: function(){ ask.textContent = words.slice(0, Math.round(typed.n)) || ' '; } }, 0.4)
      .to(typing, { autoAlpha: 1, y: 0, duration: 0.35 }, '+=0.35')
      .to(typing, { autoAlpha: 0, duration: 0.2 }, '+=0.9')
      .to(tool, { autoAlpha: 1, y: 0, duration: 0.4 }, '<')
      .to(card, { autoAlpha: 1, y: 0, duration: 0.6 }, '+=0.9')
      .to(start, { autoAlpha: 1, scale: 1, duration: 0.4, ease: 'back.out(2)' }, '-=0.1')
      .to(loop, { strokeDashoffset: 0, duration: 1.9, ease: 'power2.inOut' }, '<')
      .fromTo(counted, { v: 0 }, { v: kmTo, duration: 1.9, ease: 'power2.inOut', onUpdate: function(){ km.textContent = counted.v.toFixed(1); } }, '<')
      .to(stops, { autoAlpha: 1, scale: 1, duration: 0.45, ease: 'back.out(2.2)', stagger: 0.22 }, '-=1.1')
      .call(function(){
        tool.classList.add('done');
        toolT.textContent = 'Traceway · loop planned and saved';
      })
      .to(facts, { autoAlpha: 1, y: 0, duration: 0.4, stagger: 0.12 }, '+=0.1')
      .to(reply, { autoAlpha: 1, y: 0, duration: 0.5 }, '+=0.35');
    if ('IntersectionObserver' in window){
      new IntersectionObserver(function(entries){
        entries.forEach(function(en){ if (en.isIntersecting) chatTl.play(); else chatTl.pause(); });
      }, { threshold: 0.35 }).observe(chat);
    } else {
      chatTl.play();
    }
  }

  /* ---------- reveals ---------- */
  var rv = Array.prototype.slice.call(d.querySelectorAll('.rv'));
  if ('IntersectionObserver' in window && !reduce){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){ if (en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });
    rv.forEach(function(el){ io.observe(el); });
  } else {
    rv.forEach(function(el){ el.classList.add('in'); });
  }
})();
