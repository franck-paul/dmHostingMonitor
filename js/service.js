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

$(() => {
  Object.assign(dotclear, dotclear.getData('dm_hostingmonitor'));
  if (!dotclear.dmHostingMonitor_Ping) {
    return;
  }
  $('body').data('server', -1);
  // First pass
  dotclear.dmHostingMonitorPing();
  // Auto refresh requested (5 minutes interval by default between two pings)
  dotclear.dmHostingMonitor_Timer = setInterval(
    dotclear.dmHostingMonitorPing,
    (dotclear.dmHostingMonitor_Interval || 300) * 1000,
  );
});
