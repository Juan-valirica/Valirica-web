document.addEventListener("DOMContentLoaded", () => {

  /* =====================================================
     GLOBAL ELEMENTS
  ===================================================== */

  const hero = document.querySelector(".hero");
  const layers = document.querySelectorAll(".hero [data-depth]");
  const images = document.querySelectorAll(".dashboard-img");
  const heroElements = document.querySelectorAll(".hero-content, .hero-visual");
  const list = document.querySelector(".operativo-list");
  const openDemo = document.getElementById("openDemo");
  const closeDemo = document.getElementById("closeDemo");
  const demoModal = document.getElementById("demoModal");

  /* =====================================================
     MOTION PREFERENCE
  ===================================================== */

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  ).matches;

  /* =====================================================
     LUCIDE
  ===================================================== */

  if (window.lucide) lucide.createIcons();

  /* =====================================================
     HERO ENTRANCE
  ===================================================== */

  if (!prefersReducedMotion) {
    heroElements.forEach((el, index) => {
      el.classList.add("reveal-init");
      setTimeout(() => el.classList.add("reveal-active"), 150 + index * 150);
    });
  }

  /* =====================================================
     SECTION FADE · SCROLL REVEAL
  ===================================================== */

  const fadeSections = document.querySelectorAll(".section-fade");

  if (fadeSections.length && !prefersReducedMotion) {
    const sectionObserver = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.05 });

    fadeSections.forEach(section => sectionObserver.observe(section));
  }

  /* =====================================================
     DASHBOARD CROSSFADE
  ===================================================== */

  if (images.length >= 2) {
    let current = 0;
    images[current].classList.add("active");

    setInterval(() => {
      images[current].classList.remove("active");
      current = (current + 1) % images.length;
      images[current].classList.add("active");
    }, 4500);
  }

  /* =====================================================
     LIST REVEAL
  ===================================================== */

  if (list) {
    const items = list.querySelectorAll("li");
    items.forEach(item => item.classList.add("list-init"));

    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          items.forEach((item, index) => {
            setTimeout(() => item.classList.add("list-active"), index * 120);
          });
          obs.disconnect();
        }
      });
    }, { threshold: 0.3 });

    observer.observe(list);
  }

  /* =====================================================
     HERO PARALLAX
  ===================================================== */

  if (hero && window.innerWidth > 1024 && !prefersReducedMotion) {

    let mouseX = 0, mouseY = 0;
    let currentX = 0, currentY = 0;
    let isVisible = true;
    const speed = 0.02;

    const heroObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => isVisible = entry.isIntersecting);
    }, { threshold: 0.1 });

    heroObserver.observe(hero);

    hero.addEventListener("mousemove", e => {
      const rect = hero.getBoundingClientRect();
      mouseX = (e.clientX - rect.left - rect.width / 2) / rect.width;
      mouseY = (e.clientY - rect.top - rect.height / 2) / rect.height;
    });

    function animateParallax() {
      if (isVisible) {
        currentX += (mouseX - currentX) * speed;
        currentY += (mouseY - currentY) * speed;

        layers.forEach(layer => {
          const depth = parseFloat(layer.dataset.depth);
          layer.style.transform =
            `translate3d(${currentX * depth * 20}px, ${currentY * depth * 20}px, 0)`;
        });
      }
      requestAnimationFrame(animateParallax);
    }

    animateParallax();
  }

  /* =====================================================
     DEMO MODAL
  ===================================================== */

  if (openDemo && demoModal)
    openDemo.addEventListener("click", () => demoModal.classList.add("active"));

  if (closeDemo && demoModal)
    closeDemo.addEventListener("click", () => demoModal.classList.remove("active"));



/* =====================================================
   DIAGRAMA NEURONAL · REVEAL + CONEXIONES SVG
===================================================== */

