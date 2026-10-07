/**
 * PLAY-ON KIDS PLAYGROUND - INTERACTIVE SCRIPTS & GSAP ANIMATIONS
 * Mombasa Mall, 2nd Floor, Mwembe Tayari, Mombasa, Kenya
 * Fully optimized, smooth scrolling, GSAP ScrollTrigger, Lenis & Confetti
 */

document.addEventListener('DOMContentLoaded', () => {
  // Check for reduced motion preference
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------------------
     1. Lenis Smooth Scrolling Setup & GSAP Sync
     ------------------------------------------------------------------------ */
  let lenis = null;
  if (!prefersReducedMotion && typeof Lenis !== 'undefined') {
    try {
      lenis = new Lenis({
        duration: 1.1,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smoothWheel: true,
        touchMultiplier: 1.3,
        infinite: false,
      });

      // Synchronize Lenis with GSAP ScrollTrigger single RAF ticker
      if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        lenis.on('scroll', ScrollTrigger.update);
        gsap.ticker.add((time) => {
          lenis.raf(time * 1000);
        });
        gsap.ticker.lagSmoothing(0);
      } else {
        function raf(time) {
          lenis.raf(time);
          requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);
      }
    } catch (e) {
      console.warn('Lenis smooth scroll initialization skipped:', e);
    }
  }

  /* ------------------------------------------------------------------------
     2. Custom Playful Cursor & Follower (Desktop Only)
     ------------------------------------------------------------------------ */
  const cursor = document.querySelector('.custom-cursor');
  const cursorFollower = document.querySelector('.custom-cursor-follower');

  if (cursor && cursorFollower && !prefersReducedMotion && window.innerWidth > 992) {
    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let followerX = mouseX;
    let followerY = mouseY;

    window.addEventListener('mousemove', (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
      cursor.style.left = `${mouseX}px`;
      cursor.style.top = `${mouseY}px`;
    });

    function updateFollower() {
      followerX += (mouseX - followerX) * 0.18;
      followerY += (mouseY - followerY) * 0.18;
      cursorFollower.style.left = `${followerX}px`;
      cursorFollower.style.top = `${followerY}px`;
      requestAnimationFrame(updateFollower);
    }
    requestAnimationFrame(updateFollower);

    // Hover effects on interactive elements
    const interactiveElements = document.querySelectorAll(
      'a, button, input, select, textarea, .gallery-item, .activity-card, .feature-card, .pricing-card, .package-card, .social-btn'
    );
    interactiveElements.forEach((el) => {
      el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
      el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
    });
  }

  /* ------------------------------------------------------------------------
     3. Scroll Progress Track & Bouncing Go-Kart
     ------------------------------------------------------------------------ */
  const progressBar = document.querySelector('.scroll-progress-bar');
  const backToTopBtn = document.getElementById('backToTop');
  const header = document.querySelector('header');

  window.addEventListener('scroll', () => {
    const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
    if (scrollHeight > 0 && progressBar) {
      const progress = Math.min(100, Math.max(0, (window.scrollY / scrollHeight) * 100));
      progressBar.style.width = `${progress}%`;
    }

    // Sticky Header Class
    if (header) {
      if (window.scrollY > 40) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }

    // Back to top button visibility
    if (backToTopBtn) {
      if (window.scrollY > 350) {
        backToTopBtn.classList.add('is-visible');
      } else {
        backToTopBtn.classList.remove('is-visible');
      }
    }
  });

  if (backToTopBtn) {
    backToTopBtn.addEventListener('click', () => {
      if (lenis) {
        lenis.scrollTo(0, { duration: 1.2 });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /* ------------------------------------------------------------------------
     4. Smooth Navigation Links (Anchor Scrolling) & Active ScrollSpy
     ------------------------------------------------------------------------ */
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId === '#') return;

      if (targetId === '#top') {
        e.preventDefault();
        if (lenis) lenis.scrollTo(0);
        else window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      const targetEl = document.querySelector(targetId);
      if (targetEl) {
        e.preventDefault();
        const headerOffset = 75;
        const elementPosition = targetEl.getBoundingClientRect().top + window.scrollY;
        const offsetPosition = elementPosition - headerOffset;

        if (lenis) {
          lenis.scrollTo(offsetPosition, { duration: 1.0 });
        } else {
          window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth',
          });
        }
      }
    });
  });

  // Active Nav ScrollSpy
  const navLinks = document.querySelectorAll('nav.desktop-nav a[href^="#"]');
  const spySections = document.querySelectorAll('section[id], footer[id]');

  window.addEventListener('scroll', () => {
    let currentId = '';
    spySections.forEach((section) => {
      const sectionTop = section.offsetTop - 120;
      const sectionHeight = section.offsetHeight;
      if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
        currentId = '#' + section.getAttribute('id');
      }
    });

    if (window.scrollY < 200) {
      currentId = '#top';
    }

    navLinks.forEach((link) => {
      link.classList.remove('active');
      if (link.getAttribute('href') === currentId) {
        link.classList.add('active');
      }
    });
  });

  /* ------------------------------------------------------------------------
     5. Mobile Navigation Toggle Drawer & Gestures
     ------------------------------------------------------------------------ */
  const menuBtn = document.getElementById('menuBtn');
  const mobileNavDrawer = document.getElementById('mobileNavDrawer');
  const mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
  const mobileDrawerCloseBtn = document.getElementById('mobileDrawerCloseBtn');

  function openDrawer() {
    if (menuBtn) menuBtn.classList.add('is-active');
    if (mobileNavDrawer) {
      mobileNavDrawer.classList.add('is-open');
      mobileNavDrawer.setAttribute('aria-hidden', 'false');
    }
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (menuBtn) menuBtn.classList.remove('is-active');
    if (mobileNavDrawer) {
      mobileNavDrawer.classList.remove('is-open');
      mobileNavDrawer.setAttribute('aria-hidden', 'true');
    }
    document.body.style.overflow = '';
  }

  if (menuBtn && mobileNavDrawer) {
    menuBtn.addEventListener('click', () => {
      if (mobileNavDrawer.classList.contains('is-open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });

    if (mobileDrawerCloseBtn) {
      mobileDrawerCloseBtn.addEventListener('click', closeDrawer);
    }

    mobileNavLinks.forEach((link) => {
      link.addEventListener('click', closeDrawer);
    });

    // Close when tapping outside links on drawer background
    mobileNavDrawer.addEventListener('click', (e) => {
      if (e.target === mobileNavDrawer) {
        closeDrawer();
      }
    });
  }

  /* ------------------------------------------------------------------------
     6. GSAP Animations & ScrollTrigger Reveals
     ------------------------------------------------------------------------ */
  if (typeof gsap !== 'undefined') {
    if (typeof ScrollTrigger !== 'undefined') {
      gsap.registerPlugin(ScrollTrigger);
    }

    // Hero Letter Split
    const heroTitle = document.querySelector('.hero-title');
    if (heroTitle) {
      const text = heroTitle.textContent.trim();
      heroTitle.innerHTML = '';
      text.split('').forEach((char) => {
        const span = document.createElement('span');
        span.className = 'char';
        span.innerHTML = char === ' ' ? '&nbsp;' : char;
        heroTitle.appendChild(span);
      });

      if (!prefersReducedMotion) {
        // Pop in letters with elastic bounce
        gsap.from('.hero-title .char', {
          y: 60,
          opacity: 0,
          scale: 0.3,
          rotation: () => gsap.utils.random(-20, 20),
          stagger: 0.04,
          duration: 1.0,
          ease: 'elastic.out(1.2, 0.45)',
          clearProps: 'opacity,transform',
          onComplete: () => {
            // Gentle continuous floating loop
            gsap.to('.hero-title .char', {
              y: -6,
              duration: 1.8,
              repeat: -1,
              yoyo: true,
              ease: 'sine.inOut',
              stagger: {
                each: 0.08,
                from: 'random',
              },
            });
          },
        });

        // Hero Tagline Word Split
        const heroTagline = document.querySelector('.hero-tagline');
        if (heroTagline) {
          const taglineText = heroTagline.textContent.trim();
          heroTagline.innerHTML = '';
          taglineText.split(' ').forEach((word) => {
            const span = document.createElement('span');
            span.className = 'word';
            span.textContent = word;
            heroTagline.appendChild(span);
          });

          gsap.from('.hero-tagline .word', {
            y: 20,
            opacity: 0,
            scale: 0.9,
            stagger: 0.07,
            duration: 0.75,
            delay: 0.6,
            ease: 'power3.out',
            clearProps: 'opacity,transform',
          });
        }

        // Hero CTAs & Stat Pills
        gsap.from('.hero-ctas .btn', {
          scale: 0.6,
          opacity: 0,
          y: 25,
          stagger: 0.12,
          duration: 0.8,
          delay: 0.9,
          ease: 'back.out(1.8)',
          clearProps: 'opacity,transform',
        });

        gsap.from('.hero-stat-pill', {
          y: 35,
          opacity: 0,
          stagger: 0.1,
          duration: 0.75,
          delay: 1.1,
          ease: 'power3.out',
          clearProps: 'opacity,transform',
        });
      }
    }

    // ScrollTrigger Safe Reveal Animations
    if (!prefersReducedMotion && typeof ScrollTrigger !== 'undefined') {
      // Section Headers
      document.querySelectorAll('.section-header').forEach((header) => {
        gsap.from(header.children, {
          scrollTrigger: {
            trigger: header,
            start: 'top 88%',
            once: true,
          },
          y: 30,
          opacity: 0,
          stagger: 0.12,
          duration: 0.7,
          ease: 'back.out(1.4)',
          clearProps: 'opacity,transform',
        });
      });

      // Feature Cards Stagger
      gsap.from('.feature-card', {
        scrollTrigger: {
          trigger: '.features-grid',
          start: 'top 85%',
          once: true,
        },
        y: 40,
        opacity: 0,
        scale: 0.94,
        stagger: 0.1,
        duration: 0.7,
        ease: 'back.out(1.5)',
        clearProps: 'opacity,transform',
      });

      // Activity Cards Stagger
      gsap.from('.activity-card', {
        scrollTrigger: {
          trigger: '.activities-grid',
          start: 'top 85%',
          once: true,
        },
        y: 45,
        opacity: 0,
        scale: 0.95,
        stagger: 0.08,
        duration: 0.65,
        ease: 'power3.out',
        clearProps: 'opacity,transform',
      });

      // Pricing Cards Flip In & Trigger Counter
      gsap.from('.pricing-card', {
        scrollTrigger: {
          trigger: '.pricing-grid',
          start: 'top 85%',
          once: true,
          onEnter: animatePricingCounters,
        },
        y: 45,
        opacity: 0,
        stagger: 0.15,
        duration: 0.8,
        ease: 'back.out(1.4)',
        clearProps: 'opacity,transform',
      });

      // Package Cards Stagger
      gsap.from('.package-card', {
        scrollTrigger: {
          trigger: '.packages-grid',
          start: 'top 85%',
          once: true,
        },
        y: 40,
        opacity: 0,
        stagger: 0.12,
        duration: 0.75,
        ease: 'back.out(1.5)',
        clearProps: 'opacity,transform',
      });

      // Gallery Items Stagger
      gsap.from('.gallery-item', {
        scrollTrigger: {
          trigger: '.gallery-grid',
          start: 'top 88%',
          once: true,
        },
        y: 35,
        opacity: 0,
        scale: 0.92,
        stagger: 0.07,
        duration: 0.65,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      });

      // Video Section Cards
      gsap.from('.video-card', {
        scrollTrigger: {
          trigger: '.video-grid',
          start: 'top 85%',
          once: true,
        },
        y: 40,
        opacity: 0,
        stagger: 0.15,
        duration: 0.75,
        ease: 'power3.out',
        clearProps: 'opacity,transform',
      });

      // Parallax on Floating Balloons
      document.querySelectorAll('.balloon').forEach((balloon, i) => {
        gsap.to(balloon, {
          scrollTrigger: {
            trigger: 'body',
            start: 'top top',
            end: 'bottom bottom',
            scrub: 1.2,
          },
          y: (i % 2 === 0 ? -120 : 120),
          rotation: (i % 2 === 0 ? 15 : -15),
          ease: 'none',
        });
      });
    } else {
      // If motion reduced or ScrollTrigger unavailable, trigger counters immediately
      animatePricingCounters();
    }
  } else {
    animatePricingCounters();
  }

  // Refresh ScrollTrigger after all assets and fonts finish loading
  window.addEventListener('load', () => {
    if (typeof ScrollTrigger !== 'undefined') {
      ScrollTrigger.refresh();
    }
  });

  /* ------------------------------------------------------------------------
     7. Pricing Numbers Count-Up Animation
     ------------------------------------------------------------------------ */
  let countersAnimated = false;
  function animatePricingCounters() {
    if (countersAnimated) return;
    countersAnimated = true;

    const counters = document.querySelectorAll('.count-up');
    counters.forEach((counter) => {
      const target = +counter.getAttribute('data-target');
      if (!target) return;

      let current = 0;
      const step = Math.ceil(target / 30);
      const timer = setInterval(() => {
        current += step;
        if (current >= target) {
          counter.textContent = target.toLocaleString();
          clearInterval(timer);
        } else {
          counter.textContent = current.toLocaleString();
        }
      }, 25);
    });
  }

  /* ------------------------------------------------------------------------
     8. Live Mombasa Center Status (EAT Time Calculation UTC+3)
     ------------------------------------------------------------------------ */
  function updateLiveStatus() {
    const statusTextEl = document.getElementById('liveStatusText');
    const statusDescEl = document.getElementById('liveStatusDesc');
    const statusDot = document.getElementById('liveStatusDot');

    if (!statusTextEl || !statusDescEl) return;

    // Get current time in East Africa Time (UTC+3)
    const now = new Date();
    const utcHours = now.getUTCHours();
    const utcMinutes = now.getUTCMinutes();
    const utcDay = now.getUTCDay();

    // EAT is UTC + 3 hours
    let eatHours = utcHours + 3;
    let eatDay = utcDay;
    if (eatHours >= 24) {
      eatHours -= 24;
      eatDay = (eatDay + 1) % 7;
    }

    const currentDecTime = eatHours + utcMinutes / 60;
    let isOpen = false;
    let currentSession = '';
    let nextMessage = '';

    if (eatDay === 1) {
      // Monday - Closed
      isOpen = false;
      statusTextEl.textContent = 'Closed Today (Mondays)';
      statusDescEl.textContent = 'We are closed Mondays for deep hygiene sanitization (except public/school holidays). Opens Tuesday at 2:00 PM!';
      if (statusDot) statusDot.style.backgroundColor = '#FF3366';
    } else if (eatDay >= 2 && eatDay <= 5) {
      // Tuesday to Friday (Weekdays)
      // Session 1: 14:00 - 16:30 | Session 2: 17:00 - 20:00
      if (currentDecTime >= 14.0 && currentDecTime < 16.5) {
        isOpen = true;
        currentSession = 'Session 1 in progress (2:00 PM – 4:30 PM)';
      } else if (currentDecTime >= 17.0 && currentDecTime < 20.0) {
        isOpen = true;
        currentSession = 'Session 2 in progress (5:00 PM – 8:00 PM)';
      } else if (currentDecTime < 14.0) {
        nextMessage = 'Opens today at 2:00 PM (Session 1: 2:00 PM – 4:30 PM)';
      } else if (currentDecTime >= 16.5 && currentDecTime < 17.0) {
        nextMessage = 'Between sessions. Next Session 2 starts at 5:00 PM!';
      } else {
        nextMessage = 'Closed for the night. Next session opens tomorrow at 2:00 PM!';
      }
    } else {
      // Saturday & Sunday (Weekends)
      // Session 1: 11:00 - 13:00 | Session 2: 14:00 - 16:00 | Session 3: 16:30 - 18:30 | Session 4: 19:00 - 21:00
      if (currentDecTime >= 11.0 && currentDecTime < 13.0) {
        isOpen = true;
        currentSession = 'Weekend Session 1 in progress (11:00 AM – 1:00 PM)';
      } else if (currentDecTime >= 14.0 && currentDecTime < 16.0) {
        isOpen = true;
        currentSession = 'Weekend Session 2 in progress (2:00 PM – 4:00 PM)';
      } else if (currentDecTime >= 16.5 && currentDecTime < 18.5) {
        isOpen = true;
        currentSession = 'Weekend Session 3 in progress (4:30 PM – 6:30 PM)';
      } else if (currentDecTime >= 19.0 && currentDecTime < 21.0) {
        isOpen = true;
        currentSession = 'Weekend Night Session 4 in progress (7:00 PM – 9:00 PM)';
      } else if (currentDecTime < 11.0) {
        nextMessage = 'Opens today at 11:00 AM (Session 1: 11:00 AM – 1:00 PM)';
      } else if (currentDecTime >= 13.0 && currentDecTime < 14.0) {
        nextMessage = 'Session 2 starts at 2:00 PM!';
      } else if (currentDecTime >= 16.0 && currentDecTime < 16.5) {
        nextMessage = 'Session 3 starts at 4:30 PM!';
      } else if (currentDecTime >= 18.5 && currentDecTime < 19.0) {
        nextMessage = 'Night Session 4 starts at 7:00 PM!';
      } else {
        nextMessage = eatDay === 0 ? 'Closed for the night. Reopens Tuesday at 2:00 PM!' : 'Closed for the night. Reopens Sunday at 11:00 AM!';
      }
    }

    if (eatDay !== 1) {
      if (isOpen) {
        statusTextEl.textContent = '🎉 Open Now in Mombasa Mall!';
        statusDescEl.textContent = `${currentSession} — Walk-ins & bookings welcome!`;
        if (statusDot) statusDot.style.backgroundColor = '#2DD375';
      } else {
        statusTextEl.textContent = '🕒 Closed Right Now';
        statusDescEl.textContent = nextMessage;
        if (statusDot) statusDot.style.backgroundColor = '#FFD400';
      }
    }
  }
  updateLiveStatus();
  setInterval(updateLiveStatus, 30000);

  /* ------------------------------------------------------------------------
     9. Activities Filter Tabs
     ------------------------------------------------------------------------ */
  const filterBtns = document.querySelectorAll('.filter-btn');
  const activityCards = document.querySelectorAll('.activity-card');

  filterBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      filterBtns.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');

      const filterValue = btn.getAttribute('data-filter');

      activityCards.forEach((card) => {
        const category = card.getAttribute('data-category') || '';
        if (filterValue === 'all' || category.includes(filterValue)) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });

      if (typeof ScrollTrigger !== 'undefined') {
        ScrollTrigger.refresh();
      }
    });
  });

  /* ------------------------------------------------------------------------
     10. 3D Tilt Effect on Cards (Desktop Only)
     ------------------------------------------------------------------------ */
  if (!prefersReducedMotion && window.innerWidth > 992) {
    const tiltCards = document.querySelectorAll('.activity-card, .feature-card, .pricing-card, .package-card');
    tiltCards.forEach((card) => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = ((y - centerY) / centerY) * -6;
        const rotateY = ((x - centerX) / centerX) * 6;

        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px)`;
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
      });
    });
  }

  /* ------------------------------------------------------------------------
     11. Interactive Booking Engine & Dynamic Live Calculator
     ------------------------------------------------------------------------ */
  const bookingDateInput = document.getElementById('bookingDate');
  const bookingSessionSelect = document.getElementById('bookingSession');
  const bookingTypeSelect = document.getElementById('bookingType');
  const childCountVal = document.getElementById('childCount');
  const adultCountVal = document.getElementById('adultCount');
  const estTotalEl = document.getElementById('estimatedTotal');

  // Set min date to today
  if (bookingDateInput) {
    const today = new Date().toISOString().split('T')[0];
    bookingDateInput.min = today;
    bookingDateInput.value = today;
  }

  // Session options definition
  const weekdaySessions = [
    { val: 'Session 1 (2:00pm - 4:30pm)', label: 'Session 1: 2:00 PM – 4:30 PM (Afternoon)' },
    { val: 'Session 2 (5:00pm - 8:00pm)', label: 'Session 2: 5:00 PM – 8:00 PM (Evening Glow)' },
  ];

  const weekendSessions = [
    { val: 'Session 1 (11:00am - 1:00pm)', label: 'Session 1: 11:00 AM – 1:00 PM (Morning Kickoff)' },
    { val: 'Session 2 (2:00pm - 4:00pm)', label: 'Session 2: 2:00 PM – 4:00 PM (Peak Fun)' },
    { val: 'Session 3 (4:30pm - 6:30pm)', label: 'Session 3: 4:30 PM – 6:30 PM (Twilight Jump)' },
    { val: 'Session 4 (7:00pm - 9:00pm)', label: 'Session 4: 7:00 PM – 9:00 PM (Night Lights)' },
  ];

  function populateSessionOptions() {
    if (!bookingDateInput || !bookingSessionSelect) return;
    const selectedDate = new Date(bookingDateInput.value + 'T00:00:00');
    const day = selectedDate.getDay(); // 0 = Sun, 6 = Sat, 1 = Mon

    bookingSessionSelect.innerHTML = '';

    if (day === 1) {
      // Monday
      const opt = document.createElement('option');
      opt.value = 'Holiday Session (Monday Booking)';
      opt.textContent = 'Mondays are closed unless Public/School Holiday';
      bookingSessionSelect.appendChild(opt);
    } else if (day >= 2 && day <= 5) {
      // Weekdays
      weekdaySessions.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s.val;
        opt.textContent = s.label;
        bookingSessionSelect.appendChild(opt);
      });
    } else {
      // Weekends
      weekendSessions.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s.val;
        opt.textContent = s.label;
        bookingSessionSelect.appendChild(opt);
      });
    }
    calculateLivePrice();
  }

  if (bookingDateInput) {
    bookingDateInput.addEventListener('change', populateSessionOptions);
    populateSessionOptions();
  }

  // Stepper handlers (+ / - buttons)
  document.querySelectorAll('.stepper-btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = btn.getAttribute('data-target');
      const isPlus = btn.classList.contains('plus');
      const valEl = document.getElementById(targetId);
      if (!valEl) return;

      let current = parseInt(valEl.textContent) || 0;
      if (isPlus) {
        current = Math.min(30, current + 1);
      } else {
        const min = targetId === 'childCount' ? 1 : 0;
        current = Math.max(min, current - 1);
      }
      valEl.textContent = current;
      calculateLivePrice();
    });
  });

  function calculateLivePrice() {
    if (!estTotalEl) return;
    const bookingType = bookingTypeSelect ? bookingTypeSelect.value : 'session';
    const numKids = parseInt(childCountVal ? childCountVal.textContent : 1) || 1;
    const numAdults = parseInt(adultCountVal ? adultCountVal.textContent : 0) || 0;

    let childPrice = 1000;
    let adultAddonPrice = 500;

    if (bookingDateInput && bookingDateInput.value) {
      const day = new Date(bookingDateInput.value + 'T00:00:00').getDay();
      if (day === 0 || day === 6) {
        // Weekend pricing
        childPrice = 1500;
        adultAddonPrice = 500;
      } else {
        // Weekday pricing
        childPrice = 1000;
        adultAddonPrice = 500;
      }
    }

    if (bookingType === 'birthday') {
      estTotalEl.textContent = 'Custom Party Quote';
      return;
    } else if (bookingType === 'school') {
      estTotalEl.textContent = 'Special Group Discount';
      return;
    } else if (bookingType === 'private') {
      estTotalEl.textContent = 'VIP Buyout Quote';
      return;
    }

    const total = (numKids * childPrice) + (numAdults * adultAddonPrice);
    estTotalEl.textContent = `KES ${total.toLocaleString()}`;
  }

  if (bookingTypeSelect) {
    bookingTypeSelect.addEventListener('change', calculateLivePrice);
  }

  // Package Card Quick Select Handler
  window.selectPackage = function(packageType) {
    if (bookingTypeSelect) {
      bookingTypeSelect.value = packageType;
      calculateLivePrice();
    }
    const bookingSection = document.getElementById('booking');
    if (bookingSection) {
      if (lenis) {
        lenis.scrollTo(bookingSection, { offset: -60, duration: 1.2 });
      } else {
        bookingSection.scrollIntoView({ behavior: 'smooth' });
      }
    }
  };

  /* ------------------------------------------------------------------------
     12. Booking Form Submit & Multi-Burst Confetti Celebration
     ------------------------------------------------------------------------ */
  const bookingForm = document.getElementById('bookingForm');
  const confirmationModal = document.getElementById('confirmationModal');
  const modalSummary = document.getElementById('modalSummary');
  const modalWhatsappBtn = document.getElementById('modalWhatsappBtn');
  const modalCloseBtn = document.getElementById('modalCloseBtn');

  if (bookingForm) {
    bookingForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const name = document.getElementById('parentName').value;
      const phone = document.getElementById('parentPhone').value;
      const email = document.getElementById('parentEmail').value;
      const date = document.getElementById('bookingDate').value;
      const session = document.getElementById('bookingSession').value;
      const bType = document.getElementById('bookingType').value;
      const kids = childCountVal ? childCountVal.textContent : '1';
      const adults = adultCountVal ? adultCountVal.textContent : '0';
      const message = document.getElementById('specialRequests') ? document.getElementById('specialRequests').value : '';
      const totalCost = estTotalEl ? estTotalEl.textContent : 'KES 1,000';

      // Confetti Blast
      if (typeof confetti !== 'undefined') {
        const count = 220;
        const defaults = {
          origin: { y: 0.65 },
          colors: ['#00A651', '#FFD400', '#4FB3BF', '#FF6B35', '#FF3366', '#7B2CBF'],
        };

        function fire(particleRatio, opts) {
          confetti(Object.assign({}, defaults, opts, {
            particleCount: Math.floor(count * particleRatio),
          }));
        }

        fire(0.25, { spread: 26, startVelocity: 55 });
        fire(0.2, { spread: 60 });
        fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
        fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
        fire(0.1, { spread: 120, startVelocity: 45 });
      }

      // Populate Modal Summary
      if (modalSummary) {
        modalSummary.innerHTML = `
          <p><strong>Parent / Guardian:</strong> ${name}</p>
          <p><strong>Phone / WhatsApp:</strong> ${phone}</p>
          <p><strong>Booking Type:</strong> ${bType.toUpperCase()}</p>
          <p><strong>Visit Date:</strong> ${date} (${session})</p>
          <p><strong>Guests:</strong> ${kids} Child(ren), ${adults} Adult(s)</p>
          <p><strong>Estimated Total:</strong> ${totalCost}</p>
          ${message ? `<p><strong>Special Request:</strong> ${message}</p>` : ''}
        `;
      }

      // Format WhatsApp prefilled message
      const waText = encodeURIComponent(
        `🎈 *NEW PLAY-ON MOMBASA BOOKING REQUEST*\n\n` +
        `👤 *Name:* ${name}\n` +
        `📞 *Phone:* ${phone}\n` +
        `✉️ *Email:* ${email}\n` +
        `🎪 *Type:* ${bType.toUpperCase()}\n` +
        `📅 *Date:* ${date}\n` +
        `🕒 *Session:* ${session}\n` +
        `👶 *Kids:* ${kids} | 👨 *Adults:* ${adults}\n` +
        `💰 *Estimated Total:* ${totalCost}\n` +
        (message ? `📝 *Notes:* ${message}\n\n` : `\n`) +
        `Please confirm our reservation at Mombasa Mall 2nd Floor. Thank you!`
      );

      if (modalWhatsappBtn) {
        modalWhatsappBtn.href = `https://wa.me/254780611074?text=${waText}`;
      }

      // Show confirmation modal
      if (confirmationModal) {
        confirmationModal.classList.add('is-active');
      }
    });
  }

  if (modalCloseBtn && confirmationModal) {
    modalCloseBtn.addEventListener('click', () => {
      confirmationModal.classList.remove('is-active');
    });
  }

  if (confirmationModal) {
    confirmationModal.addEventListener('click', (e) => {
      if (e.target === confirmationModal) {
        confirmationModal.classList.remove('is-active');
      }
    });
  }

  /* ------------------------------------------------------------------------
     13. Interactive Gallery Lightbox
     ------------------------------------------------------------------------ */
  const lightboxModal = document.getElementById('lightboxModal');
  const lightboxImg = document.getElementById('lightboxImg');
  const lightboxCaption = document.getElementById('lightboxCaption');
  const lightboxCloseBtn = document.getElementById('lightboxCloseBtn');

  window.openLightbox = function(src, caption) {
    if (lightboxModal && lightboxImg) {
      lightboxImg.src = src;
      if (lightboxCaption) lightboxCaption.textContent = caption || 'Play-On Mombasa Fun Moment';
      lightboxModal.classList.add('is-open');
    }
  };

  if (lightboxCloseBtn && lightboxModal) {
    lightboxCloseBtn.addEventListener('click', () => {
      lightboxModal.classList.remove('is-open');
    });

    lightboxModal.addEventListener('click', (e) => {
      if (e.target === lightboxModal) {
        lightboxModal.classList.remove('is-open');
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && lightboxModal.classList.contains('is-open')) {
        lightboxModal.classList.remove('is-open');
      }
    });
  }

  /* ------------------------------------------------------------------------
     14. Testimonials Carousel Slider
     ------------------------------------------------------------------------ */
  const testimonialsTrack = document.querySelector('.testimonials-track');
  const prevTestimonialBtn = document.getElementById('prevTestimonial');
  const nextTestimonialBtn = document.getElementById('nextTestimonial');

  if (testimonialsTrack) {
    let currentIndex = 0;
    const cards = document.querySelectorAll('.testimonial-card');
    const totalCards = cards.length;

    function updateSlider() {
      if (!cards.length) return;
      const cardWidth = cards[0].offsetWidth + 32; // width + gap
      testimonialsTrack.style.transform = `translateX(-${currentIndex * cardWidth}px)`;
    }

    if (nextTestimonialBtn) {
      nextTestimonialBtn.addEventListener('click', () => {
        const visibleCards = window.innerWidth >= 768 ? 2 : 1;
        if (currentIndex < totalCards - visibleCards) {
          currentIndex++;
        } else {
          currentIndex = 0;
        }
        updateSlider();
      });
    }

    if (prevTestimonialBtn) {
      prevTestimonialBtn.addEventListener('click', () => {
        const visibleCards = window.innerWidth >= 768 ? 2 : 1;
        if (currentIndex > 0) {
          currentIndex--;
        } else {
          currentIndex = Math.max(0, totalCards - visibleCards);
        }
        updateSlider();
      });
    }

    // Auto-advance slider every 6 seconds
    setInterval(() => {
      if (nextTestimonialBtn) nextTestimonialBtn.click();
    }, 6000);

    window.addEventListener('resize', updateSlider);
  }

  /* ------------------------------------------------------------------------
     15. Newsletter Signup Celebration
     ------------------------------------------------------------------------ */
  const newsletterForm = document.querySelector('.footer-newsletter-form');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = newsletterForm.querySelector('input');
      if (input && input.value) {
        if (typeof confetti !== 'undefined') {
          confetti({
            particleCount: 80,
            spread: 60,
            origin: { y: 0.9 },
          });
        }
        alert('🎉 Asante! You are subscribed to Play-On VIP updates & discounts!');
        input.value = '';
      }
    });
  }
});
