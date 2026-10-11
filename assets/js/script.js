

window.addEventListener("scroll", function () {

    const header = document.querySelector("header");

    if (window.scrollY > 60) {

        header.classList.add("sticky");

    } else {

        header.classList.remove("sticky");

    }

});



document.querySelectorAll('a[href^="#"]').forEach(anchor => {

    anchor.addEventListener("click", function (e) {

        e.preventDefault();

        document.querySelector(this.getAttribute("href"))
            .scrollIntoView({
                behavior: "smooth"
            });

    });

});




const observer = new IntersectionObserver((entries) => {

    entries.forEach(entry => {

        if (entry.isIntersecting) {

            entry.target.classList.add("show");

        }

    });

}, {

    threshold: 0.15

});


document.querySelectorAll("section").forEach(sec => {

    sec.classList.add("hidden");

    observer.observe(sec);

});




const counters = document.querySelectorAll(".stat-item h3");

counters.forEach(counter => {

    const updateCounter = () => {

        const target = Number(counter.innerText.replace(/\D/g, ""));

        const count = Number(counter.getAttribute("data-count")) || 0;

        const increment = target / 80;

        if (count < target) {

            counter.setAttribute("data-count", Math.ceil(count + increment));

            counter.innerText = Math.ceil(count + increment) + "+";

            setTimeout(updateCounter, 20);

        }

        else {

            counter.innerText = target + "+";

        }

    }

    updateCounter();

});




const scrollBtn = document.createElement("button");

scrollBtn.innerHTML = "↑";

scrollBtn.id = "scrollTop";

document.body.appendChild(scrollBtn);


window.addEventListener("scroll", () => {

    if (window.scrollY > 500)

        scrollBtn.classList.add("active");

    else

        scrollBtn.classList.remove("active");

});


scrollBtn.onclick = () => {

    window.scrollTo({

        top: 0,

        behavior: "smooth"

    });

};



const links = document.querySelectorAll("nav a");

links.forEach(link => {
    if (link.href === window.location.href)
        link.classList.add("active");
});

// Patient User Account Dropdown
function toggleUserDropdown(event) {
    if (event) event.stopPropagation();
    const btn = document.getElementById('userMenuBtn');
    const panel = document.getElementById('userDropdownPanel');
    if (!panel) return;
    
    const isOpen = panel.classList.toggle('show');
    if (btn) {
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}

// Mobile Slideout Navigation
function toggleMobileNav(event) {
    if (event) event.stopPropagation();
    const btn = document.getElementById('mobileMenuToggle');
    const panel = document.getElementById('mobileNavPanel');
    const backdrop = document.getElementById('mobileNavBackdrop');
    if (!panel || !backdrop) return;

    const isOpen = panel.classList.toggle('open');
    backdrop.classList.toggle('open');
    if (btn) {
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}

function closeMobileNav() {
    const btn = document.getElementById('mobileMenuToggle');
    const panel = document.getElementById('mobileNavPanel');
    const backdrop = document.getElementById('mobileNavBackdrop');
    if (panel) panel.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}

// Close dropdowns on outside click or Esc
window.addEventListener('click', (e) => {
    const dropdown = document.getElementById('userDropdownPanel');
    const userBtn = document.getElementById('userMenuBtn');
    if (dropdown && dropdown.classList.contains('show')) {
        if (!dropdown.contains(e.target) && (!userBtn || !userBtn.contains(e.target))) {
            dropdown.classList.remove('show');
            if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
        }
    }
});

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const dropdown = document.getElementById('userDropdownPanel');
        const userBtn = document.getElementById('userMenuBtn');
        if (dropdown) {
            dropdown.classList.remove('show');
            if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
        }
        closeMobileNav();
    }
});