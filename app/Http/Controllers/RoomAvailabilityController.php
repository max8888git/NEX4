<?php

namespace App\Http\Controllers;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: which rooms are booked, and when, in the active term
 * (GET api/room-availability.php). Used by Rooms available and the
 * dashboard's "Free right now".
 *
 * Bookings come from the published (VPAA-approved) timetable, which the
 * schedule workflow stores as JSON in term_schedule_approvals.published_json.
 * Each meeting's room id is mapped to the building and room codes the
 * Facilities data uses.
 */
class RoomAvailabilityController extends Controller
{
    private const TERM_LABEL = ['1' => '1st semester', '2' => '2nd semester', 'summer' => 'Summer'];

    public function handle()
    {
        if (! RegistrarSession::user()) {
            return response()->json(['ok' => false, 'error' => 'Log in to see room availability.'], 401);
        }

        $term = DB::table('terms as t')
            ->join('school_years as y', 'y.school_year_id', '=', 't.school_year_id')
            ->where('t.is_active', 1)
            ->orderByDesc('y.is_current')
            ->orderByDesc('y.start_year')
            ->first(['t.term_id', 't.term_code', 'y.label']);
        if (! $term) {
            return response()->json(['ok' => true, 'term' => null, 'published' => null, 'bookings' => []]);
        }

        $approval = DB::table('term_schedule_approvals')
            ->where('term_id', $term->term_id)
            ->first(['published_json', 'published_at']);
        $payload = $approval && $approval->published_json ? json_decode((string) $approval->published_json, true) : null;

        return response()->json([
            'ok' => true,
            'term' => [
                'id' => (int) $term->term_id,
                'label' => $term->label.', '.(self::TERM_LABEL[(string) $term->term_code] ?? $term->term_code),
            ],
            'published' => is_array($payload) ? ['at' => $approval->published_at] : null,
            'bookings' => is_array($payload) ? $this->bookings($payload) : [],
        ]);
    }

    /** One entry per room meeting in the published timetable. */
    private function bookings(array $payload): array
    {
        // room id ("r-41") -> building and room codes as Facilities knows them
        $rooms = DB::table('rooms as r')
            ->join('floors as f', 'f.floor_id', '=', 'r.floor_id')
            ->join('buildings as b', 'b.building_id', '=', 'f.building_id')
            ->get(['r.room_id', 'r.room_code', 'b.building_code'])
            ->keyBy('room_id');

        $out = [];
        $seen = [];
        foreach ($payload['classes'] ?? [] as $class) {
            $meetings = $class['meetings'] ?? [];
            if (is_string($meetings)) {
                $meetings = json_decode($meetings, true) ?: [];
            }
            foreach ($meetings as $m) {
                if (! preg_match('/^r-(\d+)$/', (string) ($m['roomId'] ?? ''), $id) || ! isset($rooms[(int) $id[1]])) {
                    continue;
                }
                $room = $rooms[(int) $id[1]];
                $day = (string) ($m['day'] ?? '');
                $start = substr((string) ($m['start'] ?? ''), 0, 5);
                $end = substr((string) ($m['end'] ?? ''), 0, 5);
                $course = (string) ($class['courseCode'] ?? '');
                // merged sections share one meeting; list it once
                $key = implode('|', [$room->room_id, $day, $start, $end, $course]);
                if ($day === '' || $start === '' || $end === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'building' => (string) $room->building_code,
                    'room' => (string) $room->room_code,
                    'day' => $day,
                    'start' => $start,
                    'end' => $end,
                    'course' => $course,
                    'title' => (string) ($class['courseTitle'] ?? ''),
                    'component' => (string) ($class['component'] ?? ''),
                    'section' => (string) ($class['sectionName'] ?? ''),
                    'college' => (string) ($class['collegeName'] ?? ''),
                    'students' => isset($class['studentCount']) ? (int) $class['studentCount'] : null,
                ];
            }
        }

        return $out;
    }
}
