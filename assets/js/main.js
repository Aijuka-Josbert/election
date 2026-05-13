document.addEventListener("DOMContentLoaded", () => {
    const voteForm = document.getElementById("voteForm");
    const progressBar = document.getElementById("voteProgressBar");
    const progressText = document.getElementById("voteProgressText");

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
        const genderLabel = gender === "female" ? "Mrs UMU Rubaga" : "Mr UMU Rubaga";

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

    prevCategoryBtn?.addEventListener("click", () => {
        if (currentStep > 0) {
            currentStep -= 1;
            updateStepper();
        }
    });

    nextCategoryBtn?.addEventListener("click", () => {
        const activeStep = voteSteps[currentStep];
        if (!isStepComplete(activeStep)) {
            alert("Please rate every contestant in this category before continuing.");
            return;
        }

        if (currentStep < voteSteps.length - 1) {
            currentStep += 1;
            updateStepper();
        }
    });

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

    const chartCanvas = document.getElementById("overallChart");
    if (chartCanvas && window.Chart) {
        const labels = JSON.parse(chartCanvas.dataset.labels || "[]");
        const scores = JSON.parse(chartCanvas.dataset.scores || "[]");
        if (labels.length) {
            new Chart(chartCanvas, {
                type: "bar",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Average score",
                            data: scores,
                            backgroundColor: "rgba(201, 162, 39, 0.6)",
                            borderColor: "rgba(201, 162, 39, 1)",
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, max: 5 } },
                },
            });
        }
    }

    const categoryChart = document.getElementById("categoryChart");
    if (categoryChart && window.Chart) {
        const labels = JSON.parse(categoryChart.dataset.labels || "[]");
        const scores = JSON.parse(categoryChart.dataset.scores || "[]");
        if (labels.length) {
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
                    indexAxis: "y",
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, max: 5 } },
                },
            });
        }
    }
});
