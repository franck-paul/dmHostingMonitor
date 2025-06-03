/*global JustGage */
'use strict';

dotclear.ready(() => {
  const values = dotclear.getData('dm_hostingmonitor_values');

  const draw = (id, value) =>
    new JustGage({
      id,
      value,
      min: 0,
      max: 100,
      label: '%',
      showInnerShadow: false,
    });

  if (values.hd_free !== undefined) {
    draw('hd-free', values.hd_free);
  }

  if (values.hd_used !== undefined) {
    draw('hd-used', values.hd_used);
  }

  if (values.db_used !== undefined) {
    draw('db-used', values.db_used);
  }
});
