window.AppSyncPoll = {
    start(fn, intervalMs) {
        intervalMs = intervalMs || 60000;

        function tick() {
            if (document.visibilityState === 'visible') {
                fn();
            }
        }

        tick();
        return window.setInterval(tick, intervalMs);
    },
};
