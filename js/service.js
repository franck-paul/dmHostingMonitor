/*global $, dotclear, dotclear_darkMode */
'use strict';

dotclear.dmHostingMonitorPing = function() {
  var showStatus = function(online = false) {
    const $page = $('#content h2 a img');
    if ($page.length) {
      // Use the alternate home icon (in color) rather than the regular one
      var src = $page.prop('src');
      if (src.endsWith('/style/dashboard.png')) {
        // First pass, change icon and save it's alt label
        $page.prop('src', 'style/dashboard-alt.png');
        dotclear.dmHostingMonitor_Alt = $page.prop('alt') + ' : ';
      }
    } else {
      dotclear.dmHostingMonitor_Alt = '';
    }
    const $img = $page.length ? $page : $('#content h2 img');
    // Change image if necessary
    if (online !== true) {
      // For debugging purpose only:
      // console.log($('rsp',data).attr('message'));
      // window.console.log('Dotclear REST server error');
      // Server offline
      $img.css('filter', 'grayscale(1)');
      if (!$page.length) {
        $img.prop('alt', dotclear.dmHostingMonitor_Alt + dotclear.dmHostingMonitor_Offline);
      }
    } else {
      // Server online
      if (typeof dotclear_darkMode !== 'undefined' && dotclear_darkMode) {
        $img.css('filter', 'brightness(2)');
      } else {
        $img.css('filter', 'hue-rotate(225deg)');
      }
      $img.prop('alt', dotclear.dmHostingMonitor_Alt + dotclear.dmHostingMonitor_Online);
    }
  };

  $.get('services.php', {
      f: 'dmHostingMonitorPing',
      xd_check: dotclear.nonce
    })
    .done(function(data) {
      showStatus($('rsp[status=failed]', data).length > 0 ? false : true);
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
      window.console.log('AJAX ' + textStatus + ' (status: ' + jqXHR.status + ' ' + errorThrown + ')');
      showStatus(false);
    })
    .always(function() {
      // Nothing here
    });
};

$(function() {
  if (dotclear.dmHostingMonitor_Ping) {
    // Auto refresh requested : Set 5 minutes interval between two pings
    dotclear.dmHostingMonitor_Timer = setInterval(dotclear.dmHostingMonitorPing, 60 * 5 * 1000);
  }
});
