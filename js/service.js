/*global dotclear, notifyBrowser */
'use strict';

dotclear.ready(() => {
  dotclear.dmHostingMonitor = dotclear.getData('dm_hostingmonitor');
  if (!dotclear.dmHostingMonitor.ping) {
    return;
  }

  const pingServer = () => {
    const showStatus = (online = false) => {
      const body = document.querySelector('body');
      if (!body) return;

      const icons = document.querySelectorAll('img.go_home');

      if (body.dataset.server === '' && icons.length && icons[0].alt.length) {
        // Server status has never been tested yet
        dotclear.dmHostingMonitor.alt = `${icons[0].alt} : `;
      }
      dotclear.dmHostingMonitor.alt = dotclear.dmHostingMonitor.alt ?? '';

      body.style.filter = online ? '' : 'grayscale(1)';

      const msg = `${
        dotclear.dmHostingMonitor.alt + (online ? dotclear.dmHostingMonitor.online : dotclear.dmHostingMonitor.offline)
      } (${new Date().toLocaleString()})`;

      if (icons.length) {
        // Set icon(s) alt text
        for (const icon of icons) icon.alt = msg;
      }

      if (
        typeof notifyBrowser === 'function' &&
        ((online && body.dataset.server === '0') || (!online && body.dataset.server === '1'))
      ) {
        // Notify server status change
        notifyBrowser(msg);
      }

      // Store new server status
      body.dataset.server = online ? '1' : '0';
      if (icons.length) {
        for (const icon of icons) icon.title = icon.alt;
      }
    };

    if (dotclear.dmOnline() === false) {
      // No connection, no need to ping anything
      showStatus(false);
      return;
    }
    dotclear.jsonServices(
      'dmHelperPing', // Provided by dmHelper plugin
      (payload) => {
        showStatus(payload.ret);
      },
      (error) => {
        if (dotclear?.debug) console.log(error);
        showStatus(false);
      },
    );
  };

  const body = document.querySelector('body');
  if (!body) return;
  body.dataset.server = '';

  // First pass
  pingServer();

  // Auto refresh requested (defautl = every 5 minutes)
  dotclear.dmHostingMonitor.timer = setInterval(pingServer, (dotclear.dmHostingMonitor.interval || 300) * 1000);
});
