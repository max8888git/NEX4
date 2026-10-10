<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRoomRequirement extends RegistrarModel
{
    protected $table = 'course_room_requirements';

    protected $primaryKey = 'requirement_id';

    public $timestamps = false;

    public function course(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'curriculum_course_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }
}
