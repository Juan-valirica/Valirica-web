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

    const cohesionStrength = 0.0025;
    const maxRadius = 140;
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

});