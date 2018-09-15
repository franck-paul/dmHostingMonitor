/*global $, dotclear, dotclear_darkMode */
'use strict';

dotclear.dmHostingMonitorPing = function() {
  var params = {
    f: 'dmHostingMonitorPing',
    xd_check: dotclear.nonce
  };
  $.get('services.php', params, function(data) {
    const $home = $('#content h2 img');
    if ($('rsp[status=failed]', data).length > 0) {
      // For debugging purpose only:
      // console.log($('rsp',data).attr('message'));
      // window.console.log('Dotclear REST server error');
      // Server offline
      $home.css('filter', 'grayscale(1)');
      $home.prop('alt', dotclear.dmHostingMonitor_Offline);
    } else {
      // Server online
      if (typeof dotclear_darkMode !== 'undefined' && dotclear_darkMode) {
        $home.css('filter', 'brightness(2)');
      } else {
        $home.css('filter', 'hue-rotate(225deg)');
      }
      $home.prop('alt', dotclear.dmHostingMonitor_Online);
    }
  });
};

$(function() {
  if (dotclear.dmHostingMonitor_Ping) {
    // Auto refresh requested : Set 5 minutes interval between two pings
    dotclear.dmHostingMonitor_Timer = setInterval(dotclear.dmHostingMonitorPing, 60 * 5 * 1000);
  }
});