(function initDiagram() {

  const wrapper   = document.getElementById("diagramWrapper");
  const svgEl     = document.getElementById("diagramSvg");
  const brainEl   = document.getElementById("brainContainer");
  const nodes     = document.querySelectorAll(".diagram-node");
  const modSection = document.querySelector(".modulos");

  if (!wrapper || !svgEl || !brainEl || !modSection) return;

  const NS = "http://www.w3.org/2000/svg";
  const INPUT_IDS  = ["node-in-0",  "node-in-1",  "node-in-2",  "node-in-3"];
  const OUTPUT_IDS = ["node-out-0", "node-out-1", "node-out-2"];

  let connectionsDrawn = false;

  /* ---- helpers ---- */

  function relRect(el) {
    const er = el.getBoundingClientRect();
    const wr = wrapper.getBoundingClientRect();
    return {
      left:   er.left   - wr.left,
      top:    er.top    - wr.top,
      right:  er.right  - wr.left,
      bottom: er.bottom - wr.top,
      cx:     er.left   - wr.left + er.width  / 2,
      cy:     er.top    - wr.top  + er.height / 2,
      w: er.width,
      h: er.height
    };
  }

  function makePath(d, stroke, opacity, dashArray, animName, delay) {
    const p = document.createElementNS(NS, "path");
    p.setAttribute("d", d);
    p.setAttribute("stroke", stroke);
    p.setAttribute("stroke-opacity", opacity);
    p.setAttribute("stroke-width", "1.5");
    p.setAttribute("fill", "none");
    p.setAttribute("stroke-linecap", "round");
    if (dashArray) {
      svgEl.appendChild(p);                       // must be in DOM for getTotalLength
      const len = Math.round(p.getTotalLength()) || 250;
      p.setAttribute("stroke-dasharray", dashArray);
      p.setAttribute("stroke-dashoffset", "0");
      p.style.animation = `${animName} ${1.9}s linear infinite`;
      p.style.animationDelay = `${delay}s`;
    }
    return p;
  }

  /* ---- draw all bezier connections ---- */

  function drawConnections() {
    svgEl.innerHTML = "";

    // Only draw on desktop (diagram-svg is hidden via CSS on mobile)
    if (window.innerWidth <= 768) return;

    const wr = wrapper.getBoundingClientRect();
    svgEl.setAttribute("viewBox", `0 0 ${wr.width} ${wr.height}`);
    svgEl.setAttribute("width",   wr.width);
    svgEl.setAttribute("height",  wr.height);

    const br = relRect(brainEl);

    // attachment points on brain edges (inset 22px from extremes)
    const brainLeft  = { x: br.left  + 22, y: br.cy };
    const brainRight = { x: br.right - 22, y: br.cy };

    /* INPUT → BRAIN (orange) */
    INPUT_IDS.forEach((id, i) => {
      const el = document.getElementById(id);
      if (!el) return;

      const nr = relRect(el);
      const from = { x: nr.right, y: nr.cy };
      const to   = brainLeft;
      const dx   = to.x - from.x;

      const d = `M ${from.x} ${from.y} `
              + `C ${from.x + dx * 0.55} ${from.y}, `
              +   `${to.x   - dx * 0.35} ${to.y}, `
              +   `${to.x} ${to.y}`;

      // Static background
      const bg = document.createElementNS(NS, "path");
      bg.setAttribute("d", d);
      bg.setAttribute("stroke", "#ff9700");
      bg.setAttribute("stroke-opacity", "0.10");
      bg.setAttribute("stroke-width", "1.5");
      bg.setAttribute("fill", "none");
      bg.setAttribute("stroke-linecap", "round");
      svgEl.appendChild(bg);

      // Animated flow dash
      const flow = document.createElementNS(NS, "path");
      flow.setAttribute("d", d);
      flow.setAttribute("stroke", "#ff9700");
      flow.setAttribute("stroke-opacity", "0.55");
      flow.setAttribute("stroke-width", "1.5");
      flow.setAttribute("fill", "none");
      flow.setAttribute("stroke-linecap", "round");
      svgEl.appendChild(flow);

      const len = Math.round(flow.getTotalLength()) || 250;
      flow.setAttribute("stroke-dasharray", `18 ${len - 18}`);
      flow.setAttribute("stroke-dashoffset", "0");
      flow.style.animation = `diagramFlowIn 2s linear infinite`;
      flow.style.animationDelay = `${i * 0.38}s`;
    });

    /* BRAIN → OUTPUT (teal) */
    OUTPUT_IDS.forEach((id, i) => {
      const el = document.getElementById(id);
      if (!el) return;

      const nr   = relRect(el);
      const from = brainRight;
      const to   = { x: nr.left, y: nr.cy };
      const dx   = to.x - from.x;

      const d = `M ${from.x} ${from.y} `
              + `C ${from.x + dx * 0.35} ${from.y}, `
              +   `${to.x   - dx * 0.55} ${to.y}, `
              +   `${to.x} ${to.y}`;

      const bg = document.createElementNS(NS, "path");
      bg.setAttribute("d", d);
      bg.setAttribute("stroke", "#007a96");
      bg.setAttribute("stroke-opacity", "0.10");
      bg.setAttribute("stroke-width", "1.5");
      bg.setAttribute("fill", "none");
      bg.setAttribute("stroke-linecap", "round");
      svgEl.appendChild(bg);

      const flow = document.createElementNS(NS, "path");
      flow.setAttribute("d", d);
      flow.setAttribute("stroke", "#007a96");
      flow.setAttribute("stroke-opacity", "0.55");
      flow.setAttribute("stroke-width", "1.5");
      flow.setAttribute("fill", "none");
      flow.setAttribute("stroke-linecap", "round");
      svgEl.appendChild(flow);

      const len = Math.round(flow.getTotalLength()) || 250;
      flow.setAttribute("stroke-dasharray", `18 ${len - 18}`);
      flow.setAttribute("stroke-dashoffset", "0");
      flow.style.animation = `diagramFlowOut 2s linear infinite`;
      flow.style.animationDelay = `${0.2 + i * 0.38}s`;
    });

    connectionsDrawn = true;
  }

  /* ---- reveal sequence (triggered once when section enters viewport) ---- */

  function revealDiagram() {
    if (prefersReducedMotion) {
      nodes.forEach(n => n.classList.add("node-visible"));
      brainEl.classList.add("brain-visible");
      drawConnections();
      return;
    }

    // Brain appears first
    setTimeout(() => brainEl.classList.add("brain-visible"), 80);

    // Input nodes: left column stagger
    INPUT_IDS.forEach((id, i) => {
      const el = document.getElementById(id);
      if (el) setTimeout(() => el.classList.add("node-visible"), 200 + i * 100);
    });

    // Output nodes: right column stagger, offset
    OUTPUT_IDS.forEach((id, i) => {
      const el = document.getElementById(id);
      if (el) setTimeout(() => el.classList.add("node-visible"), 280 + i * 110);
    });

    // Draw SVG connections after reveal is underway
    setTimeout(drawConnections, 350);
  }

  /* ---- intersection observer ---- */

  const sectionObserver = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        revealDiagram();
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08 });

  sectionObserver.observe(modSection);

  /* ---- resize: redraw connections ---- */

  let resizeTimer;
  window.addEventListener("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (connectionsDrawn) drawConnections();
    }, 200);
  });

})();

