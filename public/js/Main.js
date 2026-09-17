// ─── NAVBAR SCROLL SHADOW ───
const nav = document.getElementById('navbar');

window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 20);
});

// ─── FADE-IN ON SCROLL FOR CARDS ───
const observer = new IntersectionObserver((entries) => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) {
      e.target.style.animation = `fadeSlideUp 0.6s ${i * 0.08}s ease both`;
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.feature-card, .step, .stat-card').forEach(el => {
  el.style.opacity = '0';
  observer.observe(el);
});