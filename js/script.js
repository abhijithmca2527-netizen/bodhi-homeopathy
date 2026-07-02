/*=========================================
BODHI HOMEOPATHY
Modern JavaScript
==========================================*/


//=============================
// Sticky Navbar
//=============================

window.addEventListener("scroll", function () {

    const header = document.querySelector("header");

    if (window.scrollY > 60) {

        header.classList.add("sticky");

    } else {

        header.classList.remove("sticky");

    }

});


//=============================
// Smooth Scroll
//=============================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {

    anchor.addEventListener("click", function (e) {

        e.preventDefault();

        document.querySelector(this.getAttribute("href"))
            .scrollIntoView({
                behavior: "smooth"
            });

    });

});


//=============================
// Fade Animation
//=============================

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


//=============================
// Counter Animation
//=============================

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


//=============================
// Scroll To Top
//=============================

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


//=============================
// Active Navigation
//=============================

const links = document.querySelectorAll("nav a");

links.forEach(link => {

    if (link.href === window.location.href)

        link.classList.add("active");

});