/* =====================================================
   CULTURE QUADRANT
===================================================== */

const canvas = document.getElementById("cultureQuadrant");
const chip = document.getElementById("alignmentChip");
const tooltip = document.getElementById("cultureTooltip");

if (!canvas) return;

const ctx = canvas.getContext("2d");

/* ===== CANVAS RESPONSIVE REAL ===== */

function resizeCanvas() {
  const rect = canvas.getBoundingClientRect();

  canvas.width = rect.width;
  canvas.height = rect.width; // cuadrado perfecto

  size = canvas.width;
  center = size / 2;
}

let size;
let center;

resizeCanvas();
window.addEventListener("resize", resizeCanvas);

/* ===== VISIBILITY CONTROL ===== */

let isVisible = false;
let mouseActive = false;

const visibilityObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    isVisible = entry.isIntersecting;
  });
}, { threshold: 0.2 });

visibilityObserver.observe(canvas);

  /* ---------- GREEN POINTS ---------- */

  const greenPoints = [];

  for (let i = 0; i < 10; i++) {
    greenPoints.push({
      x: Math.random() * size,
      y: Math.random() * size,
      angle: Math.random() * Math.PI * 2
    });
  }

  /* ---------- ORANGE POINT ---------- */

  let orangePoint = {
    x: 150,
    y: 150,
    vx: 0.8,
    vy: 0.6
  };

  /* ---------- MOUSE CONTROL ---------- */

  canvas.addEventListener("mouseenter", () => mouseActive = true);
  canvas.addEventListener("mouseleave", () => {
    mouseActive = false;
    if (tooltip) tooltip.classList.remove("visible");
  });

  canvas.addEventListener("mousemove", e => {

    const rect = canvas.getBoundingClientRect();
    const mouseX = (e.clientX - rect.left) * (size / rect.width);
    const mouseY = (e.clientY - rect.top) * (size / rect.height);

    if (mouseActive) {
      orangePoint.x = mouseX;
      orangePoint.y = mouseY;
    }

    if (!tooltip) return;

    const distOrange = Math.hypot(mouseX - orangePoint.x, mouseY - orangePoint.y);

    if (distOrange < 15) {
      tooltip.textContent = "Cultura estratégica proyectada por la dirección.";
      tooltip.style.left = (e.clientX - rect.left) + "px";
      tooltip.style.top = (e.clientY - rect.top) + "px";
      tooltip.classList.add("visible");
      return;
    }

    for (let p of greenPoints) {
      if (Math.hypot(mouseX - p.x, mouseY - p.y) < 10) {
        tooltip.textContent = "Configuración cultural individual del equipo.";
        tooltip.style.left = (e.clientX - rect.left) + "px";
        tooltip.style.top = (e.clientY - rect.top) + "px";
        tooltip.classList.add("visible");
        return;
      }
    }

    tooltip.classList.remove("visible");
  });

  /* ---------- DRAW GRID ---------- */

