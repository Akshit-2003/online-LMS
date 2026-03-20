let slideIndex = 0;
showSlides();

function showSlides() {
  let i;
  let slides = document.getElementsByClassName("mySlides");
  if (!slides || slides.length === 0) return;
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slides.length) {slideIndex = 1}
  slides[slideIndex-1].style.display = "block";
  setTimeout(showSlides, 4000); // Change image every 4 seconds
}

// --- Dashboard Widget Animation ---
document.addEventListener("DOMContentLoaded", () => {
    // Function to animate the count-up effect
    const animateValue = (element, start, end, duration) => {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            element.textContent = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    // Find all widgets with a data-target attribute
    const widgets = document.querySelectorAll('.widget-data[data-target]');
    
    widgets.forEach(widget => {
        const target = parseInt(widget.getAttribute('data-target'), 10);
        // Only animate if it's a number
        if (!isNaN(target)) {
            // animate when widget appears (handled by observer below)
            widget.dataset.countTo = target;
            widget.textContent = '0';
        }
    });

    // prepare progress bars: capture intended width and collapse
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(pb => {
        // store intended width from inline style if present or compute percentage
        let intended = pb.style.width || pb.getAttribute('data-width') || '';
        if (!intended || intended.trim() === '') {
            // compute % from computed width relative to parent
            const parentW = pb.parentElement ? pb.parentElement.clientWidth : pb.clientWidth;
            const computedPx = parseFloat(getComputedStyle(pb).width) || 0;
            if (parentW > 0) {
                intended = Math.round((computedPx / parentW) * 100) + '%';
            } else {
                intended = '100%';
            }
        }
        pb.dataset.targetWidth = intended;
        // collapse for animation start
        pb.style.width = '0%';
    });

    // IntersectionObserver to reveal items and trigger animations
    const observerOptions = { root: null, rootMargin: '0px', threshold: 0.12 };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const el = entry.target;
            if (entry.isIntersecting) {
                el.classList.add('in-view');
                // if widget-data counter found inside, trigger count
                const counter = el.querySelector && el.querySelector('.widget-data[data-target]');
                if (counter && counter.dataset.countTo && !counter.dataset.animated) {
                    animateValue(counter, 0, parseInt(counter.dataset.countTo,10) || 0, 1400);
                    counter.dataset.animated = '1';
                }
                // progress bar(s)
                const bars = el.querySelectorAll ? el.querySelectorAll('.progress-bar') : [];
                bars.forEach(b => {
                    if (!b.dataset.animated) {
                        b.style.width = b.dataset.targetWidth || b.style.width || '100%';
                        b.dataset.animated = '1';
                    }
                });
                // also individual elements (if they themselves are progress bars / counters)
                if (el.classList.contains('progress-bar') && !el.dataset.animated) {
                    el.style.width = el.dataset.targetWidth || el.style.width || '100%';
                    el.dataset.animated = '1';
                }
                // small one-time visual effect for status badges
                const status = el.querySelector && el.querySelector('.course-status');
                if (status && !status.dataset.animated) {
                    status.classList.add('in-view');
                    status.dataset.animated = '1';
                }
                // reveal nav items gracefully
                if (el.matches && el.matches('.navbar')) {
                    document.querySelectorAll('.nav-links li').forEach((li, idx) => {
                        setTimeout(()=> li.classList.add('in-view'), idx * 70);
                    });
                }
            }
        });
    }, observerOptions);

    // observe target selectors
    const targets = document.querySelectorAll('.dashboard-header, .dashboard-widget, .course-card, .recommend-card, .profile-section, .settings-section, .courses-section, .main-header, .container, .footer, .nav-links li, .header-image img, .cert-card, .stat-card');
    targets.forEach(t => observer.observe(t));

    // Also observe entire navbar to trigger nav animation
    const navbar = document.querySelector('.navbar');
    if (navbar) observer.observe(navbar);

    // fallback: if IntersectionObserver not supported, reveal everything
    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.course-card, .dashboard-widget, .recommend-card, .profile-section, .settings-section, .courses-section, .main-header, .container, .footer, .nav-links li').forEach(el => {
            el.classList.add('in-view');
        });
        // animate progress bars and counters immediately
        document.querySelectorAll('.progress-bar').forEach(pb => {
            pb.style.width = pb.dataset.targetWidth || pb.style.width || '100%';
        });
        document.querySelectorAll('.widget-data[data-target]').forEach(w => {
            animateValue(w, 0, parseInt(w.dataset.target,10) || 0, 1400);
        });
    }
});