document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("input[type='range']").forEach((range) => {
        const output = range.closest(".range-wrap")?.querySelector(".range-value");
        if (output) {
            output.textContent = range.value;
        }

        range.addEventListener("input", () => {
            if (output) {
                output.textContent = range.value;
            }
            updateVoteProgress();
        });
    });

    const voteForm = document.getElementById("voteForm");
    const progressBar = document.getElementById("voteProgressBar");
    const progressText = document.getElementById("voteProgressText");

    const updateVoteProgress = () => {
        if (!voteForm || !progressBar || !progressText) {
            return;
        }

        const total = Number(voteForm.dataset.total || 0);
        const inputs = voteForm.querySelectorAll("input[type='range']");
        let filled = 0;
        inputs.forEach((input) => {
            if (input.value !== "" && !Number.isNaN(Number(input.value))) {
                filled += 1;
            }
        });

        const percent = total ? Math.round((filled / total) * 100) : 0;
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}% completed`;
    };

    updateVoteProgress();

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
                    scales: { y: { beginAtZero: true, max: 10 } },
                },
            });
        }
    }
});
