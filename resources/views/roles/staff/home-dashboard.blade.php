@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Dashboard</title>
@endverbatim
  @fonts
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --purple-dark: #2d163f;
      --purple-soft: #f6f1fc;
      --gold: #f9b937;
      --bg: #f5f5f9;
      --text-main: #1f1230;
      --text-muted: #7a6992;
      --border-soft: #e3d9f0;
      --danger: #e05454;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
    }
    .app-shell { display: flex; flex-direction: column; min-height: 100vh; }
    .topbar {
      background: var(--purple);
      color: #fff;
      padding: 0.75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
      position: sticky;
      top: 0;
      z-index: 20;
    }
    .topbar-left { display: flex; align-items: center; gap: 0.75rem; }
    .topbar-brand-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: inherit;
      cursor: pointer;
    }
    .topbar-brand-link:hover { opacity: 0.92; }
    .topbar-logo {
      width: 32px; height: 32px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      background: #f3edf8;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .topbar-logo img { width: 100%; height: 100%; object-fit: contain; }
    .topbar-title-main { font-size: 1rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
    .topbar-title-sub { font-size: 0.75rem; color: #e2d4ff; letter-spacing: 0.16em; text-transform: uppercase; }
    .topbar-right { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
    .chip {
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }
    .topbar-notify { position: relative; display: inline-flex; align-items: center; justify-content: center; }
    .topbar-notify-btn {
      position: relative;
      width: 30px; height: 30px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.4);
      display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer;
      background: rgba(0, 0, 0, 0.16);
      color: #fff;
      font-size: 0.9rem;
    }
    .topbar-notify-dot {
      position: absolute;
      top: 6px; right: 6px;
      width: 7px; height: 7px;
      border-radius: 999px;
      background: var(--gold);
      box-shadow: 0 0 0 3px rgba(249, 185, 55, 0.35);
    }
    .topbar-user { position: relative; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
    .avatar {
      width: 28px; height: 28px;
      border-radius: 999px;
      background: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.75rem; font-weight: 600; color: var(--purple);
    }
    .user-menu,
    .notify-menu {
      position: absolute;
      top: 130%; right: 0;
      background: #fff;
      color: var(--text-main);
      min-width: 160px;
      border-radius: 0.6rem;
      border: 1px solid var(--border-soft);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
      padding: 0.35rem 0;
      font-size: 0.85rem;
      display: none;
      z-index: 10;
    }
    .user-menu a,
    .notify-menu div {
      display: block;
      padding: 0.45rem 0.9rem;
      text-decoration: none;
      color: inherit;
      white-space: nowrap;
    }
    .user-menu a:hover,
    .notify-menu div:hover { background: var(--purple-soft); color: var(--purple); }
    .user-menu a.logout { color: var(--danger); }
    .topbar-user.open .user-menu { display: block; }
    .tooltip { position: relative; }
    .tooltip::after {
      content: attr(data-tooltip);
      position: absolute;
      top: calc(100% + 10px); right: 0;
      background: #fff; color: var(--text-main);
      padding: 0.55rem 0.9rem;
      border-radius: 999px;
      box-shadow: 0 14px 26px rgba(0, 0, 0, 0.22);
      opacity: 0;
      transform: translateY(-4px);
      transition: opacity 0.15s ease, transform 0.15s ease;
      pointer-events: none;
      white-space: nowrap;
      z-index: 50;
    }
    .tooltip:hover::after { opacity: 1; transform: translateY(0); }
    .tooltip.open::after, .tooltip.open:hover::after { opacity: 0; transform: translateY(-4px); }
    .tooltip-left::after { left: 0; right: auto; }
    .nav-toggle {
      display: inline-flex;
      flex-direction: column;
      justify-content: center;
      gap: 3px;
      cursor: pointer;
      padding: 0.25rem 0.4rem;
      border-radius: 0.35rem;
      border: 1px solid rgba(255, 255, 255, 0.4);
      background: transparent;
      margin-right: 0.75rem;
    }
    .nav-toggle:hover { background: rgba(0, 0, 0, 0.2); }
    .nav-toggle-line { width: 18px; height: 2px; border-radius: 999px; background: #fff; }
    .topbar-tabs {
      display: flex;
      align-items: center;
      gap: 1.75rem;
      padding: 0.4rem 1.5rem;
      font-size: 0.8rem;
      background: var(--purple-dark);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
    }
    .topbar-tab {
      text-decoration: none;
      color: #e2d4ff;
      padding-bottom: 0.3rem;
      border-bottom: 2px solid transparent;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      white-space: nowrap;
    }
    .topbar-tab:hover {
      border-bottom-color: rgba(255, 255, 255, 0.6);
      color: #fff;
    }
    .topbar-tab.active {
      border-bottom-color: var(--gold);
      color: #fff;
      font-weight: 600;
    }
    .layout-row { display: flex; flex: 1; min-height: 0; }
    .nav-bar {
      background: var(--purple-dark);
      color: #fff;
      padding: 1.5rem 1rem;
      width: 210px;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      position: fixed;
      top: 92px;
      left: 0;
      height: calc(100vh - 92px);
      transform: translateX(-100%);
      transition: transform 0.25s ease;
      z-index: 15;
    }
    .nav-bar.nav-bar-open { transform: translateX(0); }
    .nav-section-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: #c9b8f3;
      margin-bottom: 0.25rem;
    }
    .nav-link {
      display: block;
      padding: 0.45rem 0.75rem;
      border-radius: 0.6rem;
      font-size: 0.85rem;
      color: #e8ddff;
      text-decoration: none;
    }
    .nav-link:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    .nav-link.active { background: #fff; color: var(--purple-dark); font-weight: 600; }
    .page {
      flex: 1;
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-left: 0;
      max-width: 1400px;
      width: 100%;
      margin-inline: auto;
    }
    .page-intro h1 { font-size: 1.35rem; font-weight: 600; color: var(--purple); }
    .page-intro p { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.35rem; }
    /* Dashboard: greeting + quick actions, summary cards, buildings, attention list */
    .dash-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: space-between;
      gap: 1rem;
    }
    .dash-head h1 { font-size: 1.5rem; font-weight: 700; color: var(--purple-dark); line-height: 1.2; }
    .dash-date { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.3rem; }
    .dash-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .dash-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      min-height: 44px;
      padding: 0.6rem 1.1rem;
      border-radius: 0.75rem;
      border: 1.5px solid var(--border-soft);
      background: #fff;
      color: var(--purple);
      font: inherit;
      font-size: 0.9rem;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
    }
    .dash-btn:hover { border-color: var(--purple); background: var(--purple-soft); }
    .dash-btn--primary { background: var(--gold); border-color: var(--gold); color: var(--purple-dark); }
    .dash-btn--primary:hover { background: #e5a82a; border-color: #e5a82a; }
    .dash-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
    .dash-btn:focus-visible, .dash-stat:focus-visible, .bld-row:focus-visible, .room-btn:focus-visible,
    .dash-link:focus-visible, .attn-action:focus-visible, .dash-upload-zone:focus-visible {
      outline: 3px solid var(--gold);
      outline-offset: 2px;
    }

    .dash-stats {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 1rem;
    }
    @media (max-width: 900px) {
      .dash-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
    }
    .dash-stat {
      display: block;
      text-decoration: none;
      color: inherit;
      background: #fff;
      border: 1px solid var(--border-soft);
      border-radius: 1rem;
      padding: 1rem 1.15rem;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .dash-stat:hover { border-color: rgba(92, 38, 126, 0.35); box-shadow: 0 8px 24px rgba(45, 22, 63, 0.08); }
    .dash-stat-label { display: flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }
    .dash-stat-label .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--purple); flex-shrink: 0; }
    .dash-stat-value {
      font-size: 2rem;
      font-weight: 700;
      line-height: 1.15;
      margin-top: 0.35rem;
      color: var(--purple-dark);
      font-variant-numeric: tabular-nums;
    }
    .dash-stat-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
    .dash-stat--free .dot { background: #16a34a; }
    .dash-stat--free .dash-stat-value { color: #15803d; }
    .dash-stat--pending .dot { background: #f59e0b; }
    .dash-stat--pending .dash-stat-value { color: #b45309; }
    .dash-stat--repair .dot { background: #dc2626; }
    .dash-stat--repair .dash-stat-value { color: #b91c1c; }

    .dash-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
      gap: 1rem;
      align-items: start;
    }
    @media (max-width: 960px) {
      .dash-grid { grid-template-columns: minmax(0, 1fr); }
    }
    .dash-side { display: grid; gap: 1rem; }
    .dash-card {
      background: #fff;
      border: 1px solid var(--border-soft);
      border-radius: 1rem;
      padding: 1.15rem 1.25rem 1.25rem;
    }
    .dash-card-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }
    .dash-card-head h2 { font-size: 1.05rem; font-weight: 700; color: var(--purple-dark); }
    .dash-card-hint { font-size: 0.82rem; color: var(--text-muted); margin: -0.5rem 0 0.85rem; }
    .dash-link { font-size: 0.85rem; font-weight: 600; color: var(--purple); text-decoration: none; white-space: nowrap; border-radius: 4px; }
    .dash-link:hover { text-decoration: underline; }

    .bld-list { display: grid; gap: 0.5rem; }
    .bld-row {
      width: 100%;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 0.4rem 1rem;
      align-items: center;
      padding: 0.8rem 0.95rem;
      border: 1px solid transparent;
      border-radius: 0.8rem;
      background: #faf8fd;
      font: inherit;
      color: inherit;
      text-align: left;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    }
    .bld-row:hover { background: #fff; border-color: var(--border-soft); box-shadow: 0 6px 18px rgba(45, 22, 63, 0.07); }
    .bld-name { font-weight: 600; font-size: 0.95rem; color: var(--text-main); }
    .bld-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.1rem; }
    .bld-count { font-size: 0.82rem; color: var(--text-muted); text-align: right; white-space: nowrap; }
    .bld-count strong { color: var(--text-main); font-size: 0.95rem; }
    .bld-bar { grid-column: 1 / -1; height: 8px; border-radius: 999px; background: #ece5f4; overflow: hidden; }
    .bld-bar span { display: block; height: 100%; border-radius: 999px; background: var(--purple); }

    .dash-empty { text-align: center; padding: 1.5rem 1rem 0.75rem; }
    .dash-empty-icon {
      width: 48px;
      height: 48px;
      margin: 0 auto 0.75rem;
      border-radius: 50%;
      background: var(--purple-soft);
      color: var(--purple);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .dash-empty-icon svg { width: 22px; height: 22px; }
    .dash-empty h3 { font-size: 1rem; font-weight: 700; color: var(--purple-dark); }
    .dash-empty p { font-size: 0.86rem; color: var(--text-muted); margin: 0.3rem auto 1rem; max-width: 34ch; line-height: 1.45; }

    .attn-list { list-style: none; }
    .attn-item { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.7rem 0; border-top: 1px solid #f0eaf6; }
    .attn-item:first-child { border-top: none; padding-top: 0.1rem; }
    .attn-title { font-size: 0.88rem; font-weight: 600; color: var(--text-main); line-height: 1.35; }
    .attn-meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; }
    .attn-action { margin-left: auto; font-size: 0.82rem; font-weight: 700; color: #b45309; text-decoration: none; white-space: nowrap; border-radius: 4px; }
    .attn-action:hover { text-decoration: underline; }
    .attn-more { padding-top: 0.6rem; border-top: 1px solid #f0eaf6; font-size: 0.82rem; color: var(--text-muted); }
    .attn-clear { display: flex; align-items: center; gap: 0.6rem; font-size: 0.88rem; color: #15803d; font-weight: 600; }
    .attn-clear svg { width: 20px; height: 20px; flex-shrink: 0; }
    .attn-note { font-size: 0.86rem; color: var(--text-muted); }

    .dash-upload-zone {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 0.95rem 1rem;
      border: 2px dashed rgba(92, 38, 126, 0.25);
      border-radius: 0.9rem;
      background: var(--purple-soft);
      text-decoration: none;
      color: inherit;
      cursor: pointer;
      transition: border-color 0.15s, background 0.15s;
    }
    .dash-upload-zone:hover, .dash-upload-zone.cu-zone--over { border-color: var(--purple); background: #fff; }
    .dash-upload-icon {
      width: 40px;
      height: 40px;
      border-radius: 0.7rem;
      background: #fff;
      color: var(--purple);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .dash-upload-icon svg { width: 20px; height: 20px; }
    .dash-upload-title { font-weight: 700; font-size: 0.92rem; color: var(--purple-dark); }
    .dash-upload-hint { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.1rem; }

    @media (max-width: 640px) {
      .dash-head h1 { font-size: 1.3rem; }
      .dash-actions, .dash-actions .dash-btn { width: 100%; }
      .dash-actions .dash-btn { justify-content: center; }
      .dash-stat { padding: 0.8rem 0.75rem; }
      .dash-stat-label { align-items: flex-start; font-size: 0.76rem; }
      .dash-stat-label .dot { margin-top: 0.3rem; }
      .dash-stat-value { font-size: 1.6rem; }
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 1rem;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff;
      border-radius: 14px;
      padding: 1.5rem;
      max-width: 440px;
      width: 100%;
      max-height: 85vh;
      overflow: auto;
      box-shadow: 0 24px 64px rgba(0,0,0,0.22);
      border: 1px solid var(--border-soft);
    }
    .modal-box h2 {
      font-size: 1.15rem;
      color: var(--purple);
      margin-bottom: 0.75rem;
    }
    .modal-box .sub {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-bottom: 1rem;
    }
    .day-rooms-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .day-rooms-list > .room-floor-section {
      list-style: none;
      margin: 0 0 0.85rem;
      padding: 0;
    }
    .day-rooms-list .room-floor-heading {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--purple);
      margin: 0.35rem 0 0.45rem;
    }
    .day-rooms-list > .room-floor-section:first-child .room-floor-heading {
      margin-top: 0;
    }
    .room-floor-inner-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .room-floor-inner-list li { margin-bottom: 0.4rem; }
    .room-btn {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.65rem 0.85rem;
      border-radius: 10px;
      border: 1px solid var(--border-soft);
      background: linear-gradient(90deg, var(--purple-soft), #fff);
      font: inherit;
      font-weight: 600;
      color: var(--text-main);
      text-align: left;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
    }
    .room-btn:hover { background: #fff; border-color: var(--purple); }
    .room-chip {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      white-space: nowrap;
      background: #dcfce7;
      color: #166534;
    }
    .room-chip--busy { background: #fef3c7; color: #92400e; }
    .room-chip--repair { background: #fee2e2; color: #991b1b; }
    .modal-box .btn-close, .modal-box .btn-back {
      margin-top: 1rem;
      width: 100%;
      padding: 0.55rem 1rem;
      border-radius: 8px;
      border: 1px solid var(--border-soft);
      background: var(--purple-soft);
      color: var(--purple);
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      font-size: 0.9rem;
    }
    .modal-box .btn-close:hover, .modal-box .btn-back:hover {
      background: var(--purple);
      color: #fff;
    }

    /* Dashboard room preview: same visual system as Room schedule (report-schedule-room weekly grid), one day column */
    .schedule-modal-wide {
      max-width: min(1200px, calc(100vw - 1.25rem));
      max-height: 92vh;
    }
    .dash-schedule-meta {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 0.75rem;
    }
    #scheduleModal .weekly-schedule-wrap {
      width: 100%;
      max-height: min(68vh, 640px);
      overflow: auto;
      margin: 0.35rem 0 1rem;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      background: #fff;
      -webkit-overflow-scrolling: touch;
    }
    #scheduleModal .weekly-schedule-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
      table-layout: fixed;
    }
    #scheduleModal .weekly-schedule-table th,
    #scheduleModal .weekly-schedule-table td {
      border: 1px solid #d1d5db;
      padding: 0.28rem 0.4rem;
      vertical-align: top;
    }
    #scheduleModal .weekly-schedule-table thead th {
      background: #f3f4f6;
      font-weight: 600;
      text-align: center;
      color: var(--text-main);
      font-size: 0.8rem;
      padding: 0.45rem 0.35rem;
    }
    #scheduleModal .weekly-schedule-table thead th.weekly-schedule-time-col {
      font-size: 0.88rem;
    }
    #scheduleModal .weekly-schedule-time-col {
      width: 5.25rem;
      white-space: nowrap;
      background: #fafafa;
      font-weight: 500;
      color: var(--text-muted);
    }
    #scheduleModal .weekly-schedule-empty { background: #fff; min-height: 1.35rem; }
    #scheduleModal .weekly-schedule-block {
      background: var(--purple-soft);
      color: var(--text-main);
      position: relative;
    }
    #scheduleModal .weekly-schedule-block.weekly-schedule-invalid { background: #fee2e2; color: #991b1b; font-size: 0.72rem; }
    #scheduleModal .weekly-schedule-block .wb-time { font-size: 0.74rem; color: var(--text-muted); margin-bottom: 0.15rem; }
    #scheduleModal .weekly-schedule-block .wb-subj { font-weight: 600; color: var(--purple); line-height: 1.25; font-size: 0.88rem; }
    #scheduleModal .weekly-schedule-block .wb-code { font-size: 0.72rem; margin-top: 0.15rem; color: var(--text-muted); font-weight: 600; }
    #scheduleModal .weekly-schedule-block .wb-section { font-size: 0.68rem; margin-top: 0.1rem; color: #7a6992; font-weight: 600; }
    #scheduleModal .dash-ws-inner { padding: 0.15rem 0.1rem 0.35rem; }
    .dash-schedule-footer-actions {
      margin-top: 0.75rem;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 0.65rem;
    }
    #scheduleModal .dash-schedule-footer-buttons {
      display: flex;
      flex-direction: row;
      gap: 0.75rem;
      align-items: stretch;
      width: 100%;
    }
    #scheduleModal .dash-schedule-footer-buttons .btn-dash-open-schedule,
    #scheduleModal .dash-schedule-footer-buttons .btn-back {
      flex: 1;
      margin-top: 0;
      min-width: 0;
      box-sizing: border-box;
    }
    #scheduleModal .dash-schedule-footer-buttons .btn-dash-open-schedule {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    @media (max-width: 520px) {
      #scheduleModal .dash-schedule-footer-buttons {
        flex-direction: column;
      }
    }
    .btn-dash-open-schedule {
      display: block;
      text-align: center;
      padding: 0.65rem 1.15rem;
      border: none;
      border-radius: 8px;
      background: var(--purple);
      color: #fff;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      font-family: inherit;
      cursor: pointer;
      transition: background 0.15s, box-shadow 0.15s;
    }
    .btn-dash-open-schedule:hover {
      background: var(--purple-dark);
      box-shadow: 0 2px 8px rgba(92, 38, 126, 0.25);
    }
    .dash-schedule-footer-hint {
      margin: 0;
      font-size: 0.85rem;
      color: var(--text-muted);
      text-align: center;
    }

    @media (max-width: 768px) {
      .nav-bar { width: 75%; max-width: 260px; }
    }

    .topbar-tabs .topbar-datetime {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      gap: 0.05rem;
      font-size: 0.72rem;
      color: #c9b8f3;
      line-height: 1.2;
      text-align: right;
      font-variant-numeric: tabular-nums;
      letter-spacing: 0.02em;
      flex-shrink: 0;
      margin-left: auto;
      order: 99;
      padding-left: 1rem;
      border-left: 1px solid rgba(255, 255, 255, 0.12);
    }
    .topbar-tabs .topbar-datetime .topbar-datetime-date { opacity: 0.95; }
    .topbar-tabs .topbar-datetime .topbar-datetime-time {
      font-size: 0.78rem;
      font-weight: 600;
      color: #fff;
    }
  </style>
@endverbatim
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="home-dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo">
            <img src="../Attri/mculogo.jpg" alt="MCU logo" />
          </div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
@endverbatim
@if ($role === 'super-admin')
@verbatim
        <span class="chip">University Registrar</span>
@endverbatim
@else
@verbatim
        <span class="chip">Records Officer</span>
@endverbatim
@endif
@verbatim
        <div class="topbar-notify" aria-label="Notifications">
          <button class="topbar-notify-btn" type="button">
@endverbatim
            <x-icon-bell />
@verbatim
            <span class="topbar-notify-dot" aria-hidden="true"></span>
          </button>
          <div class="notify-menu" role="menu" aria-label="Notification list"></div>
        </div>
@endverbatim
        <x-profile-menu :role="$role" />
@verbatim
      </div>
    </header>

    <div class="topbar-tabs" aria-label="Room navigation">
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
@endverbatim
      <x-app-nav-button />
@verbatim
    </div>

    </div>
@endverbatim
@if ($role === 'super-admin')
@else
@verbatim

@endverbatim
@endif
@verbatim
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim

      <main class="page">
        <header class="dash-head">
          <div>
            <h1 id="dashGreeting">Dashboard</h1>
            <p class="dash-date" id="dashToday"></p>
          </div>
          <div class="dash-actions">
            <a class="dash-btn dash-btn--primary" href="create-schedule.html">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4" /></svg>
              Create schedule
            </a>
            <a class="dash-btn" href="rooms-available.html">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
              Find a free room
            </a>
          </div>
        </header>

        <section class="dash-stats" aria-label="Today at a glance" aria-busy="true" id="dashStats">
          <a class="dash-stat" href="data-management/facilities.html">
            <div class="dash-stat-label"><span class="dot" aria-hidden="true"></span>Rooms</div>
            <div class="dash-stat-value" id="statRooms"><span class="skeleton" style="width:3rem;height:2rem;margin-top:0.2rem"></span></div>
            <div class="dash-stat-sub" id="statRoomsSub"><span class="skeleton" style="width:65%;height:0.8rem;margin-top:0.35rem"></span><span class="app-nav-sr">Loading</span></div>
          </a>
          <a class="dash-stat dash-stat--free" href="rooms-available.html">
            <div class="dash-stat-label"><span class="dot" aria-hidden="true"></span>Free right now</div>
            <div class="dash-stat-value" id="statFree"><span class="skeleton" style="width:3rem;height:2rem;margin-top:0.2rem"></span></div>
            <div class="dash-stat-sub" id="statFreeSub"><span class="skeleton" style="width:65%;height:0.8rem;margin-top:0.35rem"></span><span class="app-nav-sr">Loading</span></div>
          </a>
          <a class="dash-stat dash-stat--pending" href="requests.html">
            <div class="dash-stat-label"><span class="dot" aria-hidden="true"></span>Pending requests</div>
            <div class="dash-stat-value" id="statPending"><span class="skeleton" style="width:3rem;height:2rem;margin-top:0.2rem"></span></div>
            <div class="dash-stat-sub" id="statPendingSub"><span class="skeleton" style="width:65%;height:0.8rem;margin-top:0.35rem"></span><span class="app-nav-sr">Loading</span></div>
          </a>
          <a class="dash-stat dash-stat--repair" href="data-management/facilities.html">
            <div class="dash-stat-label"><span class="dot" aria-hidden="true"></span>Under maintenance</div>
            <div class="dash-stat-value" id="statRepair"><span class="skeleton" style="width:3rem;height:2rem;margin-top:0.2rem"></span></div>
            <div class="dash-stat-sub" id="statRepairSub"><span class="skeleton" style="width:65%;height:0.8rem;margin-top:0.35rem"></span><span class="app-nav-sr">Loading</span></div>
          </a>
        </section>

        <div class="dash-grid">
          <section class="dash-card" aria-labelledby="bldTitle">
            <div class="dash-card-head">
              <h2 id="bldTitle">Rooms by building</h2>
              <a class="dash-link" href="data-management/facilities.html">Manage buildings</a>
            </div>
            <p class="dash-card-hint" id="bldHint">Select a building to see its floors, rooms, and weekly schedules.</p>
            <div class="bld-list" id="buildingGrid"></div>
            <div class="dash-empty" id="bldEmpty" hidden>
              <div class="dash-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4" /></svg>
              </div>
              <h3>Add your first building</h3>
              <p>Buildings and their rooms show up here, with how many rooms are in use right now.</p>
              <a class="dash-btn dash-btn--primary" href="data-management/facilities.html">Add a building</a>
            </div>
          </section>

          <div class="dash-side">
            <section class="dash-card" aria-labelledby="attnTitle">
              <div class="dash-card-head">
                <h2 id="attnTitle">Needs your attention</h2>
                <a class="dash-link" href="requests.html">View all</a>
              </div>
              <div id="attnBody" aria-live="polite" aria-busy="true"><span class="skeleton" style="height:0.9rem;width:80%;margin:0.2rem 0 0.45rem"></span><span class="skeleton" style="height:0.75rem;width:55%;margin-bottom:1rem"></span><span class="skeleton" style="height:0.9rem;width:70%;margin-bottom:0.45rem"></span><span class="skeleton" style="height:0.75rem;width:45%"></span><span class="app-nav-sr">Loading requests</span></div>
            </section>

            <section class="dash-card" aria-labelledby="uploadTitle">
              <div class="dash-card-head">
                <h2 id="uploadTitle">Add curriculum</h2>
              </div>
              <a class="dash-upload-zone" id="dashCurriculumZone" href="curriculums/curriculums.html"
                 aria-label="Add curriculum: drop a CSV file here, or press to choose one">
                <span class="dash-upload-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5M12 18v-6M9 15l3-3 3 3" /></svg>
                </span>
                <span>
                  <span class="dash-upload-title" style="display:block">Drop a curriculum CSV here</span>
                  <span class="dash-upload-hint" style="display:block">or select to choose a file</span>
                </span>
              </a>
            </section>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div id="roomsModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-labelledby="roomsModalTitle">
      <h2 id="roomsModalTitle">Building</h2>
      <p class="sub" id="roomsModalSub">Tap a room to open its schedule.</p>
      <ul class="day-rooms-list" id="roomsModalList"></ul>
      <button type="button" class="btn-close" onclick="closeRoomsModal()">Close</button>
    </div>
  </div>

  <div id="scheduleModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-box schedule-modal-wide" role="dialog" aria-labelledby="scheduleModalTitle">
      <h2 id="scheduleModalTitle">Room</h2>
      <div class="dash-schedule-meta">
        <span class="sub" style="margin:0;">Monday to Saturday, 7:00am to 7:00pm · read-only preview</span>
      </div>
      <div id="dashWeeklySchedulePreview" class="weekly-schedule-wrap" aria-label="Weekly schedule preview, read-only"></div>
      <div class="dash-schedule-footer-actions">
        <div class="dash-schedule-footer-buttons">
          <a id="dashOpenRoomScheduleBtn" class="btn-dash-open-schedule" href="report-schedule-room.html">Open room schedule</a>
          <button type="button" class="btn-back" onclick="backToRooms()">Back to rooms</button>
        </div>
        <p class="sub dash-schedule-footer-hint">Edit the full weekly timetable, move blocks, and manage this room on the room schedule page.</p>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var navToggle = document.querySelector('.nav-toggle');
      var navBar = document.querySelector('.nav-bar');
      var user = document.querySelector('.topbar-user');
      var userMenu = document.querySelector('.user-menu');
      if (navToggle && navBar) {
        navToggle.addEventListener('click', function () {
          navBar.classList.toggle('nav-bar-open');
          navToggle.classList.toggle('open', navBar.classList.contains('nav-bar-open'));
          navToggle.setAttribute('aria-expanded', navBar.classList.contains('nav-bar-open') ? 'true' : 'false');
        });
      }
      if (user && userMenu) {
        user.addEventListener('click', function (e) {
          e.stopPropagation();
          user.classList.toggle('open');
          user.setAttribute('aria-expanded', user.classList.contains('open') ? 'true' : 'false');
        });
        userMenu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () {
          user.classList.remove('open');
          user.setAttribute('aria-expanded', 'false');
        });
      }
    })();

    var buildingOrder = [];
    var buildingNames = {};
    var roomsByBuilding = {};
    var extraFloorsByBuilding = {};
    var buildingUsesFloors = {};
    var roomFloorById = {};

    function floorOfRoom(bkey, rid) {
      var f =
        window.FacilitiesStore && FacilitiesStore.roomGet
          ? FacilitiesStore.roomGet(roomFloorById, bkey, rid)
          : roomFloorById[rid];
      return f != null ? f : 1;
    }

    function maxFloorsFor(bkey) {
      if (buildingUsesFloors[bkey] === false) return 1;
      var max = 1;
      (roomsByBuilding[bkey] || []).forEach(function (rid) {
        var f = floorOfRoom(bkey, rid);
        if (f > max) max = f;
      });
      (extraFloorsByBuilding[bkey] || []).forEach(function (f) {
        if (f > max) max = f;
      });
      return max;
    }

    function applyFacilitiesSnapshot(p) {
      if (!p || typeof p !== 'object') return;
      if (p.buildingOrder) buildingOrder = p.buildingOrder;
      if (p.buildingNames) buildingNames = p.buildingNames;
      if (p.roomsByBuilding) roomsByBuilding = p.roomsByBuilding;
      if (p.roomFloorById) roomFloorById = p.roomFloorById;
      if (p.extraFloorsByBuilding) extraFloorsByBuilding = p.extraFloorsByBuilding;
      if (p.buildingUsesFloors) buildingUsesFloors = p.buildingUsesFloors;
      if (p.roomStatus) roomStatus = p.roomStatus;
    }

    function getRoomsGroupedByFloor(bkey) {
      var rids = roomsByBuilding[bkey] || [];
      var byFloor = {};
      rids.forEach(function (rid) {
        var f = floorOfRoom(bkey, rid);
        if (!byFloor[f]) byFloor[f] = [];
        byFloor[f].push(rid);
      });
      Object.keys(byFloor).forEach(function (fk) {
        byFloor[fk].sort(function (a, b) {
          return parseInt(a, 10) - parseInt(b, 10);
        });
      });
      return Object.keys(byFloor)
        .map(function (k) {
          return parseInt(k, 10);
        })
        .sort(function (a, b) {
          return a - b;
        })
        .map(function (f) {
          return { floor: f, rooms: byFloor[f] };
        });
    }

    // Real room data from Facilities (filled by applyFacilitiesSnapshot).
    var roomStatus = {};
    var DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    var currentBuildingKey = '';
    var currentBuildingLabel = '';
    var currentRoomId = '';

    /** Match report-schedule-room.html: 7:00am – 7:00pm, 30-minute rows; Mon–Sat columns */
    var DASH_SCHED_START_MIN = 7 * 60;
    var DASH_SCHED_END_MIN = 19 * 60;
    var DASH_GRID_ROWS = Math.floor((DASH_SCHED_END_MIN - DASH_SCHED_START_MIN) / 30) + 1;
    var DASH_WEEK_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    var DASH_DAY_HEADER = {
      mon: 'Monday',
      tue: 'Tuesday',
      wed: 'Wednesday',
      thu: 'Thursday',
      fri: 'Friday',
      sat: 'Saturday'
    };
    var DASH_WS_SLOT_COLORS = ['#ede9fe', '#dcfce7', '#fef3c7', '#fee2e2', '#dbeafe', '#ffedd5'];

    function dashSlotBlockBg(slot) {
      var h = 0;
      var s =
        String((slot && slot.label) || '') +
        String((slot && slot.code) || '') +
        String((slot && slot.section) || '');
      for (var i = 0; i < s.length; i++) h = (h + s.charCodeAt(i) * 13) % 997;
      return DASH_WS_SLOT_COLORS[h % DASH_WS_SLOT_COLORS.length];
    }

    function parseDashTime(s) {
      if (s == null) return null;
      var str = String(s).trim();
      if (!str) return null;
      var lo = str.toLowerCase();
      var hasPm = lo.indexOf('pm') !== -1;
      var hasAm = lo.indexOf('am') !== -1 && !hasPm;
      var core = lo.replace(/(am|pm)/gi, '').replace(/\s+/g, ' ').trim();
      var hourOnly = core.match(/^(\d{1,2})$/);
      if (hourOnly && (hasAm || hasPm)) {
        var hx = parseInt(hourOnly[1], 10);
        if (isNaN(hx)) return null;
        if (hasPm) {
          if (hx < 12) hx += 12;
        } else if (hasAm && hx === 12) {
          hx = 0;
        }
        return hx * 60;
      }
      var m = core.match(/^(\d{1,2})\s*:\s*(\d{2})$/);
      if (!m) return null;
      var h = parseInt(m[1], 10);
      var min = parseInt(m[2], 10);
      if (isNaN(h) || isNaN(min) || min < 0 || min > 59) return null;
      if (!hasAm && !hasPm && h >= 13 && h <= 23) {
        return h * 60 + min;
      }
      if (hasPm) {
        if (h !== 12) h += 12;
      } else if (hasAm) {
        if (h === 12) h = 0;
      } else {
        if (h >= 1 && h <= 6) h += 12;
      }
      return h * 60 + min;
    }

    function formatDashMinutesAmPm(totalMin) {
      var tm = Math.round(totalMin);
      var h24 = Math.floor(tm / 60) % 24;
      var mins = tm % 60;
      var isPm = h24 >= 12;
      var h12 = h24 % 12;
      if (h12 === 0) h12 = 12;
      var mm = mins < 10 ? '0' + mins : String(mins);
      return h12 + ':' + mm + (isPm ? 'pm' : 'am');
    }

    function normalizeDashSlot(s) {
      if (!s || typeof s !== 'object') {
        return { start: '', end: '', label: '', code: '', section: '' };
      }
      var lab = s.label != null && s.label !== '' ? String(s.label) : s.subject != null ? String(s.subject) : '';
      var cod = s.code != null && s.code !== '' ? String(s.code) : s.classCode != null ? String(s.classCode) : '';
      return {
        start: s.start != null ? String(s.start) : '',
        end: s.end != null ? String(s.end) : '',
        label: lab,
        code: cod,
        section: s.section != null ? String(s.section) : ''
      };
    }

    function buildDashColumnMeta(slots) {
      var numRows = DASH_GRID_ROWS;
      var meta = new Array(numRows);
      for (var i = 0; i < numRows; i++) meta[i] = null;
      var normSlots = (slots || []).map(normalizeDashSlot);
      normSlots.forEach(function (slot, slotIndex) {
        var sm = parseDashTime(slot.start);
        var em = parseDashTime(slot.end);
        if (sm == null || em == null || em <= sm) return;
        sm = Math.max(sm, DASH_SCHED_START_MIN);
        em = Math.min(em, DASH_SCHED_END_MIN);
        if (em <= sm) return;
        var r0 = Math.floor((sm - DASH_SCHED_START_MIN) / 30);
        var r1 = Math.ceil((em - DASH_SCHED_START_MIN) / 30);
        r0 = Math.max(0, Math.min(numRows - 1, r0));
        r1 = Math.max(r0 + 1, Math.min(numRows, r1));
        var rs = r1 - r0;
        var ok = true;
        for (var r = r0; r < r1; r++) {
          if (meta[r] !== null) {
            ok = false;
            break;
          }
        }
        if (!ok) return;
        meta[r0] = { slot: slot, rowspan: rs, slotIndex: slotIndex };
        for (var r = r0 + 1; r < r1; r++) {
          meta[r] = 'skip';
        }
      });
      return meta;
    }

    function escapeHtml(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function plural(n, one, many) {
      return n + ' ' + (n === 1 ? one : many);
    }

    function roomLookup(map, bkey, rid) {
      return window.FacilitiesStore && FacilitiesStore.roomGet
        ? FacilitiesStore.roomGet(map, bkey, rid)
        : (map || {})[rid];
    }

    // Class bookings for the active term (api/room-availability.php, the same
    // data Rooms available uses), keyed "building::room" then day ("mon"...).
    var classBookings = {};

    function amPm(hhmm) {
      var p = String(hhmm || '').split(':');
      var h = parseInt(p[0], 10);
      if (isNaN(h)) return '';
      return (h % 12 || 12) + ':' + (p[1] || '00') + (h >= 12 ? ' PM' : ' AM');
    }

    function loadClassBookings() {
      if (!window.McuApi) return Promise.resolve();
      return McuApi.get('room-availability.php').then(function (res) {
        classBookings = {};
        (res.bookings || []).forEach(function (b) {
          var key = (b.building + '::' + b.room).toLowerCase();
          var day = String(b.day || '').toLowerCase().slice(0, 3);
          (classBookings[key] = classBookings[key] || {});
          (classBookings[key][day] = classBookings[key][day] || []).push({
            start: amPm(b.start),
            end: amPm(b.end),
            label: b.course + (b.title ? ' ' + b.title : ''),
            code: b.course,
            section: b.section || '',
          });
        });
      }).catch(function () {});
    }

    function roomSlots(bkey, rid, dayKey) {
      var byDay = classBookings[(bkey + '::' + rid).toLowerCase()];
      return byDay && byDay[dayKey] ? byDay[dayKey] : [];
    }

    // free / busy (in a class right now) / repair (under maintenance)
    function roomState(bkey, rid, now) {
      if (String(roomLookup(roomStatus, bkey, rid) || 'active') === 'under_maintenance') return { kind: 'repair' };
      var mins = now.getHours() * 60 + now.getMinutes();
      var slots = roomSlots(bkey, rid, DAY_KEYS[now.getDay()]);
      for (var i = 0; i < slots.length; i++) {
        var s = normalizeDashSlot(slots[i]);
        var a = parseDashTime(s.start);
        var b = parseDashTime(s.end);
        if (a != null && b != null && b > a && mins >= a && mins < b) return { kind: 'busy', until: b };
      }
      return { kind: 'free' };
    }

    function setStat(id, value, sub) {
      document.getElementById(id).textContent = value;
      document.getElementById(id + 'Sub').textContent = sub;
    }

    function renderStats(now) {
      var total = 0;
      var busy = 0;
      var repair = 0;
      var classesToday = 0;
      var today = DAY_KEYS[now.getDay()];
      buildingOrder.forEach(function (key) {
        (roomsByBuilding[key] || []).forEach(function (rid) {
          total++;
          var kind = roomState(key, rid, now).kind;
          if (kind === 'busy') busy++;
          else if (kind === 'repair') repair++;
          classesToday += roomSlots(key, rid, today).length;
        });
      });
      var nb = buildingOrder.length;
      setStat('statRooms', String(total), nb ? 'in ' + plural(nb, 'building', 'buildings') : 'No buildings yet');
      var freeSub = !total ? 'Add rooms to see this' : classesToday ? busy + ' in use' : 'No classes scheduled today';
      setStat('statFree', String(total - busy - repair), freeSub);
      setStat('statRepair', String(repair), repair ? 'Can’t be booked' : 'All rooms in service');
    }

    // false until FacilitiesStore has the real data (avoids flashing the empty state)
    var dashDataReady = false;

    function renderBuildingCards() {
      var grid = document.getElementById('buildingGrid');
      var now = new Date();
      if (!dashDataReady) {
        grid.setAttribute('aria-busy', 'true');
        grid.innerHTML = [1, 2, 3].map(function () {
          return '<div class="bld-row" aria-hidden="true" style="cursor:default">' +
            '<span><span class="skeleton" style="width:9rem;height:0.95rem"></span>' +
            '<span class="skeleton" style="width:12rem;height:0.75rem;margin-top:0.4rem"></span></span>' +
            '<span class="skeleton" style="width:5rem;height:0.85rem"></span>' +
            '<span class="bld-bar"></span></div>';
        }).join('') + '<span class="app-nav-sr">Loading buildings</span>';
        return;
      }
      grid.removeAttribute('aria-busy');
      grid.innerHTML = '';
      document.getElementById('bldEmpty').hidden = buildingOrder.length > 0;
      document.getElementById('bldHint').hidden = buildingOrder.length === 0;
      buildingOrder.forEach(function (key) {
        var name = buildingNames[key] || key;
        var rooms = roomsByBuilding[key] || [];
        var busy = 0;
        var repair = 0;
        rooms.forEach(function (rid) {
          var kind = roomState(key, rid, now).kind;
          if (kind === 'busy') busy++;
          else if (kind === 'repair') repair++;
        });
        var bookable = rooms.length - repair;
        var pct = bookable ? Math.round((busy / bookable) * 100) : 0;
        var meta = plural(maxFloorsFor(key), 'floor', 'floors') + ' · ' + plural(rooms.length, 'room', 'rooms') +
          (repair ? ' · ' + repair + ' under maintenance' : '');
        var count = rooms.length ? '<strong>' + busy + '</strong> of ' + bookable + ' in use' : 'No rooms yet';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bld-row';
        btn.setAttribute('aria-label', name + '. ' + meta + '. ' +
          (rooms.length ? busy + ' of ' + bookable + ' rooms in use now.' : 'No rooms yet.') + ' Show rooms.');
        btn.innerHTML =
          '<span><span class="bld-name">' + escapeHtml(name) + '</span>' +
          '<span class="bld-meta" style="display:block">' + escapeHtml(meta) + '</span></span>' +
          '<span class="bld-count">' + count + '</span>' +
          '<span class="bld-bar" aria-hidden="true"><span style="width:' + pct + '%"></span></span>';
        btn.addEventListener('click', function () {
          openRoomsModal(key);
        });
        grid.appendChild(btn);
      });
      renderStats(now);
      document.getElementById('dashStats').removeAttribute('aria-busy');
    }

    function openRoomsModal(buildingKey) {
      currentBuildingKey = buildingKey;
      currentBuildingLabel = buildingNames[buildingKey] || buildingKey;
      document.getElementById('roomsModalTitle').textContent = currentBuildingLabel + ' – Rooms';
      document.getElementById('roomsModalSub').textContent =
        plural(maxFloorsFor(buildingKey), 'floor', 'floors') + '. Select a room to see its weekly schedule.';
      var now = new Date();
      var list = document.getElementById('roomsModalList');
      list.innerHTML = '';
      getRoomsGroupedByFloor(buildingKey).forEach(function (group) {
        var sectionLi = document.createElement('li');
        sectionLi.className = 'room-floor-section';
        var head = document.createElement('div');
        head.className = 'room-floor-heading';
        head.textContent = 'Floor ' + group.floor;
        sectionLi.appendChild(head);
        var inner = document.createElement('ul');
        inner.className = 'room-floor-inner-list';
        group.rooms.forEach(function (rid) {
          var li = document.createElement('li');
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'room-btn';
          var state = roomState(buildingKey, rid, now);
          var chip = state.kind === 'repair'
            ? ['room-chip room-chip--repair', 'Under maintenance']
            : state.kind === 'busy'
              ? ['room-chip room-chip--busy', 'In use until ' + formatDashMinutesAmPm(state.until)]
              : ['room-chip', 'Free now'];
          btn.innerHTML = '<span>Room ' + escapeHtml(rid) + '</span><span class="' + chip[0] + '">' + chip[1] + '</span>';
          btn.addEventListener('click', function () {
            openScheduleModal(rid);
          });
          li.appendChild(btn);
          inner.appendChild(li);
        });
        sectionLi.appendChild(inner);
        list.appendChild(sectionLi);
      });
      if (!rids(buildingKey).length) {
        list.innerHTML = '<li class="room-floor-section"><p class="sub">No rooms in this building yet. Add them on the Facilities page.</p></li>';
      }
      document.getElementById('roomsModal').classList.add('open');
      document.getElementById('roomsModal').setAttribute('aria-hidden', 'false');
      var first = list.querySelector('.room-btn');
      if (first) first.focus();
    }

    function rids(bkey) {
      return roomsByBuilding[bkey] || [];
    }

    function closeRoomsModal() {
      document.getElementById('roomsModal').classList.remove('open');
      document.getElementById('roomsModal').setAttribute('aria-hidden', 'true');
    }

    function getSlotsForRoomDay(roomId, dayKey) {
      return roomSlots(currentBuildingKey, roomId, dayKey);
    }

    function appendDashScheduleCell(td, slot) {
      var sm = parseDashTime(slot.start);
      var em = parseDashTime(slot.end);
      td.className = 'weekly-schedule-block';
      if (sm == null || em == null || em <= sm) {
        td.className = 'weekly-schedule-block weekly-schedule-invalid';
        td.textContent = 'Invalid time';
        return;
      }
      sm = Math.max(sm, DASH_SCHED_START_MIN);
      em = Math.min(em, DASH_SCHED_END_MIN);
      td.style.background = dashSlotBlockBg(slot);
      var inner = document.createElement('div');
      inner.className = 'dash-ws-inner';
      var divT = document.createElement('div');
      divT.className = 'wb-time';
      divT.textContent = formatDashMinutesAmPm(sm) + ' – ' + formatDashMinutesAmPm(em);
      var divS = document.createElement('div');
      divS.className = 'wb-subj';
      divS.textContent = (slot.label || '').trim() || '—';
      inner.appendChild(divT);
      inner.appendChild(divS);
      if ((slot.section || '').trim()) {
        var divSec = document.createElement('div');
        divSec.className = 'wb-section';
        divSec.textContent = (slot.section || '').trim();
        inner.appendChild(divSec);
      }
      if ((slot.code || '').trim()) {
        var divC = document.createElement('div');
        divC.className = 'wb-code';
        divC.textContent = slot.code;
        inner.appendChild(divC);
      }
      td.appendChild(inner);
    }

    function renderSchedulePanel() {
      document.getElementById('scheduleModalTitle').textContent =
        'Room ' +
        currentRoomId +
        ' · ' +
        currentBuildingLabel +
        ' · Floor ' +
        (currentBuildingKey ? floorOfRoom(currentBuildingKey, currentRoomId) : '—');

      var openBtn = document.getElementById('dashOpenRoomScheduleBtn');
      if (openBtn && currentBuildingKey && currentRoomId) {
        openBtn.href =
          'report-schedule-room.html?building=' +
          encodeURIComponent(currentBuildingKey) +
          '&room=' +
          encodeURIComponent(currentRoomId);
      }

      var wrap = document.getElementById('dashWeeklySchedulePreview');
      if (!wrap) return;

      var colMetaByDay = {};
      DASH_WEEK_DAYS.forEach(function (d) {
        colMetaByDay[d] = buildDashColumnMeta(getSlotsForRoomDay(currentRoomId, d));
      });

      var table = document.createElement('table');
      table.className = 'weekly-schedule-table';
      var thead = document.createElement('thead');
      var trh = document.createElement('tr');
      var th0 = document.createElement('th');
      th0.className = 'weekly-schedule-time-col';
      th0.textContent = 'Time';
      trh.appendChild(th0);
      DASH_WEEK_DAYS.forEach(function (d) {
        var th = document.createElement('th');
        th.textContent = DASH_DAY_HEADER[d] || d;
        trh.appendChild(th);
      });
      thead.appendChild(trh);
      table.appendChild(thead);

      var tbody = document.createElement('tbody');
      for (var r = 0; r < DASH_GRID_ROWS; r++) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-grid-row', String(r));
        var tdT = document.createElement('td');
        tdT.className = 'weekly-schedule-time-col';
        tdT.textContent = formatDashMinutesAmPm(DASH_SCHED_START_MIN + r * 30);
        tr.appendChild(tdT);
        DASH_WEEK_DAYS.forEach(function (d) {
          var m = colMetaByDay[d][r];
          if (m === 'skip') return;
          var td = document.createElement('td');
          td.setAttribute('data-day', d);
          td.setAttribute('data-grid-row', String(r));
          if (m && m.slot) {
            var slot = m.slot;
            td.rowSpan = m.rowspan;
            appendDashScheduleCell(td, slot);
          } else {
            td.className = 'weekly-schedule-empty';
          }
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      }
      table.appendChild(tbody);

      wrap.innerHTML = '';
      var hasClasses = DASH_WEEK_DAYS.some(function (d) {
        return getSlotsForRoomDay(currentRoomId, d).length > 0;
      });
      if (!hasClasses) {
        var note = document.createElement('p');
        note.className = 'sub';
        note.textContent = 'No classes use this room in the published timetable.';
        wrap.appendChild(note);
      }
      wrap.appendChild(table);
    }

    function openScheduleModal(roomId) {
      currentRoomId = roomId;
      renderSchedulePanel();
      closeRoomsModal();
      document.getElementById('scheduleModal').classList.add('open');
      document.getElementById('scheduleModal').setAttribute('aria-hidden', 'false');
    }

    function backToRooms() {
      document.getElementById('scheduleModal').classList.remove('open');
      document.getElementById('scheduleModal').setAttribute('aria-hidden', 'true');
      if (currentBuildingKey) openRoomsModal(currentBuildingKey);
    }

    document.getElementById('roomsModal').addEventListener('click', function (e) {
      if (e.target === this) closeRoomsModal();
    });
    document.getElementById('scheduleModal').addEventListener('click', function (e) {
      if (e.target === this) {
        document.getElementById('scheduleModal').classList.remove('open');
        document.getElementById('scheduleModal').setAttribute('aria-hidden', 'true');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      ['scheduleModal', 'roomsModal'].forEach(function (id) {
        var m = document.getElementById(id);
        if (m.classList.contains('open')) {
          m.classList.remove('open');
          m.setAttribute('aria-hidden', 'true');
        }
      });
    });

    // Greeting and "Needs your attention" (requests waiting for the registrar)
    function renderGreeting(name) {
      var d = new Date();
      var h = d.getHours();
      var part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
      // full name: accounts are often named by office ("University Registrar"),
      // where the first word alone reads oddly
      var full = String(name || '').trim();
      document.getElementById('dashGreeting').textContent = full ? part + ', ' + full : part;
      document.getElementById('dashToday').textContent =
        d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }

    function timeAgo(iso) {
      var then = new Date(iso);
      if (isNaN(then.getTime())) return '';
      var days = Math.floor((Date.now() - then.getTime()) / 86400000);
      if (days <= 0) return 'today';
      if (days === 1) return 'yesterday';
      return days + ' days ago';
    }

    function loadAttention() {
      var body = document.getElementById('attnBody');
      var settle = function () { body.removeAttribute('aria-busy'); };
      if (!window.McuApi) {
        setStat('statPending', '–', 'Couldn’t load');
        body.innerHTML = '<p class="attn-note">Requests can’t load because the server isn’t reachable.</p>';
        return;
      }
      McuApi.get('requests.php')
        .then(function (res) {
          settle();
          var pending = (res.textRequests || []).filter(function (r) {
            return r.status === 'pending_admin';
          });
          var oldest = pending.reduce(function (o, r) {
            return !o || new Date(r.at) < new Date(o.at) ? r : o;
          }, null);
          setStat('statPending', String(pending.length), oldest ? 'Oldest from ' + timeAgo(oldest.at) : 'All caught up');
          if (!pending.length) {
            body.innerHTML =
              '<div class="attn-clear"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="m8 12 3 3 5-6" /></svg>' +
              'You’re all caught up. No requests are waiting.</div>';
            return;
          }
          var items = pending.slice(0, 3).map(function (r) {
            var what = r.classLabel || (r.topicLabels || []).join(', ') || 'Schedule change request';
            return '<li class="attn-item"><div><div class="attn-title">' + escapeHtml(what) + '</div>' +
              '<div class="attn-meta">' + escapeHtml(r.title || '') + ' · ' + escapeHtml(timeAgo(r.at)) + '</div></div>' +
              '<a class="attn-action" href="requests.html">Review</a></li>';
          });
          body.innerHTML = '<ul class="attn-list">' + items.join('') + '</ul>' +
            (pending.length > 3 ? '<p class="attn-more">' + (pending.length - 3) + ' more waiting</p>' : '');
        })
        .catch(function () {
          settle();
          setStat('statPending', '–', 'Couldn’t load');
          body.innerHTML = '<p class="attn-note">Couldn’t load requests. Refresh the page to try again.</p>';
        });
    }

    renderGreeting('');
    renderBuildingCards();
    // keep "in use now" current
    setInterval(renderBuildingCards, 60000);
  </script>
  <script>
    (function () {
      var el = document.getElementById('topbar-datetime');
      if (!el) return;
      function tick() {
        var d = new Date();
        var opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
        var tOpts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
        el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, opts) +
          '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, tOpts) + '</span>';
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="curriculum-store" />
  <x-legacy-js name="curriculum-upload" />
  <x-legacy-js name="facilities-store" />
  <x-legacy-js name="data-boot" />
@verbatim
  <script>
    (window.MCUNexReady || Promise.resolve()).then(function () {
      window.CurriculumUpload.attach(document.getElementById('dashCurriculumZone'));
      if (window.FacilitiesStore && typeof applyFacilitiesSnapshot === 'function') {
        applyFacilitiesSnapshot(FacilitiesStore.snapshot());
      }
      loadAttention();
      // keep the placeholders until the class bookings are in, so rooms don't flash "free"
      loadClassBookings().then(function () {
        dashDataReady = true;
        renderBuildingCards();
      });
      if (window.McuApi) {
        McuApi.get('auth.php').then(function (res) {
          if (res && res.user) renderGreeting(res.user.name);
        }).catch(function () {});
      }
    });
  </script>
@endverbatim
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
