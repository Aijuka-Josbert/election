    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            // Client-side heads-up for the server-enforced 10-minute admin idle
            // timeout (see includes/admin_auth.php — that's what actually ends
            // the session; this is a warning only, since a client can't be
            // trusted to log itself out). Warns 30s before the server would
            // reject the next request, and redirects to a fresh login at the
            // same 10-minute mark so an admin doesn't lose form input to a
            // surprise redirect mid-submit.
            var IDLE_LIMIT_MS = 10 * 60 * 1000;
            var WARNING_BEFORE_MS = 30 * 1000;
            var lastActivity = Date.now();
            var warned = false;

            ["mousemove", "mousedown", "keydown", "scroll", "touchstart"].forEach(function(evt) {
                document.addEventListener(evt, function() {
                    lastActivity = Date.now();
                    warned = false;
                }, {
                    passive: true
                });
            });

            setInterval(function() {
                var idleFor = Date.now() - lastActivity;
                if (idleFor >= IDLE_LIMIT_MS) {
                    window.location.href = "login.php?timeout=1";
                } else if (idleFor >= IDLE_LIMIT_MS - WARNING_BEFORE_MS && !warned) {
                    warned = true;
                    showIdleWarning();
                }
            }, 5000);

            function showIdleWarning() {
                var el = document.createElement("div");
                el.textContent = "You'll be signed out soon due to inactivity. Move your mouse or press a key to stay signed in.";
                el.style.cssText = "position:fixed;bottom:16px;right:16px;z-index:9999;background:#c8102e;color:#fff;" +
                    "padding:12px 18px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.3);max-width:320px;";
                document.body.appendChild(el);
                setTimeout(function() {
                    el.remove();
                }, WARNING_BEFORE_MS);
            }
        })();
    </script>
    </body>

    </html>