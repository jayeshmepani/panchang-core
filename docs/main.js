"use strict";
function initTheme() {
    const e = document.getElementById("themeToggle"),
        t = document.getElementById("mobileThemeToggle"),
        n = localStorage.getItem("theme"),
        o = window.matchMedia("(prefers-color-scheme: dark)").matches;
    (applyTheme(n || (o ? "dark" : "light"), !1),
        [e, t].forEach((e) => {
            e?.addEventListener("click", () => {
                applyTheme(
                    "dark" === document.documentElement.getAttribute("data-theme")
                        ? "light"
                        : "dark",
                );
            });
        }),
        window
            .matchMedia("(prefers-color-scheme: dark)")
            .addEventListener("change", (e) => {
                localStorage.getItem("theme") ||
                    applyTheme(e.matches ? "dark" : "light", !1);
            }));
}
function applyTheme(e, t = !0) {
    (document.documentElement.setAttribute("data-theme", e),
        t && localStorage.setItem("theme", e));
    const n = "dark" === e,
        o = n ? "Switch to light theme" : "Switch to dark theme";
    document.querySelectorAll("#themeToggle, #mobileThemeToggle").forEach((e) => {
        (e.setAttribute("aria-label", o),
            e.setAttribute("aria-pressed", String(n)));
        const t = e.querySelector(".theme-badge");
        t && (t.textContent = n ? "Dark" : "Light");
    });
    const r = document.querySelector(".theme-text");
    r && (r.textContent = n ? "Dark" : "Light");
}
function initNavigation() {
    const e = document.getElementById("sidebar"),
        t = document.getElementById("mobileSidebarToggle"),
        n = document.getElementById("sidebarOverlay"),
        o = document.querySelectorAll(".nav-link");
    const isMobileQuery = window.matchMedia("(max-width: 768px)");

    function r() {
        const isMobile = isMobileQuery.matches;
        e.classList.remove("open");
        n.classList.remove("active");
        t?.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
        if (isMobile) {
            e.setAttribute("inert", "");
        }
        t?.focus();
    }

    function i() {
        const isMobile = isMobileQuery.matches;
        if (isMobile && !e.classList.contains("open")) {
            e.setAttribute("inert", "");
        } else {
            e.removeAttribute("inert");
        }
    }
    (i(),
        window.addEventListener("resize", i, { passive: !0 }),
        t?.addEventListener("click", () => {
            e.classList.contains("open")
                ? r()
                : (e.classList.add("open"),
                    e.removeAttribute("inert"),
                    n.classList.add("active"),
                    t?.setAttribute("aria-expanded", "true"),
                    (document.body.style.overflow = "hidden"),
                    requestAnimationFrame(() => {
                        e.querySelector(".nav-link, #sidebarSearch")?.focus();
                    }));
        }),
        n?.addEventListener("click", r),
        document.addEventListener("keydown", (t) => {
            "Escape" === t.key && e.classList.contains("open") && r();
        }),
        o.forEach((e) => {
            e.addEventListener("click", (t) => {
                t.preventDefault();
                const n = e.getAttribute("href")?.slice(1);
                if (!n) return;
                const i = document.getElementById(n);
                if (i) {
                    o.forEach((e) => e.classList.remove("active"));
                    e.classList.add("active");
                    i.scrollIntoView({ behavior: "smooth", block: "start" });
                    if (isMobileQuery.matches) r();
                    history.pushState(null, "", `#${n}`);
                }
            });
        }));
    const a = window.location.hash.slice(1);
    if (a) {
        const e = document.getElementById(a);
        (e &&
            setTimeout(
                () => e.scrollIntoView({ behavior: "auto", block: "start" }),
                150,
            ),
            document
                .querySelector(`.nav-link[href="#${a}"]`)
                ?.classList.add("active"));
    }
    initScrollSpy();
}
function initScrollSpy() {
    const e = document.querySelectorAll(".content-section"),
        t = document.querySelectorAll(".nav-link"),
        n = new IntersectionObserver(
            (e) => {
                e.forEach((e) => {
                    if (!e.isIntersecting) return;
                    const n = e.target.id;
                    t.forEach((e) => {
                        e.classList.toggle("active", e.getAttribute("href") === `#${n}`);
                    });
                });
            },
            { rootMargin: "-15% 0px -80% 0px", threshold: 0 },
        );
    e.forEach((e) => n.observe(e));
}
document.addEventListener("DOMContentLoaded", () => {
    (initTheme(),
        initNavigation(),
        initCodeBlocks(),
        initSidebarSearch(),
        handleResponsiveTables());
});
const COPY_ICON =
    '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    CHECK_ICON =
        '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
