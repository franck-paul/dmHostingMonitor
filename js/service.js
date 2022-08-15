/*global $, dotclear, notifyBrowser */
'use strict';

dotclear.dmHostingMonitorPing = () => {
  const showStatus = (online = false) => {
    const $img = $('#content h2 a img').length ? $('#content h2 a img') : $('#content h2 img');
    if ($('body').data('server') === -1 && $img.length && $img.prop('alt').length) {
      // Server status has never been tested yet
      dotclear.dmHostingMonitor_Alt = `${$img.prop('alt')} : `;
    }
    dotclear.dmHostingMonitor_Alt = dotclear.dmHostingMonitor_Alt ?? '';
    $('body').css('filter', online ? '' : 'grayscale(1)');
    const msg = `${
      dotclear.dmHostingMonitor_Alt + (online ? dotclear.dmHostingMonitor_Online : dotclear.dmHostingMonitor_Offline)
    } (${new Date().toLocaleString()})`;
    if ($img.length) {
      $img.prop('alt', msg);
    }
    if (
      typeof notifyBrowser === 'function' &&
      ((online && $('body').data('server') === 0) || (!online && $('body').data('server') === 1))
    ) {
      notifyBrowser(msg);
    }
    // Store new server status
    $('body').data('server', online ? 1 : 0);
    $img.prop('title', $img.prop('alt'));
  };

  dotclear.services(
    'dmHostingMonitorPing',
    (data) => {
      try {
        const response = JSON.parse(data);
        if (response?.success) {
          if (response?.payload.ret) {
            showStatus(true);
          }
        } else {
          console.log(dotclear.debug && response?.message ? response.message : 'Dotclear REST server error');
          return;
        }
      } catch (e) {
        console.log(e);
      }
    },
    (error) => {
      console.log(error);
      showStatus(false);
    },
    true, // Use GET method
    { json: 1 },
  );
};

$(() => {
  Object.assign(dotclear, dotclear.getData('dm_hostingmonitor'));
  if (dotclear.dmHostingMonitor_Ping) {
    $('body').data('server', -1);
    // First pass
    dotclear.dmHostingMonitorPing();
    // Auto refresh requested : Set 5 minutes interval between two pings
    dotclear.dmHostingMonitor_Timer = setInterval(dotclear.dmHostingMonitorPing, 60 * 5 * 1000);
  }
});
