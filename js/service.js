/*global dotclear, notifyBrowser */
'use strict';

dotclear.dmHostingMonitorPing = () => {
  const showStatus = (online = false) => {
    const body = document.querySelector('body');
    if (!body) return;

    const icons =
      document.querySelectorAll('#content h2 a img').length > 0
        ? document.querySelectorAll('#content h2 a img')
        : document.querySelectorAll('#content h2 img');

    if (body.dataset.server === '' && icons.length && icons[0].alt.length) {
      // Server status has never been tested yet
      dotclear.dmHostingMonitor_Alt = `${icons[0].alt} : `;
    }
    dotclear.dmHostingMonitor_Alt = dotclear.dmHostingMonitor_Alt ?? '';

    body.style.filter = online ? '' : 'grayscale(1)';

    const msg = `${
      dotclear.dmHostingMonitor_Alt + (online ? dotclear.dmHostingMonitor_Online : dotclear.dmHostingMonitor_Offline)
    } (${new Date().toLocaleString()})`;

    if (icons.length) {
      // Set icon(s) alt text
      for (const icon of icons) icon.alt = msg;
    }

    if (
      typeof notifyBrowser === 'function' &&
      ((online && body.dataset.server === '0') || (!online && body.dataset.server === '1'))
    ) {
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

dotclear.ready(() => {
  Object.assign(dotclear, dotclear.getData('dm_hostingmonitor'));
  if (!dotclear.dmHostingMonitor_Ping) {
    return;
  }

  const body = document.querySelector('body');
  if (!body) return;
  body.dataset.server = '';

  // First pass
  dotclear.dmHostingMonitorPing();

  // Auto refresh requested (5 minutes interval by default between two pings)
  dotclear.dmHostingMonitor_Timer = setInterval(
    dotclear.dmHostingMonitorPing,
    (dotclear.dmHostingMonitor_Interval || 300) * 1000,
  );
});
