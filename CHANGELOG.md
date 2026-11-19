# Changelog

## 7.1 — 2026-11-07

- Cope with RTL
- Enforces some verification before registering module

## 7.0 — 2026-08-03

- Defer loaded script execution (wait for dmHelper)
- Avoid using magic _get(), _set() methods of MetaRecord (2.39+)
- Avoid using magic methods for user preferences and blog settings (2.39+)
- Use MetaRecord::strField(), MetaRecord::intField() and MetaRecord::boolField() whenever is possible

## 6.4 — 2026-04-09

- DA -&gt; DC

## 6.3 — 2025-12-14

- A11y: add en empty alt attribute to icon image in module title

## 6.2 — 2025-09-22

- Use backend services rather than static calls

## 6.1 — 2025-09-11

- Review context checking: admin only for all backend

## 6.0 — 2025-09-07

- DC 2.36 compliance

## 5.2 — 2025-06-03

- No need to repeat things

## 5.1 — 2025-06-03

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 5.0 — 2025-05-05

- Use Strong(…) rather than Text('strong', …)

## 4.10 — 2025-02-26

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.9 — 2025-02-24

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.8 — 2024-11-28

- Forgot this one

## 4.7 — 2024-11-28

- Remove unnecessary EOLs after script stuffs in header

## 4.6 — 2024-11-13

- Switch from jQuery to Vanilla JS
- Use dotclear.ready() rather than jQuery more or less equivalent

## 4.5 — 2024-09-05

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.4 — 2024-06-17

- Check connection before pinging server

## 4.3 — 2024-01-07

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.2 — 2023-10-27

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.1 — 2023-10-25

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.0 — 2023-10-24

- Use 2.28 Config methods
- 2.28 compliance

## 3.0 — 2023-08-23

- Dotclear 2.27 compliance, PHP 8.1+

## 2.3 — 2023-07-12

- Use SVG icon rather than PNG

## 2.2.1 — 2023-07-03

- Use unique id for preferences fields

## 2.2 — 2023-07-03

- Add ping interval setting (still 300 seconds by default)

## 2.1.2 — 2023-06-27

- Add local static $init variable

## 2.1.1 — 2023-05-29

- Fix small/large

## 2.1 — 2023-05-13

- Add missing global user preference in installation
- Better this way
- Use My::checkContext() and add Uninstall
- Use My::id() rather than plugin directory name or plugin id

## 2.0 — 2023-05-02

- Prepare 2.26
- Prepare 2.25

## 1.0 — 2022-11-29

- Avoid notice on new installation

## 0.18 — 2022-11-24

- 2.24 compliance
- Add README.md and LICENSE file (GPL-v2)
- No more inline JS
- Update js lib

## 0.17 — 2022-08-15

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 0.16 — 2022-02-13

- Update default branch in _define.php for alternative repository
- Add alternative repository

## 0.15 — 2021-08-06

- Dotclear 2.19 compliance

## 0.14 — 2020-10-12

- Display browser notice when server status change (if notifyMe plugin is installed and enabled)

## 0.13 — 2020-10-06

- Better display of offline server

## 0.12 — 2020-04-15

- 0.12 release
- Add timestamp for server status info (alt/title)

## 0.11 — 2020-04-11

- Grayscale all content of header if server offline, rather than only home icon

## 0.10 — 2020-04-07

- No more inline JS

## 0.9 — 2019-09-16

- Fix alt text for every case, add a 1st check at launch
- Remove hg files
- Switch input numeric field to type number, add title (equal to alt) to home image (serveur status)
- Cope with JS dotclear data
- Use let and const rather than var (ES2015/ES6)
- Add console log message on fail
- More readable this way
- Better this way to also cope with 500/503/… server error
- Autres correctifs et améliorations.

## 0.8.1 — 2018-09-15

- Prepare a 0.8.1 Dotclear 2.14+

## 0.8 — 2018-09-14

- Add ping service (server status : offline/online)
- short notation for array (array() → [])

## 0.7 — 2018-08-13

- Use new syntax() db driver property

## 0.6.1 — 2017-01-07

- Bugfix
- Do not include js and css if plugin not activated
- Using dcPage::cssLoad, dcPage::jsLoad and dcPage::getPF is a better way to load plugin resources

## 0.6 — 2013-10-04

- new _define.php
- Should load js and css only on dashboard page
- New mini-icon
- Adjustement
- Add gauges display option
- No more $GLOBALS at top level, and various
- Better this way in small mode
- Cope with dashboard box sizes
- Autres correctifs et améliorations.

## 0.5 — 2012-11-29

- 0.5
- harmonize &lt;label&gt;&lt;/label&gt; in forms
- Add missing files
- Revert to original name and desc in define.php of plugins. Localized versions are not required here.
- Retrocompatibility with 2.4.4 behaviours
- Update for future DC 2.5

## 0.4 — 2012-05-03

- Thresholds are now customizable

## 0.3 — 2012-04-06

- Optimisation
- Version 0.3
- Now walk through symbolic links
- Cope with all folders of Dotclear installation
- Minor fixes
- Version 0.2

## 0.2 — 2012-04-05

- Mise en place de la gestion des limites et affichage des jauges

## 0.1 — 2012-03-31

- Ajout infos sur place disque restante et totale
- Size of DB, first step for disk usage
- Empty shell for module with user prefs management
