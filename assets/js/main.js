document.addEventListener("DOMContentLoaded", () => {
    const voteForm = document.getElementById("voteForm");
    const progressBar = document.getElementById("voteProgressBar");
    const progressText = document.getElementById("voteProgressText");
    const categoryCounterEl = document.getElementById("categoryCounter");
    const totalCategoriesEl = document.getElementById("totalCategories");
    const categoryProgressContainer = document.getElementById("categoryProgressContainer");

    const updateVoteProgress = () => {
        if (!voteForm || !progressBar || !progressText) {
            return;
        }

        const total = Number(voteForm.dataset.total || 0);
        const inputs = voteForm.querySelectorAll("input.star-input:checked");
        let filled = 0;
        inputs.forEach(() => {
            filled += 1;
        });

        const percent = total ? Math.round((filled / total) * 100) : 0;
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}% completed`;
        
        // Update category completion counter
        updateCategoryProgress();
    };

    const updateCategoryProgress = () => {
        if (!voteForm || !categoryProgressContainer) {
            return;
        }

        const voteSteps = voteForm.querySelectorAll(".vote-step");
        let completedCount = 0;

        voteSteps.forEach((stepEl) => {
            const stepIndex = parseInt(stepEl.dataset.step, 10);
            const categoryId = stepEl.dataset.categoryId;
            const progressStep = categoryProgressContainer.querySelector(`[data-step="${stepIndex}"]`);

            if (!progressStep) {
                return;
            }

            const ratingGroups = stepEl.querySelectorAll(".star-rating");
            let stepComplete = true;

            for (const group of ratingGroups) {
                if (!group.querySelector("input.star-input:checked")) {
                    stepComplete = false;
                    break;
                }
            }

            if (stepComplete) {
                completedCount += 1;
                progressStep.classList.add("completed");
                progressStep.classList.remove("active");
            } else {
                progressStep.classList.remove("completed");
            }
        });

        if (categoryCounterEl) {
            categoryCounterEl.textContent = completedCount;
        }
    };

    updateVoteProgress();

    document.querySelectorAll(".star-input").forEach((input) => {
        input.addEventListener("change", updateVoteProgress);
    });

    const voteSteps = document.querySelectorAll(".vote-step");
    const prevCategoryBtn = document.getElementById("prevCategoryBtn");
    const nextCategoryBtn = document.getElementById("nextCategoryBtn");
    const submitVoteBtn = document.getElementById("submitVoteBtn");
    const currentCategoryTitle = document.getElementById("currentCategoryTitle");
    const currentCategoryMeta = document.getElementById("currentCategoryMeta");
    const stepToast = document.getElementById("stepToast");
    let toastTimer = null;
    let currentStep = 0;

    const isStepComplete = (stepEl) => {
        if (!stepEl) {
            return false;
        }

        const ratingGroups = stepEl.querySelectorAll(".star-rating");
        for (const group of ratingGroups) {
            if (!group.querySelector("input.star-input:checked")) {
                return false;
            }
        }

        return true;
    };

    const updateStepper = () => {
        voteSteps.forEach((stepEl, index) => {
            stepEl.style.display = index === currentStep ? "" : "none";
        });

        const activeStep = voteSteps[currentStep];
        const categoryName = activeStep?.dataset?.category || "";
        const gender = activeStep?.dataset?.gender || "";
        const maleTitle = voteForm?.dataset?.maleTitle || "Mr UMU Rubaga";
        const femaleTitle = voteForm?.dataset?.femaleTitle || "Mrs UMU Rubaga";

        let genderLabel;
        if (gender === "all") {
            genderLabel = `${maleTitle} & ${femaleTitle}`;
        } else if (gender === "female") {
            genderLabel = femaleTitle;
        } else {
            genderLabel = maleTitle;
        }

        if (currentCategoryTitle) {
            currentCategoryTitle.textContent = `Step ${currentStep + 1} of ${voteSteps.length}`;
        }
        if (currentCategoryMeta) {
            currentCategoryMeta.textContent = `${genderLabel} - ${categoryName}`;
        }
        if (prevCategoryBtn) {
            prevCategoryBtn.disabled = currentStep === 0;
        }
        if (nextCategoryBtn) {
            nextCategoryBtn.disabled = currentStep >= voteSteps.length - 1;
        }
        if (submitVoteBtn) {
            submitVoteBtn.style.display = currentStep === voteSteps.length - 1 ? "inline-block" : "none";
        }

        // Update progress bar active step indicator
        if (categoryProgressContainer) {
            const progressSteps = categoryProgressContainer.querySelectorAll(".progress-step");
            progressSteps.forEach((step) => {
                step.classList.remove("active");
                const stepIndex = parseInt(step.dataset.step, 10);
                if (stepIndex === currentStep) {
                    step.classList.add("active");
                }
            });
        }

        if (activeStep) {
            activeStep.classList.remove("step-change");
            requestAnimationFrame(() => {
                activeStep.classList.add("step-change");
            });
        }

        if (stepToast) {
            stepToast.textContent = `Now voting: ${genderLabel} - ${categoryName}`;
            stepToast.classList.add("show");
            if (toastTimer) {
                clearTimeout(toastTimer);
            }
            toastTimer = setTimeout(() => {
                stepToast.classList.remove("show");
            }, 2200);
        }
    };

    if (voteSteps.length) {
        updateStepper();
    }

    const bindTap = (el, handler) => {
        if (!el) {
            return;
        }

        let lastTouchAt = 0;

        el.addEventListener("touchend", (e) => {
            lastTouchAt = Date.now();
            handler(e);
        }, { passive: false });

        el.addEventListener("click", (e) => {
            if (Date.now() - lastTouchAt < 600) {
                return;
            }
            handler(e);
        });
    };

    const handlePrevClick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (currentStep > 0) {
            currentStep -= 1;
            updateStepper();
            // Scroll to top on mobile
            if (window.innerWidth < 768) {
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            }
        }
    };
    bindTap(prevCategoryBtn, handlePrevClick);

    const handleNextClick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const activeStep = voteSteps[currentStep];
        if (!isStepComplete(activeStep)) {
            alert("Please rate every contestant in this category before continuing.");
            return;
        }

        if (currentStep < voteSteps.length - 1) {
            currentStep += 1;
            updateStepper();
            // Scroll to top on mobile
            if (window.innerWidth < 768) {
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            }
        }
    };
    bindTap(nextCategoryBtn, handleNextClick);

    if (voteForm && voteSteps.length) {
        voteForm.addEventListener("change", (event) => {
            if (!event.target.classList.contains("star-input")) {
                return;
            }

            const activeStep = voteSteps[currentStep];
            if (!activeStep || !isStepComplete(activeStep)) {
                return;
            }

            if (currentStep < voteSteps.length - 1) {
                currentStep += 1;
                updateStepper();
            }
        });
    }

    // Double-submission UX guard: disable the submit button and show a
    // "please wait" state the instant either ballot form is submitted, so
    // an impatient double-click doesn't fire two overlapping requests. The
    // server already makes duplicate submissions harmless on its own (see
    // vote.php's per-voter row lock) — this is purely about not confusing
    // the voter with a second, redundant page load while the first request
    // is still in flight.
    const guardAgainstDoubleSubmit = (form, submitButtonSelector, waitingText) => {
        if (!form) {
            return;
        }
        form.addEventListener("submit", (event) => {
            const btn = form.querySelector(submitButtonSelector);
            if (btn && btn.dataset.submitting === "true") {
                // Already submitting — this is a second submit event
                // (e.g. Enter key + click), block it client-side too.
                event.preventDefault();
                return;
            }
            if (btn) {
                btn.dataset.submitting = "true";
                btn.disabled = true;
                btn.textContent = waitingText;
            }
        });
    };
    guardAgainstDoubleSubmit(document.getElementById("voteForm"), "#submitVoteBtn", "Submitting your vote…");
    guardAgainstDoubleSubmit(document.getElementById("simpleVoteForm"), "button[type=submit]", "Submitting your vote…");

    const countdown = document.getElementById("countdown");
    const eventDate = countdown?.dataset?.eventDate;

    if (countdown && eventDate) {
        const updateCountdown = () => {
            const now = new Date();
            const target = new Date(eventDate);
            const diff = target - now;

            if (diff <= 0) {
                countdown.textContent = "Voting is live now";
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const mins = Math.floor((diff / (1000 * 60)) % 60);

            countdown.textContent = `${days}d ${hours}h ${mins}m to gala night`;
        };

        updateCountdown();
        setInterval(updateCountdown, 60000);
    }

    const initRankingChart = (canvasId, color = "rgba(201, 162, 39, 0.6)") => {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !window.Chart) {
            return;
        }

        const labels = JSON.parse(canvas.dataset.labels || "[]");
        const scores = JSON.parse(canvas.dataset.scores || "[]");
        if (!labels.length) {
            return;
        }

        const borderColor = color.replace("0.6", "1").replace("0.5", "1");
        new Chart(canvas, {
            type: "bar",
            data: {
                labels,
                datasets: [
                    {
                        label: "Average score",
                        data: scores,
                        backgroundColor: color,
                        borderColor,
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 5 } },
            },
        });
    };

    initRankingChart("overallChartMale", "rgba(201, 162, 39, 0.6)");
    initRankingChart("overallChartFemale", "rgba(200, 16, 46, 0.5)");

    const categoryChart = document.getElementById("categoryChart");
    if (categoryChart && window.Chart) {
        const labels = JSON.parse(categoryChart.dataset.labels || "[]");
        const scores = JSON.parse(categoryChart.dataset.scores || "[]");
        if (labels.length) {
            const isMobile = window.matchMedia("(max-width: 767.98px)").matches;
            new Chart(categoryChart, {
                type: "bar",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Category average",
                            data: scores,
                            backgroundColor: "rgba(200, 16, 46, 0.5)",
                            borderColor: "rgba(200, 16, 46, 1)",
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    indexAxis: isMobile ? "x" : "y",
                    plugins: { legend: { display: false } },
                    scales: isMobile
                        ? { y: { beginAtZero: true, max: 5 } }
                        : { x: { beginAtZero: true, max: 5 } },
                },
            });
        }
    }

    const sliders = document.querySelectorAll(".results-slider");
    sliders.forEach((slider) => {
        const track = slider.querySelector(".results-track");
        const slides = Array.from(slider.querySelectorAll(".result-slide"));
        const prevBtn = slider.querySelector(".slider-btn.prev");
        const nextBtn = slider.querySelector(".slider-btn.next");
        const dots = slider.querySelector(".slider-dots");
        if (!track || slides.length === 0) {
            return;
        }

        let currentIndex = 0;
        const autoplay = slider.dataset.autoplay === "true";
        let timer = null;

        const renderDots = () => {
            if (!dots) {
                return;
            }
            dots.innerHTML = "";
            slides.forEach((_, index) => {
                const dot = document.createElement("button");
                dot.type = "button";
                dot.className = "slider-dot";
                if (index === currentIndex) {
                    dot.classList.add("active");
                }
                dot.addEventListener("click", () => {
                    goTo(index);
                });
                dots.appendChild(dot);
            });
        };

        const update = () => {
            track.style.transform = `translateX(${-currentIndex * 100}%)`;
            renderDots();
        };

        const goTo = (index) => {
            currentIndex = (index + slides.length) % slides.length;
            update();
        };

        prevBtn?.addEventListener("click", () => goTo(currentIndex - 1));
        nextBtn?.addEventListener("click", () => goTo(currentIndex + 1));

        let touchStartX = 0;
        let touchEndX = 0;

        const startAutoplay = () => {
            timer = setInterval(() => {
                goTo(currentIndex + 1);
            }, 5000);
        };

        const stopAutoplay = () => {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        };

        if (autoplay) {
            startAutoplay();
            slider.addEventListener("mouseenter", stopAutoplay);
            slider.addEventListener("mouseleave", startAutoplay);
        }

        slider.addEventListener("touchstart", (event) => {
            touchStartX = event.changedTouches[0]?.screenX ?? 0;
            stopAutoplay();
        });

        slider.addEventListener("touchend", (event) => {
            touchEndX = event.changedTouches[0]?.screenX ?? 0;
            const delta = touchStartX - touchEndX;
            if (Math.abs(delta) > 40) {
                if (delta > 0) {
                    goTo(currentIndex + 1);
                } else {
                    goTo(currentIndex - 1);
                }
            }

            if (autoplay) {
                startAutoplay();
            }
        });

        update();
    });

    const printBtn = document.getElementById("printResultsBtn");
    if (printBtn) {
        printBtn.addEventListener("click", () => {
            window.print();
        });
    }

    // Voting timer: show countdown using server-side status polling (no auto-reload)
    (function initVotingTimer() {
        const meta = document.getElementById('votingMeta');
        if (!meta) return;

        const base = (meta.dataset.base || '').replace(/\/$/, '');
        const apiPath = (path) => (base ? `${base}/${path.replace(/^\//, '')}` : path.replace(/^\//, ''));

        const startStr = meta.dataset.start || '';
        const endStr = meta.dataset.end || '';
        const adminEnabled = meta.dataset.enabled === '1';

        const start = startStr ? new Date(startStr) : null;
        const end = endStr ? new Date(endStr) : null;

        const findOrCreateTimerEl = () => {
            let el = document.getElementById('votingTimer');
            if (el) return el;
            const alert = document.querySelector('.container > .alert') || document.querySelector('.alert') || document.querySelector('.section-title');
            el = document.createElement('div');
            el.id = 'votingTimer';
            el.className = 'voting-timer mt-2 text-muted';
            if (alert && alert.parentNode) {
                alert.appendChild(el);
            } else {
                const container = document.querySelector('.container');
                if (container) container.insertBefore(el, container.firstChild);
            }
            return el;
        };

        const timerEl = findOrCreateTimerEl();

        const formatDiff = (ms) => {
            if (ms <= 0) return '0s';
            const s = Math.floor(ms / 1000);
            const days = Math.floor(s / 86400);
            const hours = Math.floor((s % 86400) / 3600);
            const mins = Math.floor((s % 3600) / 60);
            const secs = s % 60;
            if (days > 0) return `${days}d ${hours}h ${mins}m`;
            if (hours > 0) return `${hours}h ${mins}m ${secs}s`;
            if (mins > 0) return `${mins}m ${secs}s`;
            return `${secs}s`;
        };

        const updateLocal = () => {
            const now = new Date();

            if (!adminEnabled && !start && !end) {
                timerEl.textContent = 'Voting is currently disabled by the admin.';
                return;
            }

            if (start && now < start) {
                timerEl.textContent = `Voting opens in ${formatDiff(start - now)}`;
            } else if (end && now < end) {
                timerEl.textContent = `Voting closes in ${formatDiff(end - now)}`;
            } else if (start && end && now >= end) {
                timerEl.textContent = 'Voting window has closed.';
            } else if (adminEnabled && !start && !end) {
                timerEl.textContent = 'Voting is enabled.';
            } else {
                timerEl.textContent = '';
            }
        };

        // Poll server for authoritative open/close state and prompt user to refresh when voting starts
        let lastServerOpen = null;
        const checkServer = async () => {
            try {
                const res = await fetch(apiPath('api/voting_status.php') + '?_=' + Date.now(), { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                const serverOpen = !!Number(data.open);

                if (lastServerOpen === null) {
                    lastServerOpen = serverOpen;
                } else if (!lastServerOpen && serverOpen) {
                    // Voting has just opened on the server
                    showOpenBanner();
                    lastServerOpen = serverOpen;
                } else {
                    lastServerOpen = serverOpen;
                }
            } catch (e) {
                // ignore network errors
            }
        };

        const showOpenBanner = () => {
            if (document.getElementById('votingOpenBanner')) return;
            const banner = document.createElement('div');
            banner.id = 'votingOpenBanner';
            banner.className = 'alert alert-success text-center';
            banner.style.marginTop = '12px';
            banner.innerHTML = `Voting has started — <button type="button" class="btn btn-sm btn-primary ms-2" id="refreshToVoteBtn">Refresh to vote</button>`;
            const container = document.querySelector('.container');
            if (container) container.insertBefore(banner, container.firstChild);
            const btn = document.getElementById('refreshToVoteBtn');
            if (btn) btn.addEventListener('click', () => location.reload());
        };

        updateLocal();
        setInterval(updateLocal, 1000);
        // initial check and poll every 10s
        checkServer();
        setInterval(checkServer, 10000);
    })();
});