function drawQuadrant() {

  ctx.clearRect(0, 0, size, size);

  ctx.strokeStyle = "#e5e7eb";
  ctx.lineWidth = 1;

  // Líneas centrales
  ctx.beginPath();
  ctx.moveTo(center, 0);
  ctx.lineTo(center, size);
  ctx.stroke();

  ctx.beginPath();
  ctx.moveTo(0, center);
  ctx.lineTo(size, center);
  ctx.stroke();

  // ---- CULTURE LABELS INSIDE QUADRANTS ----

  ctx.fillStyle = "rgba(1,33,51,0.25)";
  ctx.font = "600 14px Gelica";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";

  // Superior izquierda
  ctx.fillText("Cultura colaborativa", center / 2, center / 2);

  // Superior derecha
  ctx.fillText("Cultura ágil", center + center / 2, center / 2);

  // Inferior izquierda
  ctx.fillText("Cultura jerárquica", center / 2, center + center / 2);

  // Inferior derecha
  ctx.fillText("Cultura orientada a resultados", center + center / 2, center + center / 2);
}
  /* ---------- UPDATE GREEN GROUP ---------- */

  function updateGreenPoints() {

    let centerX = 0;
    let centerY = 0;

    greenPoints.forEach(p => {
      centerX += p.x;
      centerY += p.y;
    });

    centerX /= greenPoints.length;
    centerY /= greenPoints.length;

    const cohesionStrength = 0.004;
    const maxRadius = 100;
    const returnStrength = 0.01;

    greenPoints.forEach((p, i) => {

      p.angle += Math.sin(performance.now() * 0.0006 + i) * 0.01;

      const baseSpeed = 0.9 + (i * 0.03);

      let vx = Math.cos(p.angle) * baseSpeed;
      let vy = Math.sin(p.angle) * baseSpeed;

      const dx = centerX - p.x;
      const dy = centerY - p.y;

      vx += dx * cohesionStrength;
      vy += dy * cohesionStrength;

      const dist = Math.hypot(dx, dy);

      if (dist > maxRadius) {
        vx += dx * returnStrength;
        vy += dy * returnStrength;
      }

      p.x += vx;
      p.y += vy;

      if (p.x < 30 || p.x > size - 30) p.angle = Math.PI - p.angle;
      if (p.y < 30 || p.y > size - 30) p.angle = -p.angle;
    });
  }

  /* ---------- UPDATE ORANGE ---------- */

  function updateOrangePoint() {
    if (mouseActive) return;

    orangePoint.x += orangePoint.vx;
    orangePoint.y += orangePoint.vy;

    if (orangePoint.x <= 10 || orangePoint.x >= size - 10)
      orangePoint.vx *= -1;

    if (orangePoint.y <= 10 || orangePoint.y >= size - 10)
      orangePoint.vy *= -1;
  }

  /* ---------- DRAW POINTS ---------- */

  function drawPoints() {
    greenPoints.forEach(p => {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 6, 0, Math.PI * 2);
      ctx.fillStyle = "#007a96";
      ctx.fill();
    });

    ctx.beginPath();
    ctx.arc(orangePoint.x, orangePoint.y, 10, 0, Math.PI * 2);
    ctx.fillStyle = "#ff9700";
    ctx.fill();
  }

  /* ---------- ALIGNMENT ---------- */

  function updateAlignment() {

    let centerX = 0;
    let centerY = 0;

    greenPoints.forEach(p => {
      centerX += p.x;
      centerY += p.y;
    });

    centerX /= greenPoints.length;
    centerY /= greenPoints.length;

    const dist = Math.hypot(
      orangePoint.x - centerX,
      orangePoint.y - centerY
    );

    if (dist < 70) {
      chip.textContent =
        "Cultura alineada: visión y equipo en coherencia";
      chip.classList.add("aligned");
      chip.classList.remove("misaligned");
    } else {
      chip.textContent =
        "Brecha cultural entre visión y realidad";
      chip.classList.add("misaligned");
      chip.classList.remove("aligned");
    }
  }

  /* ---------- LOOP ---------- */

  function animate() {
    if (isVisible) {
      drawQuadrant();
      updateGreenPoints();
      updateOrangePoint();
      drawPoints();
      updateAlignment();
    }
    requestAnimationFrame(animate);
  }

  animate();

