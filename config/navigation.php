<?php

/*
 * Sidebar navigation for each role folder, rendered by <x-app-nav>.
 *
 * Each item: label, page (link target, relative to the role folder, without
 * .html), icon (key in components/app-nav-icon), match (pages that highlight
 * the item; a trailing * matches a folder) and optional badge (filled in by
 * resources/js/legacy/app-nav.js).
 */

$staff = [
    'Overview' => [
        ['label' => 'Dashboard', 'page' => 'home-dashboard', 'icon' => 'dashboard', 'match' => ['home-dashboard', 'dashboard']],
    ],
    'Scheduling' => [
        ['label' => 'Create schedule', 'page' => 'create-schedule', 'icon' => 'calendar', 'match' => ['create-schedule']],
        ['label' => 'Rooms available', 'page' => 'rooms-available', 'icon' => 'door', 'match' => ['rooms-available', 'rooms-available-filters', 'data-management/rooms-available-filters']],
        ['label' => 'Reports', 'page' => 'final-report', 'icon' => 'report', 'match' => ['final-report', 'report-schedules', 'report-schedule-room']],
    ],
    'Academic data' => [
        ['label' => 'Data management', 'page' => 'data-management/college-departments', 'icon' => 'building', 'match' => ['data-management', 'data-management/*']],
        ['label' => 'Curriculums', 'page' => 'curriculums/curriculums', 'icon' => 'book', 'match' => ['curriculums/*', 'data-management/add-curriculum']],
    ],
    'Administration' => [
        ['label' => 'Requests', 'page' => 'requests', 'icon' => 'inbox', 'match' => ['requests'], 'badge' => 'pending-requests'],
        ['label' => 'Account management', 'page' => 'account-management', 'icon' => 'user-cog', 'match' => ['account-management', 'account-create', 'users']],
        ['label' => 'Transaction log', 'page' => 'logs', 'icon' => 'history', 'match' => ['logs']],
    ],
];

$viewer = [
    'Overview' => [
        ['label' => 'Dashboard', 'page' => 'dashboard', 'icon' => 'dashboard', 'match' => ['dashboard']],
    ],
    'Schedules' => [
        ['label' => 'Reports', 'page' => 'final-report', 'icon' => 'report', 'match' => ['final-report', 'department-report']],
    ],
    'Requests' => [
        ['label' => 'Requests', 'page' => 'viewer-requests', 'icon' => 'inbox', 'match' => ['viewer-requests']],
    ],
];

// VPAA: also has the room schedule viewer, and approves instead of requesting.
$vpaa = $viewer;

// Deans and Program Chairs make the curriculums (curriculum-builder).
$maker = $viewer;
$maker['Schedules'] = array_merge(
    [['label' => 'Curriculum', 'page' => 'curriculum-builder', 'icon' => 'book', 'match' => ['curriculum-builder']]],
    $viewer['Schedules'],
);
$vpaa['Schedules'] = array_merge(
    [['label' => 'Room schedule', 'page' => 'viewer', 'icon' => 'door', 'match' => ['viewer', 'viewer-rooms']]],
    $viewer['Schedules'],
);
$vpaa['Requests'] = [
    ['label' => 'Approval', 'page' => 'viewer-requests', 'icon' => 'inbox', 'match' => ['viewer-requests']],
];

return [
    'admin' => $staff,
    'super-admin' => $staff,
    'viewing' => $maker,
    'viewing-program-head' => $maker,
    'viewing-with-approval' => $vpaa,
];