function initCodeBlocks() {
    (document.querySelectorAll("pre").forEach((e) => {
        (e.removeAttribute("style"),
            e.querySelectorAll("[style]").forEach((e) => e.removeAttribute("style")));
        const t = e.closest(".code-block-wrapper");
        if (t) {
            const n = t.querySelector(".copy-btn");
            return void (n && !n.dataset.wired && wireCopyBtn(n, e));
        }
        const n = document.createElement("div");
        ((n.className = "code-block-wrapper"),
            e.parentNode.insertBefore(n, e),
            n.appendChild(e));
        const o =
            (e.querySelector("code")?.className || "")
                .match(/language-(\w+)/)?.[1]
                ?.toUpperCase() || "CODE",
            r = document.createElement("div");
        r.className = "code-header";
        const i = document.createElement("span");
        ((i.className = "code-lang"), (i.textContent = o));
        const a = document.createElement("button");
        ((a.className = "copy-btn"),
            a.setAttribute("type", "button"),
            a.setAttribute("aria-label", "Copy code"),
            (a.innerHTML = `${COPY_ICON} Copy`),
            r.appendChild(i),
            r.appendChild(a),
            n.insertBefore(r, e),
            wireCopyBtn(a, e));
    }),
        document.querySelectorAll(".copy-btn[data-copy-target]").forEach((e) => {
            if (e.dataset.wired) return;
            const t = document.getElementById(e.dataset.copyTarget);
            t && wireCopyBtn(e, t.closest("pre") || t);
        }));
}
function wireCopyBtn(e, t) {
    ((e.dataset.wired = "true"),
        e.addEventListener("click", async () => {
            const n = t.querySelector("code") || t,
                o = n.textContent || "";
            try {
                await navigator.clipboard.writeText(o);
            } catch {
                const e = document.createRange();
                e.selectNodeContents(n);
                const t = window.getSelection();
                (t.removeAllRanges(),
                    t.addRange(e),
                    document.execCommand("copy"),
                    t.removeAllRanges());
            }
            ((e.innerHTML = `${CHECK_ICON} Copied!`),
                e.classList.add("copied"),
                e.setAttribute("aria-label", "Copied!"),
                clearTimeout(e._copyTimer),
                (e._copyTimer = setTimeout(() => {
                    ((e.innerHTML = `${COPY_ICON} Copy`),
                        e.classList.remove("copied"),
                        e.setAttribute("aria-label", "Copy code"));
                }, 2200)));
        }));
}
function initSidebarSearch() {
    const e = document.getElementById("sidebarSearch");
    if (!e) return;
    const t = document.querySelectorAll(".nav-section");
    (e.addEventListener("input", () => {
        const n = e.value.trim().toLowerCase();
        t.forEach((e) => {
            let t = !1;
            (e.querySelectorAll(".nav-list li").forEach((e) => {
                const o = e.querySelector(".nav-link")?.textContent.toLowerCase() || "",
                    r = !n || o.includes(n);
                ((e.style.display = r ? "" : "none"), r && (t = !0));
            }),
                (e.style.display = t ? "" : "none"));
        });
    }),
        e.addEventListener("keydown", (t) => {
            "Escape" === t.key &&
                ((e.value = ""), e.dispatchEvent(new Event("input")), e.blur());
        }));
}
function handleResponsiveTables() {
    document.querySelectorAll(".data-table").forEach((e) => {
        if (e.closest(".table-scroll")) return;
        const t = document.createElement("div");
        ((t.className = "table-scroll"),
            e.parentNode.insertBefore(t, e),
            t.appendChild(e));
    });
}