/* =====================================================
   BENEFICIOS MEDIBLES — counter animation
===================================================== */

(function initBeneficios() {
  const section = document.querySelector('.beneficios');
  if (!section) return;

  const cards  = section.querySelectorAll('.beneficio-card');
  const values = section.querySelectorAll('.beneficio-value');

  /* Format a number with locale thousand separators (es-ES uses dots) */
  function formatNum(n, useSeparator) {
    const rounded = Math.round(n);
    if (!useSeparator) return rounded.toString();
    return rounded.toLocaleString('es-ES');
  }

  function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
  }

  function animateCounter(el) {
    const target      = parseFloat(el.dataset.count) || 0;
    const prefix      = el.dataset.prefix  || '';
    const suffix      = el.dataset.suffix  || '';
    const useSep      = !!el.dataset.separator;
    const duration    = 1600;
    const start       = performance.now();

    function step(now) {
      const t       = Math.min((now - start) / duration, 1);
      const eased   = easeOutCubic(t);
      el.textContent = prefix + formatNum(target * eased, useSep) + suffix;
      if (t < 1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
  }

  let triggered = false;

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (!entry.isIntersecting || triggered) return;
      triggered = true;

      /* Stagger card reveal */
      cards.forEach(function(card, i) {
        setTimeout(function() {
          card.classList.add('is-visible');
        }, i * 90);
      });

      /* Start counters (slight extra delay so they begin while cards are fading in) */
      values.forEach(function(el, i) {
        setTimeout(function() { animateCounter(el); }, i * 90 + 120);
      });

      observer.disconnect();
    });
  }, { threshold: 0.12 });

  observer.observe(section);
})();

/* =====================================================
   DIFERENCIADOR + CASOS — staggered reveal
===================================================== */

(function initDiferenciador() {
  const section = document.querySelector('.diferenciador');
  if (!section) return;

  const casosCards = section.querySelectorAll('.caso-card');

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (!entry.isIntersecting) return;
      casosCards.forEach(function(card, i) {
        setTimeout(function() {
          card.classList.add('is-visible');
        }, i * 110);
      });
      observer.disconnect();
    });
  }, { threshold: 0.08 });

  observer.observe(section);
})();

});