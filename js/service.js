/*global $, dotclear, getData, notifyBrowser */
'use strict';

dotclear.dmHostingMonitorPing = function() {
  const showStatus = function(online = false) {
    const $page = $('#content h2 a img');
    if ($page.length) {
      // Use the alternate home icon (in color) rather than the regular one
      const src = $page.prop('src');
      if (src.endsWith('/style/dashboard.png')) {
        // First pass, change icon and save it's alt label
        $page.prop('src', 'style/dashboard-alt.png');
        dotclear.dmHostingMonitor_Alt = $page.prop('alt') + ' : ';
      }
    }
    dotclear.dmHostingMonitor_Alt = dotclear.dmHostingMonitor_Alt == undefined ? '' : dotclear.dmHostingMonitor_Alt;
    const $img = $page.length ? $page : $('#content h2 img');
    // Change image if necessary
    if (online !== true) {
      // For debugging purpose only:
      // console.log($('rsp',data).attr('message'));
      // window.console.log('Dotclear REST server error');
      // Server offline
      $('body').css('filter', 'grayscale(1)');
      const msg = dotclear.dmHostingMonitor_Alt + dotclear.dmHostingMonitor_Offline + ` (${new Date().toLocaleString()})`;
      if (!$page.length) {
        $img.prop('alt', msg);
      }
      if (($('body').data('server') === 1) && (typeof notifyBrowser === "function")) {
        notifyBrowser(msg);
      }
      $('body').data('server', 0);
    } else {
      // Server online
      $('body').css('filter', '');
      if (dotclear && dotclear.data && dotclear.data.darkMode) {
        $img.css('filter', 'brightness(2)');
      } else {
        $img.css('filter', 'hue-rotate(225deg)');
      }
      const msg = dotclear.dmHostingMonitor_Alt + dotclear.dmHostingMonitor_Online + ` (${new Date().toLocaleString()})`;
      $img.prop('alt', msg);
      if (($('body').data('server') === 0) && (typeof notifyBrowser === "function")) {
        notifyBrowser(msg);
      }
      $('body').data('server', 1);
    }
    $img.prop('title', $img.prop('alt'));
  };

  $.get('services.php', {
      f: 'dmHostingMonitorPing',
      xd_check: dotclear.nonce
    })
    .done(function(data) {
      showStatus($('rsp[status=failed]', data).length > 0 ? false : true);
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
      window.console.log(`AJAX ${textStatus} (status: ${jqXHR.status} ${errorThrown})`);
      showStatus(false);
    })
    .always(function() {
      // Nothing here
    });
};

$(function() {
  Object.assign(dotclear, getData('dm_hostingmonitor'));
  if (dotclear.dmHostingMonitor_Ping) {
    $('body').data('server', -1);
    // First pass
    dotclear.dmHostingMonitorPing();
    // Auto refresh requested : Set 5 minutes interval between two pings
    dotclear.dmHostingMonitor_Timer = setInterval(dotclear.dmHostingMonitorPing, 60 * 5 * 1000);
  }
});